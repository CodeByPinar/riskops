<?php
declare(strict_types=1);

/**
 * RiskOps - English dictionary
 * /var/www/riskops/lang/en.php
 *
 * Keys are the Turkish source strings (see includes/i18n.php for why).
 * A string that is not listed here falls back to its Turkish original —
 * a missing translation never breaks the page, it just shows Turkish.
 *
 * Keep the keys byte-identical to the source; a stray space or a
 * different apostrophe silently disables the translation.
 */

return [

    /* ---------------------------------------------------------------
     * Navigation
     * --------------------------------------------------------------- */
    'Risk Yönetimi'     => 'Risk Management',
    'Analiz'            => 'Analysis',
    'Yönetim'           => 'Administration',

    'Panel'             => 'Dashboard',
    'Risk Kaydı'        => 'Risk Register',
    'Yeni Risk'         => 'New Risk',
    'Değerlendirmeler'  => 'Assessments',
    'Aksiyon Planları'  => 'Action Plans',
    'Raporlar'          => 'Reports',
    'Kullanıcılar'      => 'Users',
    'Departmanlar'      => 'Departments',
    'Kategoriler'       => 'Categories',
    'Silinen Riskler'   => 'Deleted Risks',
    'Denetim Kaydı'     => 'Audit Logs',
    'Ayarlar'           => 'Settings',
    'Hata Ayıklama'     => 'Debugging',

    'Profilim'          => 'My profile',
    'Parola değiştir'   => 'Change password',
    'Çıkış yap'         => 'Sign out',

    'Risk kodu, başlık veya varlık ara...' => 'Search risk code, title or asset...',
    'Menüyü aç/kapat'   => 'Toggle menu',

    /* ---------------------------------------------------------------
     * Roles
     * --------------------------------------------------------------- */
    'Sistem Yöneticisi' => 'Administrator',
    'Yönetici'          => 'Manager',
    'Analist'           => 'Analyst',
    'İzleyici'          => 'Viewer',

    /* ---------------------------------------------------------------
     * Login
     * --------------------------------------------------------------- */
    'Riskleri bugün yönetin'            => 'Manage risk today',
    'Daha Güvenli'                      => 'A Safer',
    'Daha Dayanıklı Bir Yarın'          => 'More Resilient Tomorrow',
    'RiskOps, kurumların BT ve siber risklerini bütüncül bir yaklaşımla yönetmelerine yardımcı olur.'
        => 'RiskOps helps organisations manage their IT and cyber risk with a single, coherent approach.',

    /* DIKKAT: bu uc metin TAM IFADE olarak anahtarlanir, parcali degil.
       "Daha Guvenli" tek basina anahtar olsaydi hem basliktaki
       "Daha Guvenli / Daha Dayanikli Bir Yarin" hem de buradaki
       "Daha Guvenli Operasyonlar" ayni anahtari paylasir ve biri
       digerinin cevirisini ezerdi. Kaynak-metin-anahtar yaklasiminin
       bilinen siniri: ayni kelime farkli baglamda farkli cevrilir. */
    'Daha Güvenli Operasyonlar' => 'Safer Operations',
    'Uyumluluk ve Raporlama'    => 'Compliance and Reporting',
    'Daha Güçlü Kurumlar'       => 'Stronger Organisations',

    'Daha güvenli bir gelecek için' => 'Towards a safer future',
    'Siber risklere karşı'          => 'One step ahead of',
    'bir adım önde'                 => 'cyber risk',

    'Tekrar hoş geldiniz'                  => 'Welcome back',
    'Hesabınıza giriş yaparak devam edin.' => 'Sign in to your account to continue.',
    'E-posta'                              => 'Email',
    'Parola'                               => 'Password',
    'Parolanız'                            => 'Your password',
    'Giriş yap'                            => 'Sign in',
    'Parolanızı mı unuttunuz? Sistem yöneticinize başvurun.'
        => 'Forgot your password? Please contact your system administrator.',
    'Parolayı göster'  => 'Show password',
    'Parolayı gizle'   => 'Hide password',

    'Oturumunuz korunuyor'               => 'Your session is protected',
    'Her istek sunucuda yeniden doğrulanır.' => 'Every request is re-validated on the server.',
    'Rol bazlı yetki'                    => 'Role-based access',
    'Bu sistemdeki tüm işlemler kayıt altına alınmaktadır.'
        => 'All activity in this system is recorded.',

    'Deneme kurulumu'  => 'Demo installation',
    'Kurumsal kurulum' => 'Corporate installation',
    'Tüm hakları saklıdır.' => 'All rights reserved.',

    /* ---------------------------------------------------------------
     * Common actions
     * --------------------------------------------------------------- */
    'Kaydet'        => 'Save',
    'İptal'         => 'Cancel',
    'Sil'           => 'Delete',
    'Düzenle'       => 'Edit',
    'Görüntüle'     => 'View',
    'Detay'         => 'Details',
    'Geri al'       => 'Restore',
    'Gönder'        => 'Send',
    'Yükle'         => 'Upload',
    'Ata'           => 'Assign',
    'Değiştir'      => 'Change',
    'Kapat'         => 'Close',
    'Listeye dön'   => 'Back to list',
    'Tümü'          => 'All',
    'Ara'           => 'Search',
    'Filtrele'      => 'Filter',
    'Temizle'       => 'Clear',
    'Excel'         => 'Excel',
    'Ham CSV'       => 'Raw CSV',
    'Yazdır'        => 'Print',

    /* ---------------------------------------------------------------
     * Common labels
     * --------------------------------------------------------------- */
    'Kod'           => 'Code',
    'Başlık'        => 'Title',
    'Açıklama'      => 'Description',
    'Departman'     => 'Department',
    'Kategori'      => 'Category',
    'Sahip'         => 'Owner',
    'Sorumlu'       => 'Assignee',
    'Durum'         => 'Status',
    'Seviye'        => 'Severity',
    'Öncelik'       => 'Priority',
    'Termin'        => 'Due date',
    'Skor'          => 'Score',
    'Tarih'         => 'Date',
    'Kullanıcı'     => 'User',
    'Rol'           => 'Role',
    'İşlem'         => 'Action',
    'Varlık'        => 'Entity',
    'Oluşturan'     => 'Created by',
    'Oluşturulma'   => 'Created at',
    'Son güncelleme' => 'Last updated',
    'Son giriş'     => 'Last sign-in',
    'Tamamlanma'    => 'Completed at',
    'Silinme'       => 'Deleted at',
    'Silen'         => 'Deleted by',
    'Aktif'         => 'Active',
    'Pasif'         => 'Inactive',

    /* ---------------------------------------------------------------
     * Severity / status / priority display labels
     *
     * The stored ENUM values stay English ('Open', 'Critical'); only
     * the visible label is translated. See severity_label() etc. in
     * includes/ui.php.
     * --------------------------------------------------------------- */
    'Düşük'         => 'Low',
    'Orta'          => 'Medium',
    'Yüksek'        => 'High',
    'Kritik'        => 'Critical',

    'Açık'          => 'Open',
    'İncelemede'    => 'Under Review',
    'Devam ediyor'  => 'In Progress',
    'Azaltıldı'     => 'Mitigated',
    'Kabul edildi'  => 'Accepted',
    'Devredildi'    => 'Transferred',
    'Kapatıldı'     => 'Closed',
    'Tamamlandı'    => 'Completed',
    'İptal edildi'  => 'Cancelled',

    /* ---------------------------------------------------------------
     * Risk detail tabs
     * --------------------------------------------------------------- */
    'Genel'                  => 'Overview',
    'Değerlendirme Geçmişi'  => 'Assessment History',
    'Audit'                  => 'Audit',
    'Yorumlar'               => 'Comments',
    'Ekler'                  => 'Attachments',

    'Yorum ekle'    => 'Add a comment',
    'Bu kayıtla ilgili notunuz...' => 'Your note about this record...',
    'Henüz yorum yok' => 'No comments yet',
    'İlk yorumu siz ekleyin.' => 'Be the first to comment.',
    'Ek yok'        => 'No attachments',
    'Kanıt belgelerini buraya yükleyebilirsiniz.' => 'You can upload supporting documents here.',

    /* ---------------------------------------------------------------
     * Profile
     * --------------------------------------------------------------- */
    'Bilgilerim'            => 'My details',
    'Hesap'                 => 'Account',
    'Ad Soyad'              => 'Full name',
    'Unvan'                 => 'Job title',
    'Telefon'               => 'Phone',
    'Parola değişimi'       => 'Password changed',
    'Kayıt tarihi'          => 'Member since',
    'Parolamı değiştir'     => 'Change my password',
    'Hesap bilgilerinizi görüntüleyin ve güncelleyin' => 'View and update your account details',

    /* ---------------------------------------------------------------
     * Deleted risks
     * --------------------------------------------------------------- */
    'Silinmiş risk kaydı yok' => 'No deleted risks',
    'Bir risk silindiğinde burada listelenir ve geri alınabilir.'
        => 'When a risk is deleted it is listed here and can be restored.',

    /* ---------------------------------------------------------------
     * Bulk operations
     * --------------------------------------------------------------- */
    'risk seçildi'  => 'risks selected',
    'Sahip seç...'  => 'Select owner...',
    'Durum seç...'  => 'Select status...',
    'Tümünü seç'    => 'Select all',

    /* ---------------------------------------------------------------
     * Empty states / messages
     * --------------------------------------------------------------- */
    'Kayıt bulunamadı' => 'No records found',
    'Yaklaşan termin yok' => 'No upcoming deadlines',

    /* ---------------------------------------------------------------
     * Hata ayıklama kipi yönetim ekranı  (/admin/debug/)
     * ------------------------------------------------------------- */
    'Geliştirme araçlarını geçici olarak açın' => 'Turn on development tools temporarily',
    'Hata ayıklama kipi AÇIK'     => 'Debug mode is ON',
    'Hata ayıklama kipi kapalı'   => 'Debug mode is off',
    'Bu ekrandan açıldı'          => 'Enabled from this screen',
    'sonra kapanacak'             => 'until it turns off',
    'Sunucu yapılandırmasından açık (RISKOPS_DEBUG) — bu ekrandan kapatılamaz'
        => 'Enabled by server configuration (RISKOPS_DEBUG) — cannot be turned off here',
    'Hem sunucu yapılandırmasından hem bu ekrandan açık'
        => 'Enabled both by server configuration and from this screen',
    'Araç çubuğu görünmüyor, sorgular kaydedilmiyor, istisnalar sade 500 sayfası olarak görünüyor'
        => 'No toolbar, no query recording; exceptions render as a plain 500 page',
    'storage/ dizini yazılabilir değil; kip bu ekrandan açılamaz.'
        => 'The storage/ directory is not writable; the mode cannot be enabled here.',
    'Şimdi kapat'                 => 'Turn off now',
    'Süreyi uzat'                 => 'Extend to',
    'Aç'                          => 'Turn on',

    /* Süre etiketleri: sabitte Türkçe duruyor, görünen ad çevriliyor. */
    '1 saat'                      => '1 hour',
    '4 saat'                      => '4 hours',
    '24 saat'                     => '24 hours',
    'Süre dolduğunda kip kendiliğinden kapanır; bayrak dosyası silinmemiş olsa bile yok sayılır. Açık unutma riski bu yüzden yoktur.'
        => 'The mode turns itself off when the time is up; an expired flag file is ignored even if it was never deleted. That is why it cannot be left on by accident.',
    'Açan'                        => 'Enabled by',
    'Açılma'                      => 'Enabled at',
    'Bitiş'                       => 'Expires',
    'Açıkken ne değişir'          => 'What changes while it is on',
    'Araç çubuğu'                 => 'Toolbar',
    'Sayfanın altında: çalışan her SQL ve süresi, bağlanan parametreler, sorguyu açan dosya ve satır, zaman çizelgesi, istek ve oturum içeriği, log kuyruğu.'
        => 'At the bottom of the page: every SQL that ran and how long it took, bound parameters, the file and line that issued it, a timeline, request and session contents, and the log tail.',
    'Ayrıntılı hata sayfası'      => 'Detailed error page',
    'İstisnalar yığın izi, hatalı satırın kaynak parçası ve o isteğe kadar çalışmış sorgularla görünür. Kapalıyken aynı hata sade bir 500 sayfasıdır.'
        => 'Exceptions render with a stack trace, a source excerpt around the failing line, and every query that ran during the request. With the mode off, the same error is a plain 500 page.',
    'Ölçüm kaydı'                 => 'Measurement log',
    'istek başına süre, bellek ve sorgu sayısı.'
        => 'time, memory and query count per request.',
    'Neler gösterilmez'           => 'What is never shown',
    'Parolalar, CSRF jetonu, oturum kimliği ve veritabanı parolası maskelenir. Üretim ortamında araç çubuğu yalnızca admin rolüne gösterilir.'
        => 'Passwords, the CSRF token, the session id and the database password are masked. In production the toolbar is shown to administrators only.',
    'Bu kurulum üretim ortamında çalışıyor. Kipi yalnızca incelemeniz gereken süre boyunca açık tutun.'
        => 'This installation runs in production. Keep the mode on only for as long as you need to investigate.',
    'Ortam'                       => 'Environment',
    'Ortam (APP_ENV)'             => 'Environment (APP_ENV)',
    'Sunucu değişkeni'            => 'Server variable',
    'Panel bayrağı'               => 'Panel flag',
    'Bayrak dosyası'              => 'Flag file',
    'Yavaş sorgu eşiği'           => 'Slow query threshold',
    'Ölçüm kaydı kapsamı'         => 'Measurement log scope',
    'ayarlı değil'                => 'not set',
    'açık'                        => 'on',
    'kapalı'                      => 'off',
    'yalnızca yavaş/hatalı istekler' => 'slow / failing requests only',
    'her istek'                   => 'every request',
    'Kipi kapat'                  => 'Turn off',
];
