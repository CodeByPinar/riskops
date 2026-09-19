# -*- coding: utf-8 -*-
"""README gorsellerini uretir.

    python3 docs/assets/generate.py .

Uretilenler:
    docs/assets/hero.svg       animasyonlu afis + 5x5 risk matrisi
    docs/assets/lifecycle.svg  animasyonlu risk yasam dongusu

NEDEN DIS SERVIS DEGIL
    readme-typing-svg gibi servisler her goruntulemede ucuncu bir
    sunucuya istek atar; depo onlarin ayakta kalmasina bagimli hale
    gelir. Proje "CDN yok, bagimlilik yok" uzerine kurulu (ADR-0001),
    rozet satirinda bunu yazip hemen altinda dis servise baglanmak
    tutarsiz olurdu.

NEDEN URETECI BETIK, ELDE YAZILMIS SVG DEGIL
    Matrisin 25 hucresinin rengi skordan (olasilik x etki) hesaplaniyor
    ve her hucrenin kendi animasyon gecikmesi var. Elle yazmak hem uzun,
    hem de esik degerleriyle tutarsizliga acik: esikler degisirse burada
    TEK BIR YER degisiyor.

NOTLAR
    - GitHub markdown icindeki satir ici SVG'yi temizler; ayri dosya
      olarak <img> ile gosterilen SVG'de CSS animasyonlari ve SMIL
      calisir. Dosyalar bu yuzden ayri duruyor.
    - XML yorumlarinda "--" kullanilamaz; ayrac yorumlari bu yuzden sade.
    - prefers-reduced-motion destekleniyor: hareket kapatildiginda
      animasyon durur ve son kare gosterilir.
    - Renkler assets/css/app.css icindeki degiskenlerle ayni.
"""
import io, os, re, sys

NAVY     = '#041f3c'
NAVY_MID = '#0b356d'
BLUE     = '#016ccc'
BLUE_BRT = '#0d9bf6'
BLUE_LT  = '#23b0f8'
FG       = '#eaf3ff'
FG_SOFT  = '#9fb8d4'
FG_MUTED = '#6685a8'

LOW, MED, HIGH, CRIT = '#16a34a', '#ca8a04', '#ea580c', '#b91c1c'
SEV = [(4, LOW), (9, MED), (14, HIGH), (25, CRIT)]

FONT = ("-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,"
        "'Helvetica Neue',Arial,sans-serif")


def sev_color(score):
    for limit, color in SEV:
        if score <= limit:
            return color
    return CRIT


NAVY_CARD = '#062949'
GREEN     = '#16a34a'

def hero():
    W, H = 1000, 320
    cell, gap = 30, 7
    grid = 5 * cell + 4 * gap                 # 178
    gx, gy = W - grid - 96, 80                # matris sol-ust

    cells = []
    for row in range(5):
        likelihood = 5 - row                  # ust satir en yuksek olasilik
        for col in range(5):
            impact = col + 1
            x = gx + col * (cell + gap)
            y = gy + row * (cell + gap)
            delay = 0.55 + (row + col) * 0.055
            cells.append(
                f'<rect class="c" x="{x:.0f}" y="{y:.0f}" width="{cell}" height="{cell}" '
                f'rx="7" fill="{sev_color(likelihood * impact)}" '
                f'style="animation-delay:{delay:.2f}s"/>'
            )

    chips = []
    for n, (big, small) in enumerate([('8', 'PHPStan'), ('192', 'test'),
                                      ('0', 'bağımlılık'), ('2', 'dil')]):
        cx = 58 + n * 106
        chips.append(
            f'<g class="chip" style="animation-delay:{1.15 + n * 0.1:.2f}s">'
            f'<rect x="{cx}" y="212" width="94" height="48" rx="12" '
            f'fill="rgba(255,255,255,.055)" stroke="rgba(255,255,255,.1)"/>'
            f'<text x="{cx + 15}" y="239" class="cbig">{big}</text>'
            f'<text x="{cx + 15}" y="253" class="csml">{small}</text>'
            f'</g>'
        )

    scan_from = gx - 34
    scan_to   = gx + grid + 8

    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}"
     width="{W}" height="{H}" role="img"
     aria-label="RiskOps — BT ve siber guvenlik risk yonetimi platformu">
  <title>RiskOps</title>

  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{NAVY}"/>
      <stop offset="0.55" stop-color="#072a52"/>
      <stop offset="1" stop-color="{NAVY_MID}"/>
    </linearGradient>

    <!-- Basligin uzerinden gecen isik: gradyanin KENDISI kayiyor,
         boylece maske ya da filtre gerekmiyor. -->
    <linearGradient id="shine" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0"    stop-color="{FG}"/>
      <stop offset="0.40" stop-color="{FG}"/>
      <stop offset="0.50" stop-color="{BLUE_LT}"/>
      <stop offset="0.60" stop-color="{FG}"/>
      <stop offset="1"    stop-color="{FG}"/>
      <animateTransform attributeName="gradientTransform" type="translate"
                        values="-1.2 0; 1.2 0; 1.2 0" dur="7s" repeatCount="indefinite"/>
    </linearGradient>

    <linearGradient id="rule" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="{BLUE}" stop-opacity="0"/>
      <stop offset="0.5" stop-color="{BLUE_LT}"/>
      <stop offset="1" stop-color="{BLUE}" stop-opacity="0"/>
    </linearGradient>

    <!-- Seviye seridi: matrisin renk mantiginin ozeti -->
    <linearGradient id="sev" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0"    stop-color="{LOW}"/>
      <stop offset="0.34" stop-color="{MED}"/>
      <stop offset="0.67" stop-color="{HIGH}"/>
      <stop offset="1"    stop-color="{CRIT}"/>
    </linearGradient>

    <radialGradient id="glow" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0" stop-color="{BLUE_BRT}" stop-opacity="0.28"/>
      <stop offset="1" stop-color="{BLUE_BRT}" stop-opacity="0"/>
    </radialGradient>

    <pattern id="dots" width="22" height="22" patternUnits="userSpaceOnUse">
      <circle cx="1.5" cy="1.5" r="1.1" fill="#ffffff" fill-opacity="0.045"/>
    </pattern>

    <!-- Tarama seridi yalnizca matrisin uzerinde gorunur -->
    <clipPath id="gridclip">
      <rect x="{gx - 5:.0f}" y="{gy - 5:.0f}" width="{grid + 10}" height="{grid + 10}" rx="10"/>
    </clipPath>
  </defs>

  <style>
    text {{ font-family: {FONT}; }}

    .brand  {{ font-size: 62px;   font-weight: 800; letter-spacing: -1.8px; fill: url(#shine); }}
    .kicker {{ font-size: 11.5px; font-weight: 700; letter-spacing: 2.4px;  fill: {BLUE_LT}; }}
    .lead   {{ font-size: 16px;   font-weight: 600; fill: {FG}; }}
    .cbig   {{ font-size: 19px;   font-weight: 700; fill: {FG}; }}
    .csml   {{ font-size: 10px;   font-weight: 500; fill: {FG_MUTED}; letter-spacing: .3px; }}
    .axis   {{ font-size: 10px;   font-weight: 600; fill: {FG_MUTED}; letter-spacing: 1.2px; }}
    .tick   {{ font-size: 9.5px;  font-weight: 500; fill: {FG_SOFT}; }}

    /* Giris animasyonlari BIR KEZ oynar (forwards). Surekli kipirdayan
       bir afis okumayi zorlastirir; tekrarlayan tek sey basliktaki isik
       ve matris taramasi. */
    @keyframes rise {{ from {{ opacity: 0; transform: translateY(10px); }}
                       to   {{ opacity: 1; transform: none; }} }}
    @keyframes pop  {{ 0%   {{ opacity: 0; transform: scale(.55); }}
                       70%  {{ opacity: 1; transform: scale(1.07); }}
                       100% {{ opacity: 1; transform: scale(1); }} }}
    @keyframes grow {{ from {{ transform: scaleX(0); }} to {{ transform: scaleX(1); }} }}

    .r    {{ opacity: 0; animation: rise .7s cubic-bezier(.2,.7,.3,1) forwards; }}
    .chip {{ opacity: 0; animation: rise .6s cubic-bezier(.2,.7,.3,1) forwards; }}
    .c    {{ opacity: 0; transform-box: fill-box; transform-origin: center;
             animation: pop .5s cubic-bezier(.25,1.4,.5,1) forwards; }}
    .hr   {{ transform-origin: left center; animation: grow .9s .35s ease-out backwards; }}
    .bar  {{ transform-origin: left center; animation: grow 1s 1.5s cubic-bezier(.2,.7,.3,1) backwards; }}

    /* Hareketi azalt: animasyon kapanir, son kare gosterilir. */
    @media (prefers-reduced-motion: reduce) {{
      .r, .chip, .c, .hr, .bar {{ animation: none; opacity: 1; transform: none; }}
      .brand {{ fill: {FG}; }}
    }}
  </style>

  <rect width="{W}" height="{H}" rx="20" fill="url(#bg)"/>
  <rect width="{W}" height="{H}" rx="20" fill="url(#dots)"/>
  <ellipse cx="{gx + grid / 2:.0f}" cy="{H / 2:.0f}" rx="240" ry="190" fill="url(#glow)"/>

  <!-- SOL BLOK -->
  <g class="r" style="animation-delay:.05s">
    <text x="58" y="88" class="kicker">BT &amp; SİBER RİSK YÖNETİMİ</text>
  </g>

  <g class="r" style="animation-delay:.15s">
    <text x="56" y="146" class="brand">RiskOps</text>
  </g>

  <rect class="hr" x="58" y="163" width="136" height="2.5" rx="1.25" fill="url(#rule)"/>

  <g class="r" style="animation-delay:.45s">
    <text x="58" y="192" class="lead">Risk envanteri · 5×5 değerlendirme · aksiyon takibi</text>
  </g>

  {''.join(chips)}

  <!-- MATRIS -->
  <g class="r" style="animation-delay:.4s">
    <text x="{gx:.0f}" y="{gy - 16:.0f}" class="axis">5 × 5 RİSK MATRİSİ</text>
  </g>

  {''.join(cells)}

  <g clip-path="url(#gridclip)">
    <rect x="{scan_from:.0f}" y="{gy - 5:.0f}" width="26" height="{grid + 10}"
          fill="{BLUE_LT}" opacity="0.14">
      <animate attributeName="x"
               values="{scan_from:.0f};{scan_to:.0f};{scan_to:.0f}"
               dur="4.5s" begin="2.2s" repeatCount="indefinite"/>
    </rect>
  </g>

  <!-- Seviye seridi + uc etiketleri -->
  <rect class="bar" x="{gx:.0f}" y="{gy + grid + 16:.0f}" width="{grid}" height="6"
        rx="3" fill="url(#sev)"/>
  <g class="r" style="animation-delay:1.9s">
    <text x="{gx:.0f}" y="{gy + grid + 38:.0f}" class="tick">düşük</text>
    <text x="{gx + grid:.0f}" y="{gy + grid + 38:.0f}" class="tick" text-anchor="end">kritik</text>
  </g>

  <!-- Eksenler -->
  <g class="r" style="animation-delay:1.3s">
    <text x="{gx + grid:.0f}" y="{gy - 16:.0f}" class="axis" text-anchor="end">ETKİ →</text>
    <text x="{gx - 18:.0f}" y="{gy + grid / 2:.0f}" class="axis" text-anchor="middle"
          transform="rotate(-90 {gx - 18:.0f} {gy + grid / 2:.0f})">OLASILIK →</text>
  </g>
</svg>
'''



NODES = [
    ('Risk kaydı',        'envantere girer'),
    ('5×5 değerlendirme', 'olasılık × etki'),
    ('Seviye ataması',    'düşük → kritik'),
    ('Aksiyon planı',     'sorumlu + termin'),
]


def flow():
    W, H = 960, 250
    bw, bh = 188, 62
    gap = (W - 2 * 52 - 4 * bw) / 3          # 4 kutu, 3 aralik
    x0, y0 = 52, 58

    boxes, arrows = [], []
    for i, (title, sub) in enumerate(NODES):
        x = x0 + i * (bw + gap)
        boxes.append(
            f'<g class="n" style="animation-delay:{0.1 + i * 0.14:.2f}s">'
            f'<rect x="{x:.0f}" y="{y0}" width="{bw}" height="{bh}" rx="13" '
            f'fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.13)"/>'
            f'<text x="{x + bw / 2:.0f}" y="{y0 + 26}" class="nt">{title}</text>'
            f'<text x="{x + bw / 2:.0f}" y="{y0 + 44}" class="ns">{sub}</text>'
            f'</g>'
        )
        if i < len(NODES) - 1:
            ax = x + bw
            arrows.append(
                f'<path class="flow" d="M {ax + 6:.0f} {y0 + bh / 2:.0f} '
                f'H {ax + gap - 12:.0f}" style="animation-delay:{0.5 + i * 0.14:.2f}s"/>'
                f'<path class="head" d="M {ax + gap - 14:.0f} {y0 + bh / 2 - 4:.0f} '
                f'l 5 4 l -5 4"/>'
            )

    last_x  = x0 + 3 * (bw + gap) + bw / 2
    first_x = x0 + bw / 2
    ry      = 186                            # donus kolunun yuksekligi

    # Donus kolu: asagi in, sola git, yukari cik (yuvarlatilmis koseler)
    back = (f'M {last_x:.0f} {y0 + bh + 6} '
            f'V {ry - 14} Q {last_x:.0f} {ry} {last_x - 14:.0f} {ry} '
            f'H {first_x + 14:.0f} Q {first_x:.0f} {ry} {first_x:.0f} {ry - 14} '
            f'V {y0 + bh + 6}')

    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}"
     width="{W}" height="{H}" role="img"
     aria-label="Risk yasam dongusu: kayit, degerlendirme, seviye, aksiyon, yeniden degerlendirme">
  <title>Risk yaşam döngüsü</title>

  <style>
    text {{ font-family: {FONT}; text-anchor: middle; }}
    .nt  {{ font-size: 14.5px; font-weight: 700; fill: {FG}; }}
    .ns  {{ font-size: 11px;   font-weight: 500; fill: {FG_MUTED}; }}
    .lbl {{ font-size: 12.5px; font-weight: 700; fill: {BLUE_LT}; letter-spacing: .3px; }}
    .sml {{ font-size: 10.5px; font-weight: 500; fill: {FG_SOFT}; }}

    @keyframes rise {{ from {{ opacity: 0; transform: translateY(8px); }}
                       to   {{ opacity: 1; transform: none; }} }}
    /* Kesik deseni kayiyor: cizgi boyanmiyor, AKIYOR. */
    @keyframes march {{ to {{ stroke-dashoffset: -28; }} }}
    @keyframes fade  {{ from {{ opacity: 0; }} to {{ opacity: 1; }} }}

    .n {{ opacity: 0; animation: rise .6s cubic-bezier(.2,.7,.3,1) forwards; }}

    .flow {{
      stroke: {BLUE_LT}; stroke-width: 2; fill: none; stroke-linecap: round;
      stroke-dasharray: 6 8; opacity: 0;
      animation: fade .4s forwards, march 1.2s linear infinite;
    }}
    .back {{
      stroke: {GREEN}; stroke-width: 2; fill: none; stroke-linecap: round;
      stroke-dasharray: 6 8; opacity: 0;
      animation: fade .5s 1s forwards, march 1.6s linear infinite reverse;
    }}
    .head {{ stroke: {BLUE_LT}; stroke-width: 2; fill: none;
             stroke-linecap: round; stroke-linejoin: round;
             opacity: 0; animation: fade .4s .9s forwards; }}
    .t   {{ opacity: 0; animation: rise .6s 1.15s cubic-bezier(.2,.7,.3,1) forwards; }}

    @media (prefers-reduced-motion: reduce) {{
      .n, .flow, .back, .head, .t {{ animation: none; opacity: 1; transform: none; }}
    }}
  </style>

  <rect width="{W}" height="{H}" rx="18" fill="{NAVY_CARD}"/>

  {''.join(boxes)}
  {''.join(arrows)}

  <path class="back" d="{back}"/>

  <g class="t">
    <rect x="{W / 2 - 132:.0f}" y="{ry - 15}" width="264" height="30" rx="15"
          fill="{NAVY_CARD}"/>
    <text x="{W / 2:.0f}" y="{ry + 1}" class="lbl">Yeniden değerlendirme</text>
    <text x="{W / 2:.0f}" y="{ry + 30}" class="sml">geçmiş kayıtlar silinmez — seviye düşürülür, risk kapatılmaz</text>
  </g>
</svg>
'''




# =====================================================================
#  ROZETLER VE GEZINME CIPLERI
#
#  NEDEN KENDIMIZ URETIYORUZ
#    Onceki surumde rozetler shields.io'dan geliyordu. Iki sorun vardi:
#    (1) shields'in varsayilan paleti projenin renkleriyle ilgisiz, satir
#    rastgele renkli kutular gibi duruyordu; (2) uzerinde "CDN yok" yazan
#    bir rozetin bir CDN'den gelmesi, en hafif tabirle, tuhaf.
#
#    Tek istisna CI DURUMU: o gercekten dinamik, kosunun sonucunu
#    gostermesi gerekiyor. Onu shields uzerinden aliyoruz ama RENKLERINI
#    burada tanimlananlarla ayni yapiyoruz ki satir tek parca dursun.
#
#  GEOMETRI shields'in "flat" bicimiyle ayni (yukseklik 20, kose 3):
#  boylece tek dis rozet kendi ciplerimizin arasinda yamali durmuyor.
#
#  METIN GENISLIGI TAHMIN EDILIYOR
#    SVG icinde metin olcemedigimiz icin karakter basina yaklasik genislik
#    tablosu kullaniliyor. Tahmin birkac piksel sasarsa cip biraz genis ya
#    da dar olur - kirilmaz. Tarayicida goz kontrolu yapilip sabitler
#    ayarlandi.
# =====================================================================

CHIP_H   = 20
CHIP_PAD = 9

# 11px yari kalin bir arayuz yazi tipi icin yaklasik ilerlemeler
_NARROW = set("iljItf.,:;'!|()[]{}")
_WIDE   = set("mMWw@")
_UPPER  = set("ABCDEFGHJKLNOPQRSUVXYZÇĞİÖŞÜ")


def text_width(t, size=11.0):
    w = 0.0
    for ch in t:
        if ch == ' ':
            w += 3.3
        elif ch in _NARROW:
            w += 3.4
        elif ch in _WIDE:
            w += 9.4
        elif ch in _UPPER:
            w += 7.2
        elif ch.isdigit():
            w += 6.3
        else:
            w += 6.1
    return w * (size / 11.0)


def _svg_chip(parts, h=CHIP_H, pad=CHIP_PAD, size=11, radius=3, weight=600):
    """parts: [(metin, zemin, yazi_rengi)] - soldan saga.

    GEZINME CIPLERI DAHA BUYUK: rozetlerle ayni olcude olduklarinda uc
    satir birbirinin ayni gorunuyor ve gezinme, veri gibi okunuyordu.
    Daha yuksek ve daha yuvarlak olmasi onlari "tiklanacak sey" yapiyor.
    """
    widths = [text_width(t, size) + 2 * pad for t, _, _ in parts]
    total  = sum(widths)

    segs, x = [], 0.0
    for (t, bg, fg), w in zip(parts, widths):
        segs.append((x, w, t, bg, fg))
        x += w

    rects, texts = [], []
    baseline = h / 2 + size * 0.35
    for x, w, t, bg, fg in segs:
        rects.append(f'<rect x="{x:.1f}" width="{w:.1f}" height="{h}" fill="{bg}"/>')
        texts.append(
            f'<text x="{x + w / 2:.1f}" y="{baseline:.1f}" fill="{fg}">{escape(t)}</text>'
        )

    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="{total:.0f}" height="{h}"
     viewBox="0 0 {total:.1f} {h}" role="img"
     aria-label="{escape(' '.join(t for t, _, _ in parts))}">
  <title>{escape(' '.join(t for t, _, _ in parts))}</title>
  <clipPath id="r"><rect width="{total:.1f}" height="{h}" rx="{radius}"/></clipPath>
  <g clip-path="url(#r)">{''.join(rects)}</g>
  <g font-family="{FONT}" font-size="{size}" font-weight="{weight}"
     text-anchor="middle" letter-spacing=".1">{''.join(texts)}</g>
</svg>
'''


def escape(t):
    return (t.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))


def slugify(t):
    out = []
    tr = {'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u',
          'Ç': 'c', 'Ğ': 'g', 'İ': 'i', 'Ö': 'o', 'Ş': 's', 'Ü': 'u'}
    for ch in t.lower():
        ch = tr.get(ch, ch)
        out.append(ch if ch.isalnum() else '-')
    return re.sub(r'-+', '-', ''.join(out)).strip('-')


LABEL_BG = '#0b356d'          # sol taraf: marka lacivert
LABEL_FG = '#c5dcf7'
VALUE_FG = '#ffffff'

# RENKLER WCAG 4.5:1 ESIGINE GORE SECILDI
#   11px yari kalin metin "kucuk metin" sayilir. shields.io'nun yaygin
#   yesili #16a34a beyaz uzerinde 3.30:1 verir - yaygin ama gecmiyor.
#   Ayni sekilde PHP moru (#777bb4, 3.98) ve Docker mavisi (#2496ed,
#   3.15). Marka tonlarina yakin kalarak koyulastirildi.
#
#   Bu titizlik keyfi degil: README paletin renk korlugu acisindan
#   dogrulandigini yaziyor. Rozetlerde gevsek davranmak o cumleyi
#   curutur.
OK_GREEN = '#12803c'          # 5.03:1
ACCENT   = '#016ccc'          # 5.23:1
NEUTRAL  = '#546e8c'          # 5.27:1

# (etiket, deger, deger rengi)
BADGES = [
    ('PHPStan',   'seviye 8',      OK_GREEN),
    ('baseline',  'yok',           OK_GREEN),
    ('test',      '192',           OK_GREEN),
    ('biçim',     'PSR-12',        OK_GREEN),
    ('PHP',       '8.3',           '#5b5f9e'),          # 5.87:1
    ('MariaDB',   '10.11',         '#1f6f8b'),          # 5.67:1
    ('Docker',    'compose up',    '#1a6fc4'),          # 5.10:1
    ('framework', 'yok',           NEUTRAL),
    ('CDN',       'yok',           NEUTRAL),
    ('arayüz',    'TR / EN',       ACCENT),
    ('lisans',    'MIT',           OK_GREEN),
]

NAV = ['Nedir', 'Ne değildir', 'Ekranlar', 'Özellikler', 'Güvenlik',
       'Mimari', 'Kurulum', 'ADR', 'Hata ayıklama', 'Testler', 'Yol haritası']

NAV_BG = '#0f4a86'          # rozet etiketinden bir ton acik: hiyerarsi
NAV_FG = '#dceaf9'

if __name__ == '__main__':
    root = sys.argv[1] if len(sys.argv) > 1 else '.'
    target = os.path.join(root, 'docs', 'assets')
    os.makedirs(target, exist_ok=True)

    for name, svg in (('hero.svg', hero()), ('lifecycle.svg', flow())):
        path = os.path.join(target, name)
        io.open(path, 'w', encoding='utf-8', newline=chr(10)).write(svg)
        print("  + docs/assets/%-14s %6d bayt" % (name, os.path.getsize(path)))

    # Rozetler
    for label, value, color in BADGES:
        d = os.path.join(target, 'badge')
        os.makedirs(d, exist_ok=True)
        svg = _svg_chip([(label, LABEL_BG, LABEL_FG), (value, color, VALUE_FG)])
        io.open(os.path.join(d, slugify(label + '-' + value) + '.svg'),
                'w', encoding='utf-8', newline=chr(10)).write(svg)

    # Gezinme
    for text in NAV:
        d = os.path.join(target, 'nav')
        os.makedirs(d, exist_ok=True)
        svg = _svg_chip([(text, NAV_BG, NAV_FG)],
                        h=26, pad=13, size=12, radius=6, weight=650)
        io.open(os.path.join(d, slugify(text) + '.svg'),
                'w', encoding='utf-8', newline=chr(10)).write(svg)

    print("  + docs/assets/badge/  %d rozet" % len(BADGES))
    print("  + docs/assets/nav/    %d gezinme çipi" % len(NAV))
