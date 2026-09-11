-- =====================================================================
--  RiskOps - Baslangic verileri (seed)
--  FILE   : /var/www/riskops/database/seed.sql
--
--  NOT: Tum INSERT'ler "INSERT IGNORE" ile yazilmistir.
--       Dosya tekrar calistirilabilir ve kullanicinin panelden yaptigi
--       degisiklikleri EZMEZ.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- DEPARTMENTS
-- ---------------------------------------------------------------------
INSERT IGNORE INTO departments (name, code, description, sort_order) VALUES
('IT',              'IT',     'Bilgi teknolojileri operasyonlari ve altyapi',   10),
('Cyber Security',  'CYBER',  'Siber guvenlik, SOC ve guvenlik muhendisligi',   20),
('Finance',         'FIN',    'Finans ve muhasebe',                             30),
('Human Resources', 'HR',     'Insan kaynaklari',                               40),
('Operations',      'OPS',    'Operasyon ve is surekliligi',                    50),
('Management',      'MGMT',   'Ust yonetim ve strateji',                        60),
('Sales',           'SALES',  'Satis ve is gelistirme',                         70),
('Legal',           'LEGAL',  'Hukuk, uyum ve sozlesme yonetimi',               80);


-- ---------------------------------------------------------------------
-- RISK CATEGORIES
--   color -> listelerde ve dashboard grafiklerinde kullanilacak rozet rengi
-- ---------------------------------------------------------------------
INSERT IGNORE INTO risk_categories (name, code, description, color, sort_order) VALUES
('Cyber Security',       'CYBER',    'Siber saldiri, kotu amacli yazilim, veri sizintisi',       '#b91c1c', 10),
('Network Security',     'NETWORK',  'Ag mimarisi, firewall, segmentasyon, uzak erisim',         '#c2410c', 20),
('Application Security', 'APPSEC',   'Uygulama zafiyetleri, guvenli yazilim gelistirme',         '#a16207', 30),
('Data Protection',      'DATA',     'Kisisel veri, sifreleme, veri siniflandirma, KVKK/GDPR',   '#1d4ed8', 40),
('Access Control',       'ACCESS',   'Kimlik dogrulama, yetkilendirme, ayricalikli hesaplar',    '#6d28d9', 50),
('Infrastructure',       'INFRA',    'Sunucu, depolama, sanallastirma, yedekleme',               '#0f766e', 60),
('Cloud',                'CLOUD',    'Bulut yapilandirmasi, SaaS/IaaS, paylasimli sorumluluk',   '#0369a1', 70),
('Business Continuity',  'BCM',      'Is surekliligi, felaket kurtarma, RTO/RPO',                '#9f1239', 80),
('Compliance',           'COMPLY',   'Yasal duzenlemeler, standartlar, denetim bulgulari',       '#4338ca', 90),
('Third Party',          'THIRD',    'Tedarikci, outsourcing, tedarik zinciri riski',            '#7c2d12', 100),
('Operational IT',       'OPSIT',    'Degisiklik yonetimi, izleme, kapasite, insan hatasi',      '#475569', 110),
('Physical Security',    'PHYSICAL', 'Veri merkezi, fiziksel erisim, cevresel tehditler',        '#166534', 120),
('Other',                'OTHER',    'Diger / siniflandirilmamis',                               '#64748b', 999);


-- ---------------------------------------------------------------------
-- SETTINGS
--   setting_type: string | int | bool | json
--   is_editable=0 -> panelden degistirilemez (kod tarafindan yonetilir)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings
    (setting_key, setting_value, setting_type, setting_group, label, description, is_editable, sort_order)
VALUES
('application_name',    'RiskOps',        'string', 'general',  'Uygulama Adi',
    'Sidebar ve sayfa basliklarinda gorunur.', 1, 10),
('company_name',        '',               'string', 'general',  'Kurum Adi',
    'Raporlarin ustunde gorunur.', 1, 20),
('timezone',            'Europe/Istanbul','string', 'general',  'Saat Dilimi',
    'PHP date_default_timezone_set() icin gecerli bir deger olmalidir.', 1, 30),
('date_format',         'd.m.Y',          'string', 'general',  'Tarih Formati',
    'PHP date() formati.', 1, 40),
('datetime_format',     'd.m.Y H:i',      'string', 'general',  'Tarih-Saat Formati',
    'PHP date() formati.', 1, 50),
('items_per_page',      '25',             'int',    'general',  'Sayfa Basina Kayit',
    'Liste ekranlarinda varsayilan sayfalama boyutu.', 1, 60),

('risk_matrix_size',    '5',              'int',    'risk',     'Risk Matrisi Boyutu',
    'Su an yalnizca 5x5 desteklenmektedir.', 0, 10),
('severity_thresholds', '{"Low":[1,4],"Medium":[5,9],"High":[10,16],"Critical":[17,25]}',
                                          'json',   'risk',     'Severity Esikleri',
    'Risk skorunun hangi araliklarda hangi seviyeye karsilik geldigi.', 1, 20),
('risk_code_prefix',    'RISK',           'string', 'risk',     'Risk Kodu Oneki',
    'Uretilecek kod formati: ONEK-YYYY-0001', 1, 30),
('overdue_warning_days','7',              'int',    'risk',     'Yaklasan Termin Esigi (gun)',
    'Dashboard "Upcoming Deadlines" kartinda kac gun ileriye bakilacagi.', 1, 40),

('session_lifetime',    '3600',           'int',    'security', 'Oturum Suresi (saniye)',
    'Hareketsizlik sonrasi oturumun sonlanma suresi.', 1, 10),
('max_login_attempts',  '5',              'int',    'security', 'Maksimum Basarisiz Giris',
    'Bu sayiya ulasildiginda hesap gecici olarak kilitlenir.', 1, 20),
('lockout_duration',    '900',            'int',    'security', 'Kilitlenme Suresi (saniye)',
    'Basarisiz giris limiti asildiginda beklenmesi gereken sure.', 1, 30),
('password_min_length', '10',             'int',    'security', 'Minimum Parola Uzunlugu',
    'Yeni parolalar icin zorunlu minimum karakter sayisi.', 1, 40),
('audit_retention_days','730',            'int',    'security', 'Audit Log Saklama (gun)',
    'Bu sureden eski audit kayitlari arsivlenebilir. 0 = sinirsiz.', 1, 50),

('report_classification', 'Kuruma Ozel', 'string', 'general', 'Rapor Gizlilik Ibaresi',
    'Yazdirilan raporlarin ust ve alt bilgisinde gorunur. Bos birakilirsa gosterilmez.', 1, 70),
('report_footer_note', 'Bu rapor RiskOps tarafindan otomatik uretilmistir.',
                                          'string', 'general', 'Rapor Alt Not',
    'Yazdirilan raporlarin altinda gorunen aciklama satiri.', 1, 80);


-- ---------------------------------------------------------------------
-- ILK ADMIN KULLANICISI
--   README: "Ilk giris: admin@riskops.local / Admin123456 - uygulama
--   ilk giriste parola degistirmeye zorlar." Bu satir olmazsa belgelenmis
--   kurulum sonunda sisteme giris yapilabilecek hicbir hesap olusmaz
--   (asagidaki UPDATE yalnizca mevcut admin kaydini departmana baglar).
--   Parola bcrypt ile hashlenmistir; ilk giriste degisim zorunludur.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO users (name, email, password, role, status, must_change_password)
VALUES ('System Administrator', 'admin@riskops.local',
        '$2y$12$YOWfKRyFQrinKDSIU50zHOQww8udcyN6zQm7BmKuWcp41NkLv8SBS',
        'admin', 1, 1);


-- ---------------------------------------------------------------------
-- MEVCUT ADMIN KULLANICISINI IT departmanina bagla
--   (yalnizca departmani bos ise - kullanici secimini ezmez)
-- ---------------------------------------------------------------------
UPDATE users u
JOIN departments d ON d.code = 'IT'
SET u.department_id = d.id,
    u.title         = COALESCE(NULLIF(u.title, ''), 'System Administrator')
WHERE u.email = 'admin@riskops.local'
  AND u.department_id IS NULL;


-- =====================================================================
--  SEED SONU
-- =====================================================================
