<?php
declare(strict_types=1);

/**
 * RiskOps - Ortak hata sayfasi sablonu
 * /var/www/riskops/errors/_render.php
 *
 * app_abort() tarafindan cagrilir. Bu noktada layout'un yuklenebilecegine
 * GUVENEMEYIZ (hata layout'un ortasinda da olusmus olabilir), bu yuzden
 * sayfa tamamen kendi kendine yeter: harici CSS/JS yoktur.
 *
 * Beklenen degiskenler: $rkCode, $rkTitle, $rkText, $rkIcon
 */

$rkCode  = $rkCode  ?? 500;
$rkTitle = $rkTitle ?? 'Bir hata oluştu';
$rkText  = $rkText  ?? 'An unexpected error occurred.';
$rkHome  = defined('BASE_PATH') ? BASE_PATH . '/' : '/';

/* CSP.
   bootstrap.php "style-src 'self' 'nonce-...'" gonderiyor. Bu sayfa kendi
   stilini satir ici bir <style> blogunda tasidigi icin ayni nonce olmadan
   tarayici blokluyor ve sayfa bicimsiz goruluyordu.
   csp_nonce() yuklenememis olabilir (hata bootstrap'in ortasinda da
   olusabilir): o durumda nitelik hic yazilmaz, sayfa yine okunur kalir. */
$rkNonce = function_exists('csp_nonce')
    ? ' nonce="' . htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8') . '"'
    : '';

/* Doğrudan açıldığında da (ör. /errors/404.php) doğru durum kodu dönsün.
   Önceden 200 dönüyordu: bir tarama aracı bunu "sayfa var" diye okurdu. */
if (!headers_sent()) {
    http_response_code((int)$rkCode);
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= (int)$rkCode ?> - RiskOps</title>
<style<?= $rkNonce ?>>
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
         background:#f4f8fd;color:#041f3c;
         font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
    .box{max-width:440px;width:100%;background:#fff;border:1px solid #e6eefa;border-radius:20px;
         box-shadow:0 2px 4px rgba(6,32,63,.03),0 22px 48px rgba(6,32,63,.10);
         padding:36px 34px;text-align:center}
    .code{font-size:52px;font-weight:700;line-height:1;color:#cfdcee;letter-spacing:-2px}
    h1{font-size:18px;font-weight:660;margin:14px 0 6px;letter-spacing:-.3px}
    p{color:#5c7897;margin:0 0 22px;font-size:13.5px}
    a{display:inline-block;background:linear-gradient(180deg,#2b83e8 0%,#016ccc 100%);
      color:#fff;text-decoration:none;padding:11px 20px;border-radius:11px;
      font-size:13.5px;font-weight:600;box-shadow:0 8px 18px rgba(1,108,204,.24)}
    a:hover{background:linear-gradient(180deg,#1f76de 0%,#0056b4 100%)}
    .ref{margin-top:18px;font-size:11px;color:#90a7c2}
</style>
</head>
<body>
    <div class="box">
        <div class="code"><?= (int)$rkCode ?></div>
        <h1><?= htmlspecialchars($rkTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($rkText, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars($rkHome, ENT_QUOTES, 'UTF-8') ?>">Ana sayfaya dön</a>
        <div class="ref"><?= htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
</body>
</html>
