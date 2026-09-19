<?php

declare(strict_types=1);

/**
 * RiskOps - Bildirim e-postası gönderimi
 * /var/www/riskops/includes/mailer.php
 *
 * İki cron betiği aynı gönderim mantığını kullanıyor:
 *   tools/notify_due_actions.php    termini yaklaşan aksiyonlar
 *   tools/notify_stale_reviews.php  gözden geçirilmeyen riskler
 *
 * DÜZ METİN GÖNDERİLİR. HTML e-posta istemciye göre bozulan ve kaçış
 * hatası yaparsa enjeksiyon yüzeyi açan bir biçimdir; bir hatırlatma
 * için gereken her şey düz metinle anlatılabilir.
 *
 * SMTP KULLANMAK İSTERSENİZ: yalnızca mail_send() fonksiyonunun içi
 * değişir, çağıran betikler aynı kalır.
 */

/**
 * Gönderen ayarlarını okur ve doğrular.
 *
 * Dönüş İKİ AYRI ŞEKİLDİR: doğrulama geçtiyse email ve name HER ZAMAN
 * dolu, geçmediyse error HER ZAMAN dolu. Tek bir "hepsi olabilir"
 * şekli yazmak, `if (!$sender['ok'])` kontrolünden sonra bile
 * çağıranı belirsizlikte bırakıyordu.
 *
 * @return array{ok: true, email: string, name: string}
 *         |array{ok: false, error: string}
 */
function mail_sender(): array
{
    $email = trim((string)setting('notify_from_email', ''));
    $name  = trim((string)setting('notify_from_name', app_name()));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok'    => false,
            'error' => 'notify_from_email ayarı boş veya geçersiz. '
                     . 'Settings ekranından geçerli bir gönderen adresi girin.',
        ];
    }

    return ['ok' => true, 'email' => $email, 'name' => $name !== '' ? $name : 'RiskOps'];
}

/**
 * Tek bir bildirim gönderir.
 *
 * BAŞLIK ENJEKSİYONU: başlık alanlarına satır sonu veya NUL kaçarsa
 * saldırgan ek alıcı (Bcc) ekleyebilir. Konu, gönderen adı ve adres
 * temizlenir; alıcı adresi ayrıca doğrulanır.
 *
 * MTA yoksa mail() false döner — çağıran bunu SESSİZCE GEÇMEMELİ.
 */
function mail_send(
    string $toEmail,
    string $subject,
    string $body,
    string $fromEmail,
    string $fromName
): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $clean = static fn (string $v): string => (string)preg_replace('/[\r\n\x00]/', '', $v);

    $headers = [
        'From: ' . $clean($fromName) . ' <' . $clean($fromEmail) . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: RiskOps',
        /* Otomatik uretilen posta oldugunu bildirir: otomatik yanit
           dongulerini (out-of-office) engeller. */
        'Auto-Submitted: auto-generated',
    ];

    return @mail($clean($toEmail), $clean($subject), $body, implode("\r\n", $headers));
}

/**
 * Bildirim gövdelerinin ortak alt bilgisi.
 */
function mail_footer(): string
{
    return str_repeat('=', 60) . "\n"
         . 'Bu e-posta ' . app_name() . " tarafindan otomatik gonderilmistir.\n"
         . "Kayitlarinizi sistemden guncelleyebilirsiniz.\n";
}
