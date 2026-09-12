/* =====================================================================
   RiskOps - Arayuz davranislari
   /var/www/riskops/assets/js/app.js

   Kural: Buradaki hicbir sey bir GUVENLIK onlemi degildir.
          Tum yetki ve dogrulama kontrolleri sunucu tarafindadir.
   ===================================================================== */

(function () {
    'use strict';

    /* ----------------------------------------- Sidebar (mobil) */

    function closeSidebar() {
        document.body.classList.remove('rk-sidebar-open');
    }

    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-rk-sidebar-toggle]')) {
            document.body.classList.toggle('rk-sidebar-open');
            return;
        }
        if (ev.target.closest('[data-rk-sidebar-close]')) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            closeSidebar();
        }
    });

    /* ----------------------------------------- Flash mesaj kapatma */

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-rk-dismiss]');
        if (!btn) { return; }
        var box = btn.closest('.rk-alert');
        if (box) { box.remove(); }
    });

    /* ----------------------------------------- Onay gerektiren formlar
       Kullanim:
         <form method="post" data-rk-confirm="Bu risk silinecek. Emin misiniz?">
       Silme islemleri HER ZAMAN POST + CSRF ile yapilir; bu yalnizca
       kazara tiklamaya karsi bir UX korumasidir.
    ----------------------------------------------------------------- */

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        var message = form.getAttribute('data-rk-confirm');
        if (message && !window.confirm(message)) {
            ev.preventDefault();
            return;
        }

        // Cift gonderimi engelle
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn && !form.hasAttribute('data-rk-no-lock')) {
            window.setTimeout(function () {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '.65';
            }, 0);
        }
    });

    /* ----------------------------------------- Risk skoru canli onizleme
       Kullanim (risk formunda):
         <select data-rk-score="likelihood">...</select>
         <select data-rk-score="impact">...</select>
         <span data-rk-score-out></span>
       Esikler data-rk-thresholds ile JSON olarak verilir.
    ----------------------------------------------------------------- */

    function initScorePreview(scope) {
        var out = scope.querySelector('[data-rk-score-out]');
        if (!out) { return; }

        var lik = scope.querySelector('[data-rk-score="likelihood"]');
        var imp = scope.querySelector('[data-rk-score="impact"]');
        if (!lik || !imp) { return; }

        var thresholds = { Low: [1, 4], Medium: [5, 9], High: [10, 16], Critical: [17, 25] };
        try {
            var raw = out.getAttribute('data-rk-thresholds');
            if (raw) { thresholds = JSON.parse(raw); }
        } catch (e) { /* varsayilan kullanilir */ }

        var classes = {
            Low: 'sev-low', Medium: 'sev-medium', High: 'sev-high', Critical: 'sev-critical'
        };

        function severityOf(score) {
            for (var name in thresholds) {
                if (!Object.prototype.hasOwnProperty.call(thresholds, name)) { continue; }
                var range = thresholds[name];
                if (score >= range[0] && score <= range[1]) { return name; }
            }
            return score > 16 ? 'Critical' : 'Low';
        }

        function update() {
            var l = parseInt(lik.value, 10);
            var i = parseInt(imp.value, 10);
            if (!l || !i) {
                out.textContent = '-';
                out.className = 'rk-score sev-none';
                return;
            }
            var score = l * i;
            var sev = severityOf(score);
            out.textContent = score;
            out.className = 'rk-score ' + (classes[sev] || 'sev-none');
            out.setAttribute('title', sev);
        }

        lik.addEventListener('change', update);
        imp.addEventListener('change', update);
        update();
    }

    /* ------------------------------------------------------------------
       Parola goster / gizle
       ------------------------------------------------------------------
       Dugme SATIR ICI onclick ile degil, data-rk-pw-toggle ozniteligiyle
       baglaniyor. Sebep: CSP'den 'unsafe-inline' kaldirilabilsin (yol
       haritasinda duran madde). Satir ici bir onclick eklemek o maddeyi
       kalici olarak imkansiz kilardi.
       ------------------------------------------------------------------ */
    function initPasswordToggle(btn) {
        var input = document.getElementById(btn.getAttribute('data-rk-pw-toggle'));
        if (!input) { return; }

        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';

            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Parolayi gizle' : 'Parolayi goster');

            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
            }

            /* Odak alanda kalsin: kullanici yazmaya devam edebilsin. */
            input.focus();
        });
    }

    /* ------------------------------------------------------------------
       Renk secici <-> metin alani senkronu  (admin/categories)
       ------------------------------------------------------------------ */
    function initColorSync(picker) {
        var text = document.querySelector('input[name="' + picker.getAttribute('data-rk-color-sync') + '"]');
        if (!text) { return; }

        picker.addEventListener('input', function () { text.value = picker.value; });
        text.addEventListener('change', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(text.value)) { picker.value = text.value; }
        });
    }

    /* ------------------------------------------------------------------
       Esik onizlemesi  (admin/settings)
       Esik alanlari degistikce 5x5 onizleme matrisini canli boyar.
       ------------------------------------------------------------------ */
    function initThresholdPreview(editor) {
        var preview = document.getElementById(editor.getAttribute('data-rk-threshold-editor'));
        if (!preview) { return; }

        var order = ['Low', 'Medium', 'High', 'Critical'];
        var cls = { Low: 'sev-low', Medium: 'sev-medium', High: 'sev-high', Critical: 'sev-critical' };

        function repaint() {
            var bands = order.map(function (sev) {
                var min = editor.querySelector('[name="threshold_' + sev + '_min"]');
                var max = editor.querySelector('[name="threshold_' + sev + '_max"]');
                return {
                    sev: sev,
                    min: min ? parseInt(min.value, 10) : NaN,
                    max: max ? parseInt(max.value, 10) : NaN
                };
            });

            preview.querySelectorAll('.rk-mx-cell').forEach(function (cell) {
                var score = parseInt(cell.getAttribute('data-score'), 10);
                var hit = bands.find(function (b) { return score >= b.min && score <= b.max; });
                cell.className = 'rk-mx-cell ' + (hit ? cls[hit.sev] : 'sev-none');
                cell.title = hit ? hit.sev : 'hicbir banda girmiyor';
            });
        }

        editor.addEventListener('input', repaint);
        repaint();
    }

    /* ------------------------------------------------------------------
       Yazdir dugmesi
       onclick="window.print()" yerine: CSP script-src'den 'unsafe-inline'
       kaldirilabilsin diye. Satir ici bir olay ozniteligi de tipki satir
       ici <script> gibi 'unsafe-inline' gerektirir.
       ------------------------------------------------------------------ */
    function initPrintButton(btn) {
        btn.addEventListener('click', function () { window.print(); });
    }

    /* ------------------------------------------------------------------
       Toplu islem  (risks/index.php)
       ------------------------------------------------------------------
       Secim kutulari, "tumunu sec" ve islem cubugunun gorunurlugu.

       ONEMLI: bu katman yalnizca ARAYUZ. Hangi risklerin gercekten
       guncellenecegine ve kullanicinin yetkisi olup olmadigina
       risks/bulk.php karar verir. Buradaki hicbir kontrol guvenlik
       onlemi degildir.
       ------------------------------------------------------------------ */
    /* ------------------------------------------------------------------
       Yikici islemler icin onay
       ------------------------------------------------------------------
       data-rk-confirm tasiyan dugme, onay alinmadan formunu gondermez.
       Toplu islem formu kendi submit dinleyicisinde bunu zaten ele
       aliyor; burada o formun DISINDAKI dugmeler baglaniyor.

       Bu bir GUVENLIK onlemi degil, kaza onlemidir. Yetki ve CSRF
       kontrolu sunucuda.
       ------------------------------------------------------------------ */
    function initConfirm(btn) {
        if (btn.closest('[data-rk-bulk]')) { return; }   // toplu islem kendi halleder

        btn.addEventListener('click', function (ev) {
            if (!window.confirm(btn.getAttribute('data-rk-confirm'))) {
                ev.preventDefault();
            }
        });
    }

    function initBulk(form) {
        var bar   = form.querySelector('[data-rk-bulk-bar]');
        var all   = form.querySelector('[data-rk-bulk-all]');
        var count = form.querySelector('[data-rk-bulk-count]');
        var items = function () {
            return Array.prototype.slice.call(form.querySelectorAll('[data-rk-bulk-item]'));
        };

        function selected() {
            return items().filter(function (c) { return c.checked; });
        }

        function sync() {
            var n = selected().length;
            var total = items().length;

            if (count) { count.textContent = String(n); }
            if (bar) { bar.hidden = (n === 0); }

            if (all) {
                all.checked = (n > 0 && n === total);
                /* Kismi secimde ucuncu bir gorsel durum: kullanici
                   "tumu secili" saniip yanlis islem yapmasin. */
                all.indeterminate = (n > 0 && n < total);
            }
        }

        if (all) {
            all.addEventListener('change', function () {
                items().forEach(function (c) { c.checked = all.checked; });
                sync();
            });
        }

        form.addEventListener('change', function (ev) {
            if (ev.target && ev.target.matches('[data-rk-bulk-item]')) { sync(); }
        });

        /* Gonderim oncesi son kontrol: secim yoksa ya da islem bir
           deger bekliyorsa (sahip/durum) kullaniciyi bos istekle
           sunucuya gondermeyelim. */
        form.addEventListener('submit', function (ev) {
            var btn = ev.submitter;
            var act = btn && btn.value;

            if (selected().length === 0) {
                ev.preventDefault();
                alert('Once en az bir risk secin.');
                return;
            }

            if (act === 'assign' || act === 'status') {
                var input = form.querySelector('[data-rk-bulk-input="' + act + '"]');
                if (input && input.value === '') {
                    ev.preventDefault();
                    input.focus();
                    return;
                }
            }

            if (btn && btn.hasAttribute('data-rk-confirm')) {
                if (!window.confirm(btn.getAttribute('data-rk-confirm'))) {
                    ev.preventDefault();
                }
            }
        });

        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rk-score-scope]').forEach(initScorePreview);
        document.querySelectorAll('[data-rk-pw-toggle]').forEach(initPasswordToggle);
        document.querySelectorAll('[data-rk-color-sync]').forEach(initColorSync);
        document.querySelectorAll('[data-rk-threshold-editor]').forEach(initThresholdPreview);
        document.querySelectorAll('[data-rk-print]').forEach(initPrintButton);
        document.querySelectorAll('[data-rk-bulk]').forEach(initBulk);
        document.querySelectorAll('[data-rk-confirm]').forEach(initConfirm);
    });

})();
