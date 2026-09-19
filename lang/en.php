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
    'Eklentiler'        => 'Plugins',

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

    /* ---------------------------------------------------------------
     * Eklenti yönetim ekranı  (/admin/plugins/)
     * ------------------------------------------------------------- */
    'Diskteki eklentileri açın veya kapatın' => 'Enable or disable plugins found on disk',
    'Kurulu eklentiler'           => 'Installed plugins',
    'Eklenti'                     => 'Plugin',
    'Sürüm'                       => 'Version',
    'bulundu'                     => 'found',
    'yüklü'                       => 'loaded',
    'Yüklenemedi'                 => 'Failed to load',
    /* 'Açık' / 'Aç' anahtarları hata ayıklama ekranında BAŞKA anlamda
       kullanılıyor (kip açık / kipi aç). Aynı kelimeyi iki bağlamda
       anahtar yapmak, ADR-0002'de yazılı bilinen sınır: PHP dizisinde
       ikinci tanım birinciyi sessizce ezer. Tam ifade kullanıyoruz. */
    'Eklenti açık'                => 'Enabled',
    'Eklenti kapalı'              => 'Disabled',
    'Eklentiyi aç'                => 'Enable',
    'Eklentiyi kapat'             => 'Disable',
    'Eklenti kapatılacak. Emin misiniz?' => 'This plugin will be disabled. Are you sure?',
    'Henüz eklenti yok'           => 'No plugins yet',
    'Eklentiler plugins/ dizinine konur. Her eklenti kendi klasöründe bir plugin.php dosyası taşır.'
        => 'Plugins live in the plugins/ directory. Each one carries a plugin.php manifest in its own folder.',
    'Eklenti kurmak'              => 'Installing a plugin',
    'Eklenti kurmak, yazarına çekirdek kadar güvenmektir.'
        => 'Installing a plugin means trusting its author as much as the core.',
    'Bu istekte eklenti hatası oluştu' => 'A plugin failed during this request',
    'Bu istekte kayıtlı dinleyiciler'  => 'Listeners registered in this request',
    'Mevcut kancalar'             => 'Available hooks',
    'dinleyici'                   => 'listener(s)',
    'storage/ dizini yazılabilir değil; eklentiler bu ekrandan açılıp kapatılamaz.'
        => 'The storage/ directory is not writable; plugins cannot be toggled here.',

    /* ---------------------------------------------------------------
     * Dashboard
     * --------------------------------------------------------------- */
    'Acil Aksiyon Gerektiren Riskler' => 'Risks Requiring Immediate Action',
    'Seviye Dağılımı'             => 'Severity Distribution',
    'Risk Trendi'                 => 'Risk Trend',
    'Departman Yoğunluğu'         => 'Department Load',
    'Tehdit Alanları'             => 'Threat Areas',
    'Yaklaşan Terminler'          => 'Upcoming Due Dates',
    'Geciken Aksiyonlar'          => 'Overdue Actions',
    'Geciken Aksiyon'             => 'Overdue Action',
    'Açık Aksiyon'                => 'Open Action',
    'Bana Atananlar'              => 'Assigned to Me',
    'Mevcut Durum'                => 'Current Status',
    'Kritik + Yüksek'             => 'Critical + High',
    'Kritik + Yüksek sayısı ipucunda' => 'Critical + High counts shown in the tooltip',
    'En yüksek etkin skorlu 10 açık risk' => 'Top 10 open risks by effective score',
    'Aylara göre açılan ve kapanan risk sayısı' => 'Risks opened and closed by month',
    'Departmana göre risk sayısı' => 'Risk count by department',
    'Kategoriye göre risk sayısı' => 'Risk count by category',
    'Son 6 Ay'                    => 'Last 6 Months',
    'Son 12 ay'                   => 'Last 12 months',
    'Açılan'                      => 'Opened',
    'Kapanan'                     => 'Closed',
    'Net'                         => 'Net',
    'Toplam'                      => 'Total',
    'Toplam aksiyon'              => 'Total actions',
    'Adet'                        => 'Count',
    'Oran'                        => 'Share',
    'Yük'                         => 'Load',

    /* ---------------------------------------------------------------
     * Risk form and detail
     * --------------------------------------------------------------- */
    'Risk'                        => 'Risk',
    'Risk Tanımı'                 => 'Risk Definition',
    'Riskin ne olduğu, hangi koşullarda gerçekleşebileceği'
        => 'What the risk is and under what conditions it could occur',
    'Etkilenen Varlık / Sistem'   => 'Affected Asset / System',
    'Tehdit'                      => 'Threat',
    'Tehdit kaynağı / aktörü'     => 'Threat source / actor',
    'Zafiyet'                     => 'Vulnerability',
    'İstismar edilebilecek zayıflık' => 'The weakness that could be exploited',
    'Olasılık'                    => 'Likelihood',
    'Etki'                        => 'Impact',
    'Inherent'                    => 'Inherent',
    'Inherent Skor'               => 'Inherent Score',
    'Residual'                    => 'Residual',
    'Residual Olasılık'           => 'Residual Likelihood',
    'Residual Etki'               => 'Residual Impact',
    'Residual Skor'               => 'Residual Score',
    'Önizleme (5×5 matris)'       => 'Preview (5×5 matrix)',
    'Sınıflandırma'               => 'Classification',
    'Strateji'                    => 'Strategy',
    'Treatment Strategy'          => 'Treatment Strategy',
    'Risk Sahibi'                 => 'Risk Owner',
    'Risk Durumu'                 => 'Risk Status',
    'Risk kodu, başlık veya varlık' => 'Risk code, title or asset',
    'Termin (başlangıç)'          => 'Due from',
    'Termin (bitiş)'              => 'Due to',
    'Riske git'                   => 'Go to risk',
    'Riski aç'                    => 'Open risk',
    'Bağlı Risk'                  => 'Linked Risk',
    'Bağlı risk'                  => 'Linked risk',
    'Açık (Open / Under Review / In Progress)'
        => 'Open (Open / Under Review / In Progress)',

    /* ---------------------------------------------------------------
     * Actions
     * --------------------------------------------------------------- */
    'Aksiyon'                     => 'Action',
    'Aksiyon Ekle'                => 'Add Action',
    'Aksiyon Başlığı'             => 'Action Title',
    'Yapılacak işin kapsamı, bağımlılıklar, kabul kriteri'
        => 'Scope of work, dependencies, acceptance criteria',
    'Tamamla'                     => 'Complete',
    'İptal edilen'                => 'Cancelled',

    /* ---------------------------------------------------------------
     * Assessments
     * --------------------------------------------------------------- */
    'Değerlendirme'               => 'Assessment',
    'Risk Değerlendirmesi'        => 'Risk Assessment',
    'Değerlendirme Tarihi'        => 'Assessment Date',
    'Değerlendiren'               => 'Assessed by',
    'Değerlendirmeyi Kaydet'      => 'Save Assessment',
    'Gerekçe / Not'               => 'Rationale / Note',
    'Skorun neden değiştiği, hangi kontrolün devreye girdiği'
        => 'Why the score changed and which control took effect',
    'Tür'                         => 'Type',
    'Tip'                         => 'Type',

    /* ---------------------------------------------------------------
     * Reports
     * --------------------------------------------------------------- */
    'Yönetici Risk Özeti'         => 'Executive Risk Summary',
    'Yönetim Dikkatine Sunulan Riskler' => 'Risks for Management Attention',
    'Seviyeye Göre'               => 'By Severity',
    'Duruma Göre'                 => 'By Status',
    'Departmana Göre'             => 'By Department',
    'Kategoriye Göre'             => 'By Category',
    'Ham'                         => 'Raw',
    'Ad Soyad / İmza / Tarih'     => 'Name / Signature / Date',
    'Onay'                        => 'Approval',

    /* ---------------------------------------------------------------
     * Users
     * --------------------------------------------------------------- */
    'Ad'                          => 'Name',
    'Ad, e-posta veya ünvan'      => 'Name, e-mail or title',
    'Ünvan'                       => 'Title',
    'Hesap Durumu'                => 'Account Status',
    'İlk girişte parola değiştirmeli' => 'Must change password at first login',
    'Sıfırla'                     => 'Reset',
    'Yetki'                       => 'Permission',
    'Kimlik'                      => 'Identity',

    /* ---------------------------------------------------------------
     * Categories and departments
     * --------------------------------------------------------------- */
    'Renk'                        => 'Color',
    'Renk kodu'                   => 'Color code',
    'Kısa, benzersiz tanımlayıcı. Büyük harfe çevrilir.'
        => 'Short, unique identifier. Converted to upper case.',
    'Kullanımda olduğu için silinemez — pasifleştirebilirsiniz'
        => 'In use, so it cannot be deleted — deactivate it instead',
    'Örn: Uygulama Güvenliği'     => 'e.g. Application Security',
    'Örn: APPSEC'                 => 'e.g. APPSEC',
    'Örn: Bilgi Teknolojileri'    => 'e.g. Information Technology',
    'Örn: IT'                     => 'e.g. IT',
    'Örn: RDP erişimini VPN arkasına alma'
        => 'e.g. Move RDP access behind the VPN',

    /* ---------------------------------------------------------------
     * Settings and audit log
     * --------------------------------------------------------------- */
    'Ayarları Kaydet'             => 'Save Settings',
    'Kod tarafından yönetilir.'   => 'Managed in code.',
    'Salt okunur — audit kayıtları arayüzden değiştirilemez.'
        => 'Read-only — audit records cannot be changed from the interface.',
    'Değişiklik'                  => 'Change',
    'IP'                          => 'IP',
    'Arama'                       => 'Search',
    'Uygula'                      => 'Apply',
    'Not'                         => 'Note',
    'Belirlenmedi'                => 'Not set',
    'Belirtilmedi'                => 'Not specified',
    'Başlangıç'                   => 'Start',
    'Vazgeç'                      => 'Cancel',
    'Tümünü yönet'                => 'Manage all',
    'Seçiniz'                     => 'Select...',
    'Ay'                          => 'Month',
    'Sıra'                        => 'Order',
    'Zaman'                       => 'Time',

    /* ---------------------------------------------------------------
     * Plugins screen
     *
     * The long paragraphs are security statements, not decoration:
     * they say what a plugin can do and why there is no upload form.
     * Translating them loosely would weaken the warning, so they are
     * kept as full sentences rather than reused fragments.
     * --------------------------------------------------------------- */
    'Eklenti, uygulamanın kendi yetkileriyle çalışan PHP kodudur: veritabanına erişebilir, dosya yazabilir, ağa çıkabilir. PHP\'de gerçek bir kum havuzu yoktur; bu yüzden kısıtlandığı iddia edilmiyor.'
        => 'A plugin is PHP code running with the application\'s own privileges: it can reach the database, write files and open network connections. PHP has no real sandbox, so no claim is made that it is confined.',
    'Bu ekrandan dosya YÜKLENEMEZ ve bu bilinçli bir karardır: yükleme ucu olsaydı, ele geçirilmiş tek bir yönetici hesabı doğrudan uzaktan kod çalıştırmaya dönüşürdü. Kurulum sunucuda yapılır:'
        => 'Files CANNOT be uploaded from this screen, and that is deliberate: an upload endpoint would turn a single compromised admin account straight into remote code execution. Installation happens on the server:',
    'Dosyalar yerine konduktan sonra eklenti bu listede görünür ve buradan açılabilir. Yazma yetkisi gerektirmez; yalnızca açık eklenti listesi storage/plugins.json içinde tutulur.'
        => 'Once the files are in place the plugin appears in this list and can be enabled here. No write access to the code is needed; only the list of enabled plugins is stored in storage/plugins.json.',
    'Örnek eklenti: plugins/ornek-kayit-defteri/ — hem eylem hem filtre kancası kullanıyor ve yorumlarla açıklıyor.'
        => 'Example plugin: plugins/ornek-kayit-defteri/ — it uses both an action and a filter hook, and explains each in comments.',
    'Sol menü (filtre) — menü dizisi' => 'Sidebar (filter) — the menu array',
    'Rapor kayıt defteri (filtre) — rapor tanımları'
        => 'Report registry (filter) — report definitions',
    'Risk oluşturuldu (eylem) — $riskId, $veri'
        => 'Risk created (action) — $riskId, $data',
    'Risk güncellendi (eylem) — $riskId, $eski, $yeni'
        => 'Risk updated (action) — $riskId, $old, $new',
    'Risk silindi (eylem) — $riskId, $veri'
        => 'Risk deleted (action) — $riskId, $data',

    /* ---------------------------------------------------------------
     * Risk detail
     *
     * Bu etiketler $row() yardımcısından geçiyor, yani çeviri çağrı
     * yerinde değil yardımcının içinde yapılıyor. tools/i18n_check.php
     * bunları "kullanılmıyor" diye gösterir — değişken argümanlı
     * çağrıları göremez; aracın kendi çıktısı bunu söylüyor.
     * --------------------------------------------------------------- */
    'Risk Kodu'                   => 'Risk Code',
    'Etkin Seviye'                => 'Effective Severity',
    'Hedef Tarih'                 => 'Target Date',
    'Son Güncelleme'              => 'Last Updated',
    'Kapanış'                     => 'Closure',
    'Aksiyon bulunamadı.'         => 'Action not found.',
    'Bu risk için aksiyon planı yok' => 'No action plan for this risk',
    'Riski azaltacak somut adımları buradan ekleyin.'
        => 'Add the concrete steps that will reduce this risk.',
    'Değerlendirme kaydı yok'     => 'No assessment records',
    'Gözden geçirme'              => 'Review',

    /* ---------------------------------------------------------------
     * Print letterhead and signatures
     * --------------------------------------------------------------- */
    'Rapor tarihi'                => 'Report date',
    'Belge no'                    => 'Document no',
    'Hazırlayan'                  => 'Prepared by',
    'Gözden Geçiren'              => 'Reviewed by',
    'Onaylayan'                   => 'Approved by',
    'Sistem'                      => 'System',
    'Kapsam'                      => 'Scope',
    'Bu belge :sinif sınıfındadır. Yetkisiz kişilerle paylaşılmamalıdır.'
        => 'This document is classified :sinif. It must not be shared with unauthorised parties.',

    'Yeniden Değerlendir'           => 'Reassess',
    'Tüm değerlendirmeler'          => 'All assessments',
    'Etkilenen Varlık'              => 'Affected Asset',
    'İlk değerlendirme'             => 'Initial assessment',
    'Residual (kalan risk)'         => 'Residual (remaining risk)',
    'En fazla :boyut, kayıt başına :adet dosya. İzin verilen türler:'
        => 'Up to :boyut, :adet files per record. Allowed types:',

    /* ---------------------------------------------------------------
     * Dashboard cards, filters and form help
     *
     * Bu grup, sarma aracındaki bayt/karakter hizalama hatası
     * düzeltilince görünür oldu — metinler hep oradaydı, araç yanlış
     * yere bakıyordu.
     * --------------------------------------------------------------- */
    'Toplam Risk'                 => 'Total Risk',
    'Açık Risk'                   => 'Open Risk',
    'Ortalama Skor'               => 'Average Score',
    'Olasılık × Etki'             => 'Likelihood × Impact',
    'Son 30 Günde'                => 'In the last 30 days',
    'Hiç Gözden Geçirilmemiş'     => 'Never Reviewed',
    'Termini geçmiş açık risk'    => 'Open risks past their target date',
    'Henüz değerlendirilmedi'     => 'Not assessed yet',

    'Etkin'                       => 'Effective',
    'Geciken'                     => 'Overdue',
    'Tamamlanan'                  => 'Completed',
    'Devam Ediyor'                => 'In Progress',
    'Kullanım'                    => 'Usage',

    'Azaltılmış'                  => 'Mitigated',
    'Azaltılmış (Mitigated)'      => 'Mitigated',
    'Kabul edilmiş (Accepted)'    => 'Accepted',
    'Devredilmiş (Transferred)'   => 'Transferred',
    'Kapatılmış (Closed)'         => 'Closed',

    'Yalnızca gecikenler'         => 'Overdue only',
    'Aksiyon başlığı, açıklama veya risk kodu'
        => 'Action title, description or risk code',
    'Risk kodu, başlık veya not'  => 'Risk code, title or note',
    'Kullanıcı adı, IP veya değişen değer'
        => 'User name, IP or changed value',
    'Kullanıcı sonradan silindi'  => 'User was deleted afterwards',

    'Aktif (giriş yapabilir)'     => 'Active (can sign in)',
    'Giriş için kullanılır, benzersiz olmalıdır.'
        => 'Used to sign in; must be unique.',
    'Kendi rolünüzü değiştiremezsiniz.'
        => 'You cannot change your own role.',
    'Riskin takibinden sorumlu kişi.'
        => 'The person responsible for tracking this risk.',
    'Riskin kapatılması hedeflenen tarih.'
        => 'The date by which the risk should be closed.',
    'Geriye dönük kayıt girilebilir, ileri tarih girilemez.'
        => 'Past dates are allowed; future dates are not.',
    'Denetimde en çok sorulan soru budur: skor neden değişti?'
        => 'This is the question auditors ask most: why did the score change?',

    'RFC 4180 — sistem entegrasyonu için'
        => 'RFC 4180 — for system integration',
    'Türkçe Excel\'de çift tıkla açılır'
        => 'Opens on double-click in Turkish Excel',

    /* Örnek metinler: form alanlarının placeholder değerleri. */
    'Örn: BT Risk Analisti'       => 'e.g. IT Risk Analyst',
    'Örn: Elif Kaya'              => 'e.g. Jane Doe',
    'Örn: Internete açık RDP servisleri üzerinden yetkisiz erişim'
        => 'e.g. Unauthorised access through internet-facing RDP services',
    'Örn: SRV-DC01, Müşteri Portalı, Yedekleme altyapısı'
        => 'e.g. SRV-DC01, Customer Portal, Backup infrastructure',

    'Kurumsal IT ve siber risk durumu — :tarih'
        => 'Corporate IT and cyber risk status — :tarih',
    'Hoş geldiniz, :ad.'            => 'Welcome, :ad.',
    'Risk Matrisi (5×5)'            => 'Risk Matrix (5×5)',
    'Etkin olasılık × etki'         => 'Effective likelihood × impact',
];
