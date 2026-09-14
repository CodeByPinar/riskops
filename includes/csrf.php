<?php

declare(strict_types=1);

/**
 * RiskOps - CSRF koruması
 * /var/www/riskops/includes/csrf.php
 *
 * Kullanım:
 *   Formda   : <?= csrf_field() ?>
 *   Handler'da: csrf_require();   // ilk satir olmali
 */

const CSRF_FIELD = '_token';

/** Oturum başına tek token üretir/döndürür. */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Forma gomulecek hidden input. */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_FIELD . '" value="' . e(csrf_token()) . '">';
}

/** Zamanlama saldirisina karsi hash_equals kullanir. */
function csrf_verify(?string $token): bool
{
    $stored = $_SESSION['_csrf'] ?? '';
    return is_string($stored) && $stored !== ''
        && is_string($token) && $token !== ''
        && hash_equals($stored, $token);
}

/** Login/logout sonrasi token yenilenir (session fixation önlemi). */
function csrf_rotate(): void
{
    unset($_SESSION['_csrf']);
    csrf_token();
}

/**
 * POST handler'larinin ILK satırı.
 * POST degilse 405, token gecersizse 403 ile sonlandirir.
 */
function csrf_require(): void
{
    if (!is_post()) {
        app_abort(405, 'Non-POST request to a POST-only endpoint: ' . current_path());
    }
    if (!csrf_verify(isset($_POST[CSRF_FIELD]) && is_string($_POST[CSRF_FIELD]) ? $_POST[CSRF_FIELD] : null)) {
        app_log('warning', 'CSRF validation failed', [
            'ip'   => client_ip(),
            'path' => current_path(),
            'user' => $_SESSION['user_id'] ?? null,
        ]);
        app_abort(403, 'CSRF token mismatch');
    }
}

/* --- Proje dokumaninda gecen isimler için takma adlar --------------- */
function generate_csrf_token(): string
{
return csrf_token();
}
function verify_csrf_token(?string $token): bool
{
return csrf_verify($token);
}
