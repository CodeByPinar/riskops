<?php
declare(strict_types=1);

/**
 * RiskOps - Kimlik doğrulama ve yetkilendirme
 * /var/www/riskops/includes/auth.php
 *
 * KURAL: Yetki kontrolü HER ZAMAN sunucu tarafinda yapılır.
 *        Sidebar'da linki gizlemek bir guvenlik önlemi DEĞİLDİR.
 */

const ROLE_ADMIN   = 'admin';
const ROLE_MANAGER = 'manager';
const ROLE_ANALYST = 'analyst';
const ROLE_VIEWER  = 'viewer';

function all_roles(): array
{
    return [ROLE_ADMIN, ROLE_MANAGER, ROLE_ANALYST, ROLE_VIEWER];
}

/**
 * Rol -> yetki matrisi.
 * '*' = tüm yetkiler (yalnızca admin).
 * Listede OLMAYAN her yetki admin'e ozeldir:
 *   risk.delete, user.manage, department.manage,
 *   category.manage, settings.manage, audit.view
 */
function role_permissions(): array
{
    return [
        ROLE_ADMIN => ['*'],

        ROLE_MANAGER => [
            'risk.view', 'risk.create', 'risk.update', 'risk.assess',
            'action.view', 'action.create', 'action.update', 'action.complete',
            'report.view', 'user.view',
        ],

        ROLE_ANALYST => [
            'risk.view', 'risk.create', 'risk.update', 'risk.assess',
            'action.view', 'action.create', 'action.update', 'action.complete',
            'report.view',
        ],

        ROLE_VIEWER => [
            'risk.view', 'action.view', 'report.view',
        ],
    ];
}

function role_label(?string $role): string
{
    return match ($role) {
        ROLE_ADMIN   => 'Administrator',
        ROLE_MANAGER => 'Manager',
        ROLE_ANALYST => 'Analyst',
        ROLE_VIEWER  => 'Viewer',
        default      => '-',
    };
}

/* ---------------------------------------------------------------- */

function auth_check(): bool
{
    return !empty($_SESSION['user_id']);
}

function auth_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function auth_role(): ?string
{
    $r = $_SESSION['user_role'] ?? null;
    return is_string($r) ? $r : null;
}

function auth_name(): string
{
    return (string)($_SESSION['user_name'] ?? '');
}

function auth_user(): ?array
{
    if (!auth_check()) {
        return null;
    }
    return [
        'id'            => auth_id(),
        'name'          => auth_name(),
        'email'         => (string)($_SESSION['user_email'] ?? ''),
        'role'          => auth_role(),
        'department_id' => isset($_SESSION['user_department_id'])
                            ? (int)$_SESSION['user_department_id'] : null,
    ];
}

/** Başarılı login sonrasi oturumu baslatir. */
function auth_start(array $user): void
{
    // Session fixation koruması
    session_regenerate_id(true);

    $_SESSION['user_id']            = (int)$user['id'];
    $_SESSION['user_name']          = (string)$user['name'];
    $_SESSION['user_email']         = (string)$user['email'];
    $_SESSION['user_role']          = (string)$user['role'];
    $_SESSION['user_department_id'] = isset($user['department_id']) ? (int)$user['department_id'] : null;
    /* Arayuz dili tercihi oturumda tasinir: her istekte kullanici
       tablosuna gitmemek icin (bkz. includes/i18n.php locale()). */
    $_SESSION['user_locale']        = $user['locale'] ?? null;
    $_SESSION['must_change_password'] = !empty($user['must_change_password']);
    $_SESSION['login_at']           = time();
    $_SESSION['last_activity']      = time();

    csrf_rotate();
}

function auth_destroy(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/* ---------------------------------------------------------------- */

function can(string $ability): bool
{
    $role = auth_role();
    if ($role === null) {
        return false;
    }
    $perms = role_permissions()[$role] ?? [];
    return in_array('*', $perms, true) || in_array($ability, $perms, true);
}

function require_login(): void
{
    if (!auth_check()) {
        if (session_status() === PHP_SESSION_ACTIVE && !is_post()) {
            $_SESSION['_intended'] = current_path();
        }
        redirect('/auth/login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array((string)auth_role(), $roles, true)) {
        app_log('warning', 'Authorization denied (role)', [
            'user'     => auth_id(),
            'role'     => auth_role(),
            'required' => $roles,
            'path'     => current_path(),
        ]);
        app_abort(403);
    }
}

/**
 * Zamanlama saldirisina karsi esitleyici hash.
 *
 * SORUN: Kullanici bulunamadiginda da password_verify() calistiriyoruz ki
 * yanit suresi "bu e-posta kayitli mi" bilgisini ele vermesin. Ancak sabit
 * bir hash gomuldugunde maliyeti gercek parolalarinkiyle ayni olmak
 * ZORUNDA. Aksi halde esitleyici tam tersini yapar: olcumde var olan hesap
 * 46 ms, olmayan 158 ms suruyordu (gomulu hash cost=12, gercekler cost=10).
 *
 * COZUM: Esitleyici hash PASSWORD_DEFAULT ile bir kez uretilip saklanir ve
 * PHP'nin varsayilan algoritmasi/maliyeti degisirse kendiliginden yenilenir.
 * Boylece her iki yolda da tam olarak bir bcrypt dogrulamasi, ayni maliyette.
 */
function auth_timing_equalizer_hash(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $file = STORAGE_PATH . '/.auth_timing_hash';
    $hash = is_file($file) ? trim((string)@file_get_contents($file)) : '';

    if ($hash === '' || password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        if (@file_put_contents($file, $hash, LOCK_EX) !== false) {
            @chmod($file, 0600);
        }
    }

    return $cached = $hash;
}

/**
 * Oturumu veritabanindaki gercekle karsilastirir.
 *
 * NEDEN GEREKLI: Rol, hesap durumu ve parola oturum acilirken $_SESSION'a
 * kopyalanir. Yonetici bir hesabi pasiflestirdiginde veya rolunu
 * dusurdugunde bu kopya eskir; kullanici oturumu acik kaldigi surece eski
 * yetkileriyle calismaya devam eder. Olcumle dogrulandi: hesap status=0
 * yapildiktan sonra ayni oturum /dashboard/ icin 200 donuyordu.
 *
 * Bu kontrol her istekte tek bir birincil anahtar sorgusu ekler ve uc seyi
 * birden kapatir: pasiflestirme, rol degisikligi ve parola sifirlama
 * sonrasi oturum gecersizlestirme. Ayri bir sessions tablosu gerekmez.
 */
function auth_revalidate(): void
{
    if (PHP_SAPI === 'cli' || !auth_check()) {
        return;
    }

    try {
        $stmt = db()->prepare(
            'SELECT role, status, must_change_password, password_changed_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => auth_id()]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        // Veritabani gecici olarak erisilemezse kullaniciyi disari atma;
        // bir altyapi arizasi toplu oturum kapatmaya donusmemeli.
        app_log('error', 'Session revalidation failed: ' . $e->getMessage());
        return;
    }

    $userId = auth_id();

    /** Oturumu sonlandirir ve giris ekranina mesajla doner. */
    $revoke = static function (string $reason, string $type, string $message) use ($userId): never {
        audit('session_revoked', 'user', $userId, null, ['reason' => $reason]);
        auth_destroy();
        session_start();
        session_regenerate_id(true);
        flash($type, $message);
        redirect('/auth/login.php');
    };

    if ($row === false) {
        $revoke('account_deleted', 'error',
            'Hesabiniz bulunamadi. Lutfen sistem yoneticinizle iletisime gecin.');
    }

    if ((int)$row['status'] !== 1) {
        $revoke('account_disabled', 'error',
            'Hesabiniz devre disi birakildi. Sistem yoneticinizle iletisime gecin.');
    }

    // Parola baska bir yerden degistirildi/sifirlandi: bu oturum artik gecersiz
    $changedAt = $row['password_changed_at'] !== null
        ? (int)strtotime((string)$row['password_changed_at'])
        : 0;
    $loginAt = (int)($_SESSION['login_at'] ?? 0);

    if ($changedAt > 0 && $loginAt > 0 && $changedAt > $loginAt) {
        $revoke('password_changed', 'warning',
            'Parolaniz degistirildi. Lutfen yeni parolanizla giris yapin.');
    }

    // Rol degismis: oturumdaki kopyayi tazele (oturumu kapatmaya gerek yok)
    if ((string)$row['role'] !== (string)auth_role()) {
        app_log('info', 'Session role refreshed', [
            'user' => $userId,
            'from' => auth_role(),
            'to'   => $row['role'],
        ]);
        audit('session_role_refreshed', 'user', $userId,
            ['role' => auth_role()], ['role' => $row['role']]);
        $_SESSION['user_role'] = (string)$row['role'];
    }

    $_SESSION['must_change_password'] = ((int)$row['must_change_password'] === 1);
}

/**
 * Geçici parolayla giren kullanıcıyı parola değiştirme ekranına kilitler.
 *
 * Yalnızca çıkış ve parola değiştirme sayfaları açık kalır. POST istekleri
 * yönlendirilmez, 403 ile kesilir: yarım kalmış bir form gönderimi sessizce
 * kaybolmasın.
 */
function require_password_change_if_needed(): void
{
    if (PHP_SAPI === 'cli' || !auth_check() || empty($_SESSION['must_change_password'])) {
        return;
    }

    $allowed = ['/auth/change_password.php', '/auth/logout.php'];
    if (in_array(current_path(), $allowed, true)) {
        return;
    }

    if (is_post()) {
        app_abort(403, 'Password change required before any other action');
    }

    redirect('/auth/change_password.php');
}

function require_can(string $ability): void
{
    require_login();
    if (!can($ability)) {
        app_log('warning', 'Authorization denied (ability)', [
            'user'    => auth_id(),
            'role'    => auth_role(),
            'ability' => $ability,
            'path'    => current_path(),
        ]);
        app_abort(403);
    }
}
