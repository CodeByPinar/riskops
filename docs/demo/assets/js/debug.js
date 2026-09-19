/* =====================================================================
   RiskOps - Hata ayiklama arac cubugu davranisi
   /var/www/riskops/assets/js/debug.js

   Yalnizca hata ayiklama kipinde yuklenir.

   BU DOSYA VERI TASIMAZ. Panellerin icerigi sunucuda HTML olarak
   basilir; buradaki tek is sekme acip kapatmak ve acik sekmeyi
   hatirlamaktir. Boylece CSP'ye satir ici <script> veya JSON blogu
   eklemek gerekmiyor (script-src 'self').
   ===================================================================== */

(function () {
    'use strict';

    var root = document.getElementById('rkdbg');
    if (!root) { return; }

    var body   = root.querySelector('.rkdbg-body');
    var tabs   = Array.prototype.slice.call(root.querySelectorAll('[data-rkdbg-tab]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-rkdbg-panel]'));
    var KEY    = 'rkdbg.panel';

    /* localStorage her baglamda yoktur (gizli sekme, kisitli tarayici).
       Erisilemiyorsa arac cubugu yine calismali, sadece sekme
       hatirlanmaz. */
    function remember(value) {
        try {
            if (value === null) { window.localStorage.removeItem(KEY); }
            else                { window.localStorage.setItem(KEY, value); }
        } catch (err) { /* yok say */ }
    }

    function recall() {
        try { return window.localStorage.getItem(KEY); }
        catch (err) { return null; }
    }

    function close() {
        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        panels.forEach(function (p) { p.hidden = true; });
        body.hidden = true;
        document.body.classList.remove('rkdbg-open');
        remember(null);
    }

    function open(name) {
        var found = false;

        panels.forEach(function (p) {
            var mine = p.getAttribute('data-rkdbg-panel') === name;
            p.hidden = !mine;
            if (mine) { found = true; }
        });
        if (!found) { close(); return; }

        tabs.forEach(function (t) {
            t.classList.toggle('is-active', t.getAttribute('data-rkdbg-tab') === name);
        });
        body.hidden = false;
        document.body.classList.add('rkdbg-open');
        body.scrollTop = 0;
        remember(name);
    }

    root.addEventListener('click', function (ev) {
        var tab = ev.target.closest('[data-rkdbg-tab]');
        if (!tab) { return; }

        var name = tab.getAttribute('data-rkdbg-tab');
        if (tab.classList.contains('is-active')) { close(); }
        else                                     { open(name); }
    });

    /* Escape: panel kapanir. Uygulamanin kendi Escape davranisini
       (kenar cubugu) engellemez; ikisi farkli dinleyicidir. */
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !body.hidden) { close(); }
    });

    var last = recall();
    if (last) { open(last); }
}());
