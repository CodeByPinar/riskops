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

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rk-score-scope]').forEach(initScorePreview);
    });

})();
