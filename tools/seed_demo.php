<?php

declare(strict_types=1);

/**
 * RiskOps - Örnek veri üreteci (GELİŞTİRME ORTAMI İÇİN)
 * /var/www/riskops/tools/seed_demo.php
 *
 * Kullanım:
 *   php tools/seed_demo.php                -> örnek riskleri ve aksiyonları ekler
 *   php tools/seed_demo.php --purge --yes  -> TÜM risk verisini siler
 *
 * DİKKAT: Bu betik gerçek veri içeren bir kurulumda ÇALIŞTIRILMAMALIDIR.
 * Üretim ortamına alırken tools/ dizini sunucudan kaldırılmalıdır.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$argvFlags = array_slice($argv ?? [], 1);
$purge = in_array('--purge', $argvFlags, true);
$yes   = in_array('--yes', $argvFlags, true);

$pdo = db();

/* ------------------------------------------------------------------ */
/* PURGE                                                               */
/* ------------------------------------------------------------------ */

if ($purge) {
    if (!$yes) {
        fwrite(STDERR, "TÜM risk verisi silinecek. Onaylamak için --yes ekleyin.\n");
        exit(1);
    }
    $pdo->exec('DELETE FROM risk_actions');
    $pdo->exec('DELETE FROM risk_assessments');
    $pdo->exec('DELETE FROM risks');
    $pdo->exec('DELETE FROM risk_sequences');
    $pdo->exec("DELETE FROM audit_logs WHERE entity_type IN ('risk','risk_action')");
    echo "Tüm risk verisi silindi ve risk_code sayacı sıfırlandı.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* Demo kullanıcılar                                                   */
/* ------------------------------------------------------------------ */

$demoUsers = [
    ['Mert Yılmaz',  'manager@riskops.local',  'manager', 'CYBER', 'Siber Güvenlik Müdürü'],
    ['Elif Kaya',    'analyst@riskops.local',  'analyst', 'IT',    'BT Risk Analisti'],
    ['Burak Demir',  'analyst2@riskops.local', 'analyst', 'CYBER', 'Güvenlik Analisti'],
];

$deptByCode = [];
foreach (departments_list(false) as $d) {
    $deptByCode[$d['code']] = (int)$d['id'];
}
$catByCode = [];
foreach (categories_list(false) as $c) {
    $catByCode[$c['code']] = (int)$c['id'];
}

$defaultPassword = 'RiskOps2026Demo';
$userIds = [1];   // admin

foreach ($demoUsers as [$name, $email, $role, $deptCode, $title]) {
    $st = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
    $st->execute([':e' => $email]);
    $existing = $st->fetchColumn();

    if ($existing !== false) {
        $userIds[] = (int)$existing;
        continue;
    }

    $pdo->prepare(
        'INSERT INTO users (name, email, password, role, department_id, title, status, must_change_password, created_by)
         VALUES (:n, :e, :p, :r, :d, :t, 1, 1, 1)'
    )->execute([
        ':n' => $name,
        ':e' => $email,
        ':p' => password_hash($defaultPassword, PASSWORD_DEFAULT),
        ':r' => $role,
        ':d' => $deptByCode[$deptCode] ?? null,
        ':t' => $title,
    ]);
    $userIds[] = (int)$pdo->lastInsertId();
    echo "  kullanıcı eklendi: {$email} ({$role})\n";
}

/* ------------------------------------------------------------------ */
/* Örnek riskler                                                       */
/* ------------------------------------------------------------------ */

// [başlık, kategori, departman, varlık, tehdit, zafiyet, L, I, strateji, rL, rI, durum, termin(gün), açıklama]
$risks = [
    ['İnternete açık RDP servisleri üzerinden yetkisiz erişim', 'CYBER', 'IT',
     'SRV-TS01, SRV-TS02', 'Fidye yazılımı operatörleri', 'MFA bulunmayan RDP, zayıf parola politikası',
     4, 5, 'Mitigate', null, null, 'In Progress', 45,
     'Dış tarama sonucunda 3389 portunun internete açık olduğu iki sunucu tespit edildi.'],

    ['Ayrıcalıklı hesaplarda MFA eksikliği', 'ACCESS', 'CYBER',
     'Active Directory — Domain Admins', 'Kimlik bilgisi hırsızlığı', 'Yönetici hesaplarında tek faktörlü kimlik doğrulama',
     4, 5, 'Mitigate', null, null, 'In Progress', 30,
     'Domain Admins grubundaki 6 hesapta çok faktörlü kimlik doğrulama tanımlı değil.'],

    ['Yedeklerin geri dönüş testinin yapılmaması', 'BCM', 'IT',
     'Veeam yedekleme altyapısı', 'Veri kaybı / fidye yazılımı', 'Geri yükleme testi 14 aydır yapılmadı',
     3, 5, 'Mitigate', 2, 4, 'Open', -12,
     'Yedekler alınıyor ancak geri dönüş doğrulanmadığı için kurtarılabilirlik belirsiz.'],

    ['Kritik sunucularda eksik güvenlik yamaları', 'INFRA', 'IT',
     'SRV-DB01, SRV-APP03, SRV-FILE02', 'Bilinen zafiyetin istismarı', 'Yama döngüsü 90 günü aştı',
     4, 4, 'Mitigate', 2, 4, 'In Progress', 20,
     'Üç kritik sunucuda CVSS 9.0 üzeri açıklıklar kapatılmamış durumda.'],

    ['Web uygulamasında SQL injection açıklığı', 'APPSEC', 'IT',
     'Müşteri Portalı', 'Dış saldırgan', 'Parametrelendirilmemiş veritabanı sorguları',
     3, 5, 'Mitigate', 1, 5, 'Mitigated', 60,
     'Sızma testinde tespit edildi; prepared statement geçişi tamamlandı.'],

    ['Kişisel verilerin şifrelenmeden saklanması', 'DATA', 'LEGAL',
     'CRM veritabanı', 'Veri sızıntısı / KVKK yaptırımı', 'Hassas kolonlarda şifreleme uygulanmıyor',
     3, 5, 'Mitigate', 2, 4, 'Open', 90,
     'Kimlik numarası ve iletişim bilgileri düz metin olarak tutuluyor.'],

    ['Tedarikçi uzak erişiminin denetlenmemesi', 'THIRD', 'IT',
     'VPN — tedarikçi hesapları', 'Tedarik zinciri saldırısı', 'Oturum kaydı ve süre sınırlaması yok',
     3, 4, 'Mitigate', 2, 3, 'Under Review', 75,
     'Dört tedarikçi süresiz VPN erişimine sahip; oturumlar kayıt altına alınmıyor.'],

    ['Bulut depolama kovalarının herkese açık olması', 'CLOUD', 'IT',
     'S3 — raporlama-arşiv', 'Yetkisiz veri erişimi', 'Yanlış yapılandırılmış erişim politikası',
     3, 4, 'Mitigate', 1, 4, 'Mitigated', -30,
     'İki kova public-read olarak yapılandırılmıştı, erişim kapatıldı.'],

    ['Ağ segmentasyonunun yetersizliği', 'NETWORK', 'IT',
     'Kurumsal yerel ağ', 'Yanal hareket (lateral movement)', 'Düz ağ topolojisi, VLAN ayrımı yok',
     3, 4, 'Mitigate', null, null, 'Open', 180,
     'Kullanıcı ağı ile sunucu ağı aynı yayın alanında bulunuyor.'],

    ['Güvenlik olay izleme (SIEM) çözümünün bulunmaması', 'CYBER', 'CYBER',
     'Merkezi log altyapısı', 'Tespit edilemeyen saldırı', 'Log toplama ve korelasyon yapılmıyor',
     4, 4, 'Mitigate', null, null, 'Under Review', 150,
     'Güvenlik olayları ancak kullanıcı şikâyetiyle fark edilebiliyor.'],

    ['Çalışanların oltalama farkındalığının düşük olması', 'CYBER', 'HR',
     'Tüm personel', 'Oltalama (phishing)', 'Düzenli farkındalık eğitimi verilmiyor',
     4, 3, 'Mitigate', 3, 3, 'In Progress', 40,
     'Simülasyon testinde %31 tıklama oranı ölçümlendi.'],

    ['Veri merkezi fiziksel erişim kontrolünün zayıflığı', 'PHYSICAL', 'OPS',
     'Ana veri merkezi', 'Yetkisiz fiziksel erişim', 'Kart kaydı tutulmuyor, refakat zorunluluğu yok',
     2, 4, 'Mitigate', 1, 4, 'Open', 120,
     'Ziyaretçi girişleri kayıt altına alınmıyor ve refakat uygulanmıyor.'],

    ['ISO 27001 denetim bulgularının kapatılmaması', 'COMPLY', 'MGMT',
     'Bilgi güvenliği yönetim sistemi', 'Sertifikasyon kaybı', 'Açık bulgular için aksiyon planı yok',
     3, 3, 'Mitigate', 2, 3, 'In Progress', -5,
     'Önceki denetimden kalan 7 minör bulgu halen açık durumda.'],

    ['Desteği sona ermiş işletim sistemi kullanımı', 'INFRA', 'IT',
     'SRV-LEGACY01 (Windows Server 2012)', 'Desteklenmeyen sistemin istismarı', 'Üretici desteği sona erdi',
     3, 4, 'Accept', 3, 4, 'Accepted', null,
     'Uygulama bağımlılığı nedeniyle risk kabul edildi; sistem ağ seviyesinde izole edildi.'],

    ['Değişiklik yönetimi sürecinin uygulanmaması', 'OPSIT', 'IT',
     'Üretim ortamı', 'Hatalı değişiklik kaynaklı kesinti', 'Onaysız değişiklikler yapılıyor',
     3, 3, 'Mitigate', 2, 2, 'Mitigated', -60,
     'Değişiklik talep formu ve onay akışı devreye alındı.'],

    ['Siber sigorta kapsamının yetersizliği', 'COMPLY', 'FIN',
     'Kurumsal siber sigorta poliçesi', 'Karşılanmayan mali kayıp', 'Poliçe limiti risk iştahının altında',
     2, 4, 'Transfer', 2, 2, 'Transferred', -90,
     'Poliçe limiti yükseltildi ve fidye yazılımı teminatı kapsama eklendi.'],

    ['Mobil cihazlarda MDM çözümünün bulunmaması', 'ACCESS', 'IT',
     'Kurumsal mobil cihazlar', 'Cihaz kaybıyla veri sızıntısı', 'Uzaktan silme ve şifreleme zorunluluğu yok',
     3, 3, 'Mitigate', null, null, 'Open', 100,
     'Kurumsal e-posta kişisel cihazlardan denetimsiz olarak erişilebiliyor.'],

    ['Kritik uygulamada felaket kurtarma planının olmaması', 'BCM', 'OPS',
     'ERP', 'Uzun süreli hizmet kesintisi', 'RTO/RPO tanımlı değil, felaket kurtarma sahası yok',
     2, 5, 'Mitigate', null, null, 'Under Review', 200,
     'ERP kesintisi tüm operasyonu durdurur; kurtarma süresi bilinmiyor.'],
];

$inserted = 0;
$skipped  = 0;

foreach ($risks as $i => $r) {
    [$title, $catCode, $deptCode, $asset, $threat, $vuln,
     $lik, $imp, $treat, $rLik, $rImp, $status, $targetOffset, $desc] = $r;

    // Aynı başlık zaten varsa atla (betik tekrar çalıştırılabilir olsun)
    $st = $pdo->prepare('SELECT id FROM risks WHERE title = :t LIMIT 1');
    $st->execute([':t' => $title]);
    if ($st->fetchColumn() !== false) {
        $skipped++;
        continue;
    }

    // Sessiz fallback YOK: yanlış yazılmış bir departman kodu tüm riskleri
    // tek departmana toplar ve bu hata gözden kaçar.
    $catId  = $catByCode[$catCode]   ?? null;
    $deptId = $deptByCode[$deptCode] ?? null;
    if ($catId === null) {
        fwrite(STDERR, "  ATLANDI - kategori kodu yok '{$catCode}': {$title}\n");
        continue;
    }
    if ($deptId === null) {
        fwrite(STDERR, "  ATLANDI - departman kodu yok '{$deptCode}': {$title}\n");
        continue;
    }

    $ownerId   = $userIds[$i % count($userIds)];
    $createdBy = $userIds[($i + 1) % count($userIds)];
    $target    = $targetOffset === null ? null : date('Y-m-d', strtotime("{$targetOffset} days"));
    $age       = (count($risks) - $i) * 5;

    $pdo->beginTransaction();

    $code = next_risk_code($pdo);

    $pdo->prepare(
        'INSERT INTO risks
            (risk_code, title, description, category_id, department_id, asset_name,
             threat, vulnerability, owner_id, likelihood, impact, inherent_severity,
             treatment_strategy, residual_likelihood, residual_impact, residual_severity,
             status, target_date, created_by, created_at)
         VALUES
            (:code, :title, :desc, :cat, :dept, :asset, :threat, :vuln, :owner,
             :lik, :imp, :isev, :treat, :rlik, :rimp, :rsev, :status, :target, :cby,
             DATE_SUB(NOW(), INTERVAL :age DAY))'
    )->execute([
        ':code'   => $code,
        ':title'  => $title,
        ':desc'   => $desc,
        ':cat'    => $catId,
        ':dept'   => $deptId,
        ':asset'  => $asset,
        ':threat' => $threat,
        ':vuln'   => $vuln,
        ':owner'  => $ownerId,
        ':lik'    => $lik,
        ':imp'    => $imp,
        ':isev'   => severity_from_score($lik * $imp),
        ':treat'  => $treat,
        ':rlik'   => $rLik,
        ':rimp'   => $rImp,
        ':rsev'   => ($rLik !== null && $rImp !== null) ? severity_from_score($rLik * $rImp) : null,
        ':status' => $status,
        ':target' => $target,
        ':cby'    => $createdBy,
        ':age'    => $age,
    ]);

    $riskId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO risk_assessments
            (risk_id, assessment_type, likelihood, impact, severity, notes, assessed_by, assessed_at)
         VALUES (:rid, :type, :l, :i, :sev, :notes, :by, DATE_SUB(NOW(), INTERVAL :age DAY))'
    )->execute([
        ':rid'   => $riskId,
        ':type'  => 'initial',
        ':l'     => $lik,
        ':i'     => $imp,
        ':sev'   => severity_from_score($lik * $imp),
        ':notes' => 'İlk değerlendirme.',
        ':by'    => $createdBy,
        ':age'   => $age,
    ]);

    if ($rLik !== null && $rImp !== null) {
        $pdo->prepare(
            'INSERT INTO risk_assessments
                (risk_id, assessment_type, likelihood, impact, severity, notes, assessed_by, assessed_at)
             VALUES (:rid, :type, :l, :i, :sev, :notes, :by, DATE_SUB(NOW(), INTERVAL :age DAY))'
        )->execute([
            ':rid'   => $riskId,
            ':type'  => 'residual',
            ':l'     => $rLik,
            ':i'     => $rImp,
            ':sev'   => severity_from_score($rLik * $rImp),
            ':notes' => 'Kontroller uygulandıktan sonra kalan risk.',
            ':by'    => $ownerId,
            ':age'   => max(1, (count($risks) - $i) * 2),
        ]);
    }

    $pdo->commit();

    audit('risk_created', 'risk', $riskId, null,
        ['risk_code' => $code, 'title' => $title, 'status' => $status],
        $createdBy, 'Seed');

    $inserted++;
}

/* ------------------------------------------------------------------ */
/* Aksiyon planları                                                    */
/* ------------------------------------------------------------------ */

// [risk başlığı (kısmi eşleşme), aksiyon başlığı, öncelik, durum, termin(gün)]
$actions = [
    ['RDP servisleri',      'RDP erişimini VPN arkasına alma',                 'Critical', 'In Progress',  15],
    ['RDP servisleri',      'Tüm RDP hesaplarına MFA zorunluluğu getirme',     'Critical', 'Open',         30],
    ['MFA eksikliği',       'Domain Admin hesaplarına MFA tanımlama',          'Critical', 'In Progress',  10],
    ['MFA eksikliği',       'Ayrıcalıklı hesap envanterini çıkarma',           'High',     'Completed',   -20],
    ['Yedeklerin',          'Aylık geri yükleme testi prosedürünü yazma',      'High',     'Open',         -8],
    ['Yedeklerin',          'İlk tam geri yükleme tatbikatını gerçekleştirme', 'Critical', 'Open',         -3],
    ['güvenlik yamaları',   'Kritik sunucular için acil yama penceresi açma',  'Critical', 'In Progress',   7],
    ['güvenlik yamaları',   'Otomatik yama yönetimi çözümü değerlendirme',     'Medium',   'Open',         90],
    ['SQL injection',       'Tüm sorguları prepared statement hâline getirme', 'Critical', 'Completed',   -45],
    ['SQL injection',       'Doğrulama amaçlı sızma testi yaptırma',           'High',     'Completed',   -15],
    ['şifrelenmeden',       'Hassas kolonlar için şifreleme tasarımı',         'High',     'Open',         45],
    ['Tedarikçi',           'Tedarikçi erişimlerini süreli hâle getirme',      'High',     'In Progress',  60],
    ['Bulut depolama',      'Kova erişim politikalarını düzeltme',             'Critical', 'Completed',   -35],
    ['segmentasyonunun',    'VLAN tasarımı ve geçiş planı hazırlama',          'High',     'Open',        150],
    ['SIEM',                'SIEM ürün seçimi ve kavram doğrulama (PoC)',      'High',     'Open',        120],
    ['oltalama',            'Çeyreklik farkındalık eğitimi programı kurma',    'Medium',   'In Progress',  30],
    ['oltalama',            'Aylık oltalama simülasyonu yürütme',              'Medium',   'Open',         25],
    ['fiziksel erişim',     'Ziyaretçi kayıt defteri ve refakat kuralı',       'Medium',   'Open',         60],
    ['ISO 27001',           'Açık bulgular için düzeltici faaliyet planı',     'High',     'In Progress',  -2],
    ['Değişiklik yönetimi', 'Değişiklik onay akışını devreye alma',            'Medium',   'Completed',   -50],
    ['MDM',                 'MDM çözümü pilot uygulaması başlatma',            'Medium',   'Open',         80],
    ['felaket kurtarma',    'RTO/RPO belirleme çalıştayı düzenleme',           'High',     'Open',         45],
];

$actionCount   = 0;
$actionSkipped = 0;

foreach ($actions as $j => [$titlePart, $actionTitle, $priority, $aStatus, $dueOffset]) {
    $st = $pdo->prepare('SELECT id FROM risks WHERE title LIKE :t AND deleted_at IS NULL LIMIT 1');
    $st->execute([':t' => '%' . $titlePart . '%']);
    $riskId = $st->fetchColumn();

    if ($riskId === false) {
        fwrite(STDERR, "  ATLANDI - eşleşen risk yok '{$titlePart}': {$actionTitle}\n");
        $actionSkipped++;
        continue;
    }

    $dup = $pdo->prepare('SELECT id FROM risk_actions WHERE risk_id = :r AND title = :t LIMIT 1');
    $dup->execute([':r' => $riskId, ':t' => $actionTitle]);
    if ($dup->fetchColumn() !== false) {
        continue;
    }

    $pdo->prepare(
        'INSERT INTO risk_actions
            (risk_id, title, description, owner_id, priority, status, due_date, completed_at, created_by)
         VALUES (:rid, :title, :desc, :owner, :pri, :status, :due, :done, :cby)'
    )->execute([
        ':rid'    => (int)$riskId,
        ':title'  => $actionTitle,
        ':desc'   => null,
        ':owner'  => $userIds[$j % count($userIds)],
        ':pri'    => $priority,
        ':status' => $aStatus,
        ':due'    => date('Y-m-d', strtotime("{$dueOffset} days")),
        ':done'   => $aStatus === 'Completed'
                       ? date('Y-m-d H:i:s', strtotime(($dueOffset - 2) . ' days'))
                       : null,
        ':cby'    => 1,
    ]);
    $actionCount++;
}

/* ------------------------------------------------------------------ */

echo "\n";
echo "Eklenen risk        : {$inserted}\n";
echo "Atlanan (mevcut)    : {$skipped}\n";
echo "Eklenen aksiyon     : {$actionCount}\n";
if ($actionSkipped > 0) {
    echo "Eşleşmeyen aksiyon  : {$actionSkipped}\n";
}
echo "\nDemo kullanıcı parolası: {$defaultPassword}\n";
echo "  manager@riskops.local   (manager)\n";
echo "  analyst@riskops.local   (analyst)\n";
echo "  analyst2@riskops.local  (analyst)\n";
echo "\nTemizlemek için: php tools/seed_demo.php --purge --yes\n";
