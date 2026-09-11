<?php
declare(strict_types=1);

/**
 * RiskOps - Giriş doğrulama (POST handler)
 * /var/www/riskops/auth/authenticate.php
 *
 * Akış:
 *   1. POST + CSRF zorunlu
 *   2. Girdi doğrulama
 *   3. Brute-force kontrolü (login_attempts)
 *   4. Kullanıcı arama + password_verify (zamanlama sızıntısına karsi sabit is)
 *   5. Hesap durumu kontrolü
 *   6. Başarılı: session_regenerate_id, last_login_at, audit, yönlendirme
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();   // POST degilse 405, token gecersizse 403

/* ------------------------------------------------------------------ */
/* 1) Girdi                                                            */
/* ------------------------------------------------------------------ */

$email    = input('email');
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$ip       = client_ip();
$now      = date('Y-m-d H:i:s');

/** Başarısız denemeyi kaydeder ve giriş ekranina döner. */
$failAndRedirect = static function (string $message, string $email, string $ip,
                                    string $auditAction = 'login_failed',
                                    ?int $userId = null): never {
    try {
        $stmt = db()->prepare(
            'INSERT INTO login_attempts (email, ip_address, success, user_agent, attempted_at)
             VALUES (:e, :ip, 0, :ua, NOW())'
        );
        $stmt->execute([':e' => mb_substr($email, 0, 150), ':ip' => $ip, ':ua' => client_agent()]);
    } catch (Throwable $e) {
        app_log('error', 'login_attempts write failed: ' . $e->getMessage());
    }

    audit($auditAction, 'user', $userId, null, ['email' => $email], $userId, null);

    old_set(['email' => $email]);
    flash('error', $message);
    redirect('/auth/login.php');
};

if ($email === null || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $failAndRedirect('E-posta veya parola hatalı.', (string)$email, $ip, 'login_invalid_input');
}

/* ------------------------------------------------------------------ */
/* 2) Brute-force kontrolü                                             */
/* ------------------------------------------------------------------ */

$maxAttempts = max(1, (int)setting('max_login_attempts', 5));
$lockout     = max(60, (int)setting('lockout_duration', 900));
$since       = date('Y-m-d H:i:s', time() - $lockout);

$stmt = db()->prepare(
    'SELECT
        COALESCE(SUM(email = :email), 0)   AS by_email,
        COALESCE(SUM(ip_address = :ip), 0) AS by_ip
     FROM login_attempts
     WHERE success = 0 AND attempted_at > :since'
);
$stmt->execute([':email' => $email, ':ip' => $ip, ':since' => $since]);
$attempts = $stmt->fetch() ?: ['by_email' => 0, 'by_ip' => 0];

// E-posta bazlı kilit: hedefli saldırıyı durdurur.
// IP bazlı kilit daha genis tutulur ki ortak NAT arkasindaki
// meşru kullanıcılar birbirini kilitlemesin.
$emailLocked = (int)$attempts['by_email'] >= $maxAttempts;
$ipLocked    = (int)$attempts['by_ip']    >= $maxAttempts * 5;

if ($emailLocked || $ipLocked) {
    app_log('warning', 'Login blocked by rate limit', [
        'email'    => $email,
        'ip'       => $ip,
        'by_email' => (int)$attempts['by_email'],
        'by_ip'    => (int)$attempts['by_ip'],
    ]);
    audit('login_rate_limited', 'user', null, null, ['email' => $email, 'ip' => $ip]);

    $minutes = (int)ceil($lockout / 60);
    old_set(['email' => $email]);
    flash('error', "Çok fazla başarısız giriş denemesi. Lütfen {$minutes} dakika sonra tekrar deneyin.");
    redirect('/auth/login.php');
}

/* ------------------------------------------------------------------ */
/* 3) Kullanıcıyı bul ve parolayı dogrula                              */
/* ------------------------------------------------------------------ */

$stmt = db()->prepare(
    'SELECT id, name, email, password, role, status, department_id, must_change_password
     FROM users
     WHERE email = :email
     LIMIT 1'
);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Kullanıcı yoksa da password_verify çalıştırılır: "kullanıcı var mi yok mu"
// bilgisinin yanıt süresinden sızmasını engeller.
$hash = is_array($user) && isset($user['password'])
    ? (string)$user['password']
    : auth_timing_equalizer_hash();

$passwordOk = password_verify($password, $hash);

if (!is_array($user) || !$passwordOk) {
    $failAndRedirect(
        'E-posta veya parola hatalı.',
        $email,
        $ip,
        'login_failed',
        is_array($user) ? (int)$user['id'] : null
    );
}

/* ------------------------------------------------------------------ */
/* 4) Hesap durumu                                                     */
/* ------------------------------------------------------------------ */

if ((int)$user['status'] !== 1) {
    $failAndRedirect(
        'Hesabınız devre dışı bırakılmış. Sistem yöneticinizle iletişime geçin.',
        $email,
        $ip,
        'login_disabled_account',
        (int)$user['id']
    );
}

/* ------------------------------------------------------------------ */
/* 5) Başarılı giriş                                                   */
/* ------------------------------------------------------------------ */

// Parola eski bir algoritma/maliyetle hashlenmisse sessizce güncelle
if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
    try {
        $upd = db()->prepare('UPDATE users SET password = :p, password_changed_at = NOW() WHERE id = :id');
        $upd->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int)$user['id']]);
        app_log('info', 'Password rehashed', ['user' => (int)$user['id']]);
    } catch (Throwable $e) {
        app_log('error', 'Password rehash failed: ' . $e->getMessage());
    }
}

// Oturumu başlat (session_regenerate_id + csrf_rotate içerir)
auth_start([
    'id'            => (int)$user['id'],
    'name'          => (string)$user['name'],
    'email'         => (string)$user['email'],
    'role'          => (string)$user['role'],
    'department_id' => $user['department_id'] !== null ? (int)$user['department_id'] : null,
    'must_change_password' => (int)$user['must_change_password'] === 1,
]);

try {
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => (int)$user['id']]);

    // Başarılı denemeyi kaydet ve bu e-postanin kilidini kaldir
    db()->prepare(
        'INSERT INTO login_attempts (email, ip_address, success, user_agent, attempted_at)
         VALUES (:e, :ip, 1, :ua, NOW())'
    )->execute([':e' => $email, ':ip' => $ip, ':ua' => client_agent()]);

    db()->prepare('DELETE FROM login_attempts WHERE email = :e AND success = 0')
        ->execute([':e' => $email]);
} catch (Throwable $e) {
    // Giriş başarılı oldu; bu yan işlemlerin hatası kullanıcıyı engellememelidir.
    app_log('error', 'Post-login bookkeeping failed: ' . $e->getMessage());
}

audit('login', 'user', (int)$user['id'], null, ['email' => $email, 'role' => $user['role']]);

flash('success', 'Hoş geldiniz, ' . $user['name'] . '.');

// Giriş öncesi gitmek istediği sayfaya dön (yalnızca site içi yol)
$intended = $_SESSION['_intended'] ?? null;
unset($_SESSION['_intended']);

// Acik yonlendirme korumasi. Yalnizca "/" ile baslayan, tek egik cizgili
// ve yalnizca guvenli karakterler iceren site ici yollar kabul edilir.
// Ters egik cizgi ACIKCA reddedilir: tarayicilar bu karakteri "/" olarak
// normallestirir, boylece protokole goreli bir adres olusabilir.
$safePath = '#^/[A-Za-z0-9/._~-]*$#';

if (is_string($intended)
    && $intended !== ''
    && str_starts_with($intended, '/')
    && !str_starts_with($intended, '//')
    && !str_contains($intended, '/auth/')
    && preg_match($safePath, $intended) === 1) {
    redirect($intended);
}

redirect('/dashboard/');
