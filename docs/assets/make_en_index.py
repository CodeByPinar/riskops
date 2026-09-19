# -*- coding: utf-8 -*-
"""docs/en/index.html — Türkçe tanıtım sayfasından İngilizce sürüm.

NEDEN ÇEVİRİ BETİĞİ, İKİNCİ BİR ELLE YAZILMIŞ SAYFA DEĞİL
    İki sayfa elle tutulursa biri kaçınılmaz olarak geride kalır.
    Burada YAPI tek yerden geliyor (docs/index.html), yalnızca metinler
    değişiyor. Yapısal bir değişiklik yapıldığında bu betik yeniden
    çalıştırılır ve iki dil ayrışmaz.

    Eşleşmeyen bir metin sessizce Türkçe kalmaz: betik durur ve hangi
    parçanın bulunamadığını söyler.
"""
import io, os, sys

R = sys.argv[1]
src = io.open(R + "/docs/index.html", encoding="utf-8").read()

# Uzun metinler önce: kısa bir eşleşme uzun cümlenin içini bozmasın.
SUBS = [
    # ---------------------------------------------------------- head
    ('<html lang="tr">', '<html lang="en">'),
    ("<title>RiskOps — BT ve Siber Güvenlik Risk Yönetimi Platformu</title>",
     "<title>RiskOps — IT &amp; Cyber Security Risk Management</title>"),
    ('content="Kurumsal risk envanteri, 5×5 değerlendirme, aksiyon takibi ve yönetim raporlaması. PHP 8.3 + MariaDB, çerçevesiz, CDN\'siz. Çalışan uygulamayı tarayıcıdan gezin."',
     'content="Corporate risk register, 5×5 assessment, action tracking and management reporting. PHP 8.3 + MariaDB, no framework, no CDN. Browse the running application."'),
    ('content="RiskOps — BT ve Siber Risk Yönetimi"',
     'content="RiskOps — IT &amp; Cyber Risk Management"'),
    ('content="Çalışan uygulamayı tarayıcıdan gezin. Kurumsal risk envanteri, 5×5 matris, aksiyon takibi, yönetim raporu."',
     'content="Browse the running application. Corporate risk register, 5×5 matrix, action tracking, executive report."'),

    # --------------------------------------------------------- paths
    ('href="demo/assets/img/favicon-32.png"', 'href="../demo/assets/img/favicon-32.png"'),
    ('src="assets/hero.svg"', 'src="../assets/hero.svg"'),
    ('src="assets/lifecycle.svg"', 'src="../assets/lifecycle.svg"'),
    ('src="screenshots/', 'src="../screenshots/'),
    ('href="demo/dashboard/"', 'href="demo/dashboard/"'),
    ('href="demo/risks/view/57/"', 'href="demo/risks/view/57/"'),
    ('href="demo/reports/executive_summary/"', 'href="demo/reports/executive_summary/"'),
    ('href="demo/admin/plugins/"', 'href="demo/admin/plugins/"'),
    ('<a class="brand" href="./">', '<a class="brand" href="./">'),

    # ---------------------------------------------------------- nav
    ('<a class="hide-sm" href="#nedir">Nedir</a>', '<a class="hide-sm" href="#what">What it is</a>'),
    ('<a class="hide-sm" href="#ekranlar">Ekranlar</a>', '<a class="hide-sm" href="#screens">Screens</a>'),
    ('<a class="hide-sm" href="#kalite">Kalite</a>', '<a class="hide-sm" href="#quality">Quality</a>'),
    ('<a class="hide-sm" href="#kurulum">Kurulum</a>', '<a class="hide-sm" href="#install">Install</a>'),
    ('<a class="lang" href="en/" hreflang="en" lang="en">EN</a>',
     '<a class="lang" href="../" hreflang="tr" lang="tr">TR</a>'),
    ('<a class="btn btn-primary" href="demo/dashboard/">Demoyu gez →</a>',
     '<a class="btn btn-primary" href="demo/dashboard/">Live demo →</a>'),

    # --------------------------------------------------------- hero
    ('<p class="kicker">BT &amp; SİBER RİSK YÖNETİMİ</p>',
     '<p class="kicker">IT &amp; CYBER RISK MANAGEMENT</p>'),
    ('<p>Risk envanteri · 5×5 değerlendirme · aksiyon takibi</p>',
     '<p>Risk register · 5×5 assessment · action tracking</p>'),
    ('<span>PHPStan seviyesi</span>', '<span>PHPStan level</span>'),
    ('<div><b>192</b><span>test</span></div>', '<div><b>192</b><span>tests</span></div>'),
    ('<span>bağımlılık</span>', '<span>dependencies</span>'),
    ('<div><b>2</b><span>dil</span></div>', '<div><b>2</b><span>languages</span></div>'),
    ('<a class="btn btn-primary" href="demo/dashboard/">Çalışan uygulamayı gez →</a>',
     '<a class="btn btn-primary" href="demo/dashboard/">Browse the running app →</a>'),
    ('>Kaynak kodu</a>', '>Source code</a>'),
    ("""    Demo, çalışan uygulamadan alınmış gerçek çıktıdır — ekran görüntüsü değil.
    Gezinme çalışır; kaydetme ve filtreleme çalışmaz.""",
     """    The demo is real output captured from the running app — not screenshots.
    Navigation works; saving and filtering do not."""),

    # -------------------------------------------------------- nedir
    ('<section id="nedir" class="wrap">', '<section id="what" class="wrap">'),
    ('<p class="kicker">NEDİR</p>', '<p class="kicker">WHAT IT IS</p>'),
    ('<h2>Risk çözülmez, seviyesi düşürülür</h2>',
     '<h2>A risk is not closed — its level is lowered</h2>'),
    ("""    RiskOps, bir kurumun BT ve siber güvenlik risklerini kayıt altına almak,
    olasılık × etki üzerinden puanlamak, azaltıcı aksiyonları takip etmek ve
    yönetime sunulabilir belgeler üretmek için yazıldı.""",
     """    RiskOps was written to record an organisation's IT and cyber security
    risks, score them by likelihood × impact, track mitigating actions and
    produce documents that can be handed to management."""),
    ('alt="Risk yaşam döngüsü: kayıt, değerlendirme, seviye ataması, aksiyon planı, yeniden değerlendirme"',
     'alt="Risk lifecycle: record, assessment, severity, action plan, reassessment"'),
    ('<h3><span class="dot" style="background:var(--crit)"></span> Envanter</h3>',
     '<h3><span class="dot" style="background:var(--crit)"></span> Register</h3>'),
    ("""<p>Otomatik risk kodu, 5×5 matris, yumuşak silme. Skor veritabanında
         üretilen kolon — uygulama onu hiç hesaplamaz, tutarsızlık imkânsız.</p>""",
     """<p>Automatic risk codes, 5×5 matrix, soft delete. The score is a
         generated column — the application never computes it, so it cannot
         drift.</p>"""),
    ('<h3><span class="dot" style="background:var(--high)"></span> Aksiyon</h3>',
     '<h3><span class="dot" style="background:var(--high)"></span> Actions</h3>'),
    ("""<p>Riske bağlı azaltıcı aksiyonlar, sorumlu ve termin. Geciken aksiyonlar
         panelde ve raporda ayrıca işaretlenir.</p>""",
     """<p>Mitigating actions linked to a risk, with an owner and a due date.
         Overdue actions are flagged on the dashboard and in reports.</p>"""),
    ('<h3><span class="dot" style="background:var(--low)"></span> Rapor</h3>',
     '<h3><span class="dot" style="background:var(--low)"></span> Reports</h3>'),
    ("""<p>Altı hazır rapor ve tek sayfalık Yönetici Özeti: antetli, gizlilik
         ibareli, imza bloklu yazdırma çıktısı.</p>""",
     """<p>Six built-in reports and a one-page Executive Summary: letterhead,
         confidentiality notice and a signature block, ready to print.</p>"""),

    # -------------------------------------------------- ne değildir
    ('<p class="kicker">SINIR</p>', '<p class="kicker">SCOPE</p>'),
    ('<h2>RiskOps ne değildir</h2>', '<h2>What RiskOps is not</h2>'),
    ('    Bu ayrım projenin en önemli tasarım kararı, bu yüzden gizlenmiyor.',
     '    This distinction is the most important design decision, so it is stated up front.'),
    ('<tr><th>Değildir</th><th>Neden önemli</th></tr>',
     '<tr><th>Not this</th><th>Why it matters</th></tr>'),
    ("""<tr><td>Helpdesk / ticket sistemi</td>
        <td>Burada “kapatılacak talep” yok; süregiden bir risk durumu var.</td></tr>""",
     """<tr><td>Helpdesk / ticketing</td>
        <td>There is no “request to close” here — there is an ongoing risk state.</td></tr>"""),
    ("""<tr><td>ITSM aracı</td>
        <td>Varlık envanteri, değişiklik yönetimi ve SLA takibi kapsam dışı.</td></tr>""",
     """<tr><td>ITSM tool</td>
        <td>Asset inventory, change management and SLA tracking are out of scope.</td></tr>"""),
    ("""<tr><td>Zafiyet tarayıcı</td>
        <td>Tarama yapmaz; tarama <em>sonuçlarının yönetildiği</em> yerdir.</td></tr>""",
     """<tr><td>Vulnerability scanner</td>
        <td>It does not scan; it is where scan <em>results are managed</em>.</td></tr>"""),
    ("""<tr><td>SIEM</td>
        <td>Log toplamaz, korelasyon kurmaz.</td></tr>""",
     """<tr><td>SIEM</td>
        <td>It does not collect logs or correlate events.</td></tr>"""),

    # ----------------------------------------------------- ekranlar
    ('<section id="ekranlar" class="wrap">', '<section id="screens" class="wrap">'),
    ('<p class="kicker">EKRANLAR</p>', '<p class="kicker">SCREENS</p>'),
    ('<h2>Gerçek uygulamayı gezin</h2>', '<h2>Browse the real application</h2>'),
    ("""    Aşağıdaki görseller demonun ilgili ekranına gider. Demo, çalışan
    kurulumdan indirilmiş HTML’dir: aynı CSS, aynı veri, aynı düzen.""",
     """    Each image opens that screen in the demo. The demo is HTML captured
    from a running installation: same CSS, same data, same layout."""),
    ('alt="RiskOps paneli"', 'alt="RiskOps dashboard"'),
    ('<figcaption>Panel <span>matris · trend · dağılımlar</span></figcaption>',
     '<figcaption>Dashboard <span>matrix · trend · distributions</span></figcaption>'),
    ('alt="Risk detay ekranı"', 'alt="Risk detail screen"'),
    ('<figcaption>Risk detayı <span>değerlendirme geçmişi · aksiyonlar</span></figcaption>',
     '<figcaption>Risk detail <span>assessment history · actions</span></figcaption>'),
    ('alt="Yönetici özeti raporu"', 'alt="Executive summary report"'),
    ('<figcaption>Yönetici Özeti <span>tek sayfa · yazdırmaya uygun</span></figcaption>',
     '<figcaption>Executive Summary <span>one page · print ready</span></figcaption>'),
    ('alt="Eklenti yönetim ekranı"', 'alt="Plugin administration screen"'),
    ('<figcaption>Eklentiler <span>kancalar · kum havuzu yok</span></figcaption>',
     '<figcaption>Plugins <span>hooks · no sandbox</span></figcaption>'),

    # ------------------------------------------------------- kalite
    ('<section id="kalite" class="wrap">', '<section id="quality" class="wrap">'),
    ('<p class="kicker">KALİTE</p>', '<p class="kicker">QUALITY</p>'),
    ('<h2>Ölçülen, iddia edilmeyen</h2>', '<h2>Measured, not claimed</h2>'),
    ("""    Sayılar depodaki araçların çıktısıdır; her sürüm CI’da yeniden ölçülür.""",
     """    These numbers come from tools in the repository and are re-measured by CI on every push."""),
    ('<span>PHPStan seviyesi, baseline yok</span>', '<span>PHPStan level, no baseline</span>'),
    ('<span>birim + entegrasyon testi</span>', '<span>unit + integration tests</span>'),
    ('<span>çalışma zamanı bağımlılığı</span>', '<span>runtime dependencies</span>'),
    ('<span>mimari karar kaydı (ADR)</span>', '<span>architecture decision records</span>'),
    ('<h3>Güvenlik varsayılanı</h3>', '<h3>Security defaults</h3>'),
    ('<li>Parolalar <code>password_hash()</code>, her POST’ta CSRF</li>',
     '<li>Passwords via <code>password_hash()</code>, CSRF on every POST</li>'),
    ('<li>Yetki kontrolü sunucu tarafında; arayüz yalnızca gizler</li>',
     '<li>Authorisation is server-side; the UI only hides things</li>'),
    ('<li>Silme yalnızca POST + CSRF + yetki ile</li>',
     '<li>Deletion requires POST + CSRF + permission</li>'),
    ('<li>CSP’de <code>unsafe-inline</code> yok — satır içi script yok</li>',
     '<li>No <code>unsafe-inline</code> in CSP — no inline scripts</li>'),
    ('<h3>Kararlar yazılı</h3>', '<h3>Decisions are written down</h3>'),
    ("""<p>Neden çerçeve yok, neden çeviri anahtarı Türkçe metnin kendisi,
         neden eklentiler için kum havuzu iddia edilmiyor — hepsi gerekçesiyle
         <a href="https://github.com/CodeByPinar/riskops/tree/main/docs/architecture">ADR’lerde</a>.</p>""",
     """<p>Why there is no framework, why the translation key is the Turkish
         source string, why no sandbox is claimed for plugins — each with its
         reasoning in the
         <a href="https://github.com/CodeByPinar/riskops/tree/main/docs/architecture">ADRs</a>.</p>"""),

    # ------------------------------------------------------ kurulum
    ('<section id="kurulum" class="wrap">', '<section id="install" class="wrap">'),
    ('<p class="kicker">KURULUM</p>', '<p class="kicker">INSTALL</p>'),
    ('<h2>İki komut</h2>', '<h2>Two commands</h2>'),
    ('<h3>Docker ile</h3>', '<h3>With Docker</h3>'),
    ("""<p style="margin-top:10px">Tarayıcıda <code>localhost:8080</code>. Demo verisi
         isteğe bağlı olarak tek komutla yüklenir.</p>""",
     """<p style="margin-top:10px">Then open <code>localhost:8080</code>. Demo data
         is optional and loads with a single command.</p>"""),
    ('<h3>Docker’sız</h3>', '<h3>Without Docker</h3>'),
    ("""<p>Ubuntu 24.04 · PHP 8.3 · MariaDB 10.11 · Apache 2.4.
         Şema tek dosya, iki kez yüklenebilir (idempotent).
         Adımlar <a href="https://github.com/CodeByPinar/riskops#kurulum-dockersız">README’de</a>.</p>""",
     """<p>Ubuntu 24.04 · PHP 8.3 · MariaDB 10.11 · Apache 2.4.
         The schema is a single file and can be loaded twice (idempotent).
         Steps are in the
         <a href="https://github.com/CodeByPinar/riskops#kurulum-dockersız">README</a>.</p>"""),

    # --------------------------------------------------------- alt
    ('<span>RiskOps · MIT lisansı</span>', '<span>RiskOps · MIT licence</span>'),
    ('<a href="https://github.com/CodeByPinar/riskops/tree/main/docs/architecture">Mimari kararlar</a>',
     '<a href="https://github.com/CodeByPinar/riskops/tree/main/docs/architecture">Architecture decisions</a>'),
    ('<a href="demo/dashboard/">Demo</a>', '<a href="demo/dashboard/">Demo</a>'),
    ('<span class="sp">Demo verisi kurgudur; gerçek kurum verisi içermez.</span>',
     '<span class="sp">Demo data is fictional; the interface is English, the records are Turkish.</span>'),
]

missing = []
for old, new in SUBS:
    if old not in src:
        missing.append(old[:64].replace("\n", " "))
        continue
    src = src.replace(old, new, 1)

if missing:
    print("  DURDURULDU — eşleşmeyen %d parça (sayfa yapısı değişmiş olabilir):" % len(missing))
    for m in missing:
        print("    " + m)
    sys.exit(1)

os.makedirs(R + "/docs/en", exist_ok=True)
io.open(R + "/docs/en/index.html", "w", encoding="utf-8", newline="\n").write(src)
print("  + docs/en/index.html")
