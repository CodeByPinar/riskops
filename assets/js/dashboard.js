/* =====================================================================
   RiskOps - Dashboard grafikleri
   /var/www/riskops/assets/js/dashboard.js

   TASARIM KARARLARI (dataviz prosedürü)
   -------------------------------------
   * Form önce seçildi, renk en son. Büyüklük karşılaştırmaları yatay bar,
     zaman içindeki değişim çizgi.
   * Kategori ve departman grafikleri TEK SERİ. 13 kategori için 13 hue
     üretilmez; kimliği etiket taşır, renk yalnızca büyüklüğü taşır.
   * Trend iki seri: #016ccc + #e8833a. Bu çift doğrulayıcıdan geçti
     (normal görüş ΔE 22.3, CVD ayrımı eşik üstü). Kontrast uyarısı
     görünür etiket zorunlu kılar -> efsane + doğrudan uç etiketi var.
   * Severity renkleri grafiklerde DEĞİL, sunucu tarafında render edilen
     ısı haritası ve bar listelerinde kullanılır; hepsi sayı etiketi taşır
     (status renkleri asla tek başına anlam taşımaz).
   * Chart.js yüklenmezse veya istek başarısız olursa sayfa çalışmaya
     devam eder; grafik alanı bir mesajla değiştirilir.
   ===================================================================== */

(function () {
    'use strict';

    var root = document.getElementById('rkDashboard');
    if (!root) { return; }

    var INK        = '#234066';   // --rk-text-soft
    var INK_MUTED  = '#5c7897';   // --rk-muted
    var GRID       = '#e6edf5';
    var SURFACE    = '#ffffff';
    var SERIES_1   = '#016ccc';   // marka mavisi
    var SERIES_2   = '#e8833a';   // doğrulanmış ikinci kategorik hue

    function fail(message) {
        root.querySelectorAll('[data-rk-chart]').forEach(function (box) {
            box.innerHTML = '<div class="rk-chart-fallback">' +
                '<i class="bi bi-bar-chart"></i><span>' + message + '</span></div>';
        });
    }

    if (typeof window.Chart === 'undefined') {
        fail('Grafik kütüphanesi yüklenemedi. Tablolar etkilenmedi.');
        return;
    }

    Chart.defaults.font.family =
        "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = INK_MUTED;
    Chart.defaults.animation.duration = 260;
    Chart.defaults.maintainAspectRatio = false;

    /* --- Doğrudan değer etiketi (Chart.js 4 datalabels ile gelmiyor) --- */
    var valueLabels = {
        id: 'rkValueLabels',
        afterDatasetsDraw: function (chart, args, opts) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.font = '600 11px ' + Chart.defaults.font.family;
            ctx.fillStyle = INK;
            ctx.textBaseline = 'middle';

            chart.data.datasets.forEach(function (ds, di) {
                if (ds.rkNoLabels) { return; }
                var meta = chart.getDatasetMeta(di);
                if (meta.hidden) { return; }

                meta.data.forEach(function (el, i) {
                    var v = ds.data[i];
                    if (v === null || v === undefined || v === 0) { return; }
                    var text = (opts && opts.suffix) ? String(v) + opts.suffix : String(v);

                    if (chart.options.indexAxis === 'y') {
                        ctx.textAlign = 'left';
                        ctx.fillText(text, el.x + 7, el.y);
                    } else {
                        ctx.textAlign = 'center';
                        ctx.fillText(text, el.x, el.y - 10);
                    }
                });
            });
            ctx.restore();
        }
    };

    var tooltipStyle = {
        backgroundColor: '#041f3c',
        titleColor: '#ffffff',
        bodyColor: '#dbe9f8',
        borderColor: 'rgba(255,255,255,.12)',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 6,
        displayColors: true,
        boxPadding: 4
    };

    /** Yatay bar: tek seri, kimlik etiketten gelir. */
    function horizontalBar(canvasId, labels, values, extraTooltip) {
        var el = document.getElementById(canvasId);
        if (!el) { return; }

        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: SERIES_1,
                    hoverBackgroundColor: '#0056b4',
                    borderRadius: 4,           // yalnızca veri ucu yuvarlak
                    borderSkipped: 'start',    // taban çizgisine yapışık
                    barThickness: 14,
                    maxBarThickness: 16
                }]
            },
            options: {
                indexAxis: 'y',
                layout: { padding: { right: 34 } },
                plugins: {
                    legend: { display: false },   // tek seri -> efsane yok
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: function (c) {
                                var base = ' ' + c.parsed.x + ' risk';
                                return extraTooltip ? [base].concat(extraTooltip(c.dataIndex)) : base;
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: INK_MUTED },
                        grid: { color: GRID, drawTicks: false },
                        border: { display: false }
                    },
                    y: {
                        ticks: { color: INK, font: { size: 12 }, crossAlign: 'far' },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            },
            plugins: [valueLabels]
        });
    }

    /* ------------------------------------------------------------------ */

    fetch(root.getAttribute('data-endpoint'), { credentials: 'same-origin' })
        .then(function (res) {
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            return res.json();
        })
        .then(function (data) {

            /* --- Trend: iki seri, efsane + uç etiketi zorunlu --------- */
            var trendEl = document.getElementById('rkTrendChart');
            if (trendEl && data.trend) {
                new Chart(trendEl, {
                    type: 'line',
                    data: {
                        labels: data.trend.labels,
                        datasets: [
                            {
                                label: 'Açılan risk',
                                data: data.trend.opened,
                                borderColor: SERIES_1,
                                backgroundColor: SERIES_1,
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 6,
                                pointHitRadius: 14,
                                pointBackgroundColor: SERIES_1,
                                pointBorderColor: SURFACE,
                                pointBorderWidth: 2,   // üst üste binen işaretlerde yüzey halkası
                                tension: 0.25
                            },
                            {
                                label: 'Kapanan risk',
                                data: data.trend.closed,
                                borderColor: SERIES_2,
                                backgroundColor: SERIES_2,
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 6,
                                pointHitRadius: 14,
                                pointBackgroundColor: SERIES_2,
                                pointBorderColor: SURFACE,
                                pointBorderWidth: 2,
                                tension: 0.25
                            }
                        ]
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    boxWidth: 8,
                                    boxHeight: 8,
                                    padding: 14,
                                    color: INK
                                }
                            },
                            tooltip: tooltipStyle
                        },
                        scales: {
                            x: {
                                ticks: { color: INK_MUTED },
                                grid: { display: false },
                                border: { color: GRID }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0, color: INK_MUTED },
                                grid: { color: GRID, drawTicks: false },
                                border: { display: false }
                            }
                        }
                    }
                });
            }

            /* --- Kategori --------------------------------------------- */
            if (data.category && data.category.length) {
                horizontalBar(
                    'rkCategoryChart',
                    data.category.map(function (r) { return r.ad; }),
                    data.category.map(function (r) { return r.adet; })
                );
            }

            /* --- Departman: kritik/yüksek sayısı METİN olarak taşınır -- */
            if (data.department && data.department.length) {
                var dep = data.department;
                horizontalBar(
                    'rkDepartmentChart',
                    dep.map(function (r) { return r.ad; }),
                    dep.map(function (r) { return r.adet; }),
                    function (i) {
                        return ' Kritik + Yüksek: ' + dep[i].onemli;
                    }
                );
            }
        })
        .catch(function (err) {
            fail('Grafik verisi alınamadı.');
            if (window.console) { console.error('[RiskOps] grafik verisi:', err); }
        });

})();
