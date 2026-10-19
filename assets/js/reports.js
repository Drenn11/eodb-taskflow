/**
 * EODB-TaskFlow - Report charts (Chart.js)
 *
 * Each chart is a <canvas data-chart="ID"> with its data in
 * <script type="application/json" id="ID">{...}</script>, produced by PHP
 * from the database. Supported kinds: "doughnut" and "bar".
 *
 * Charts follow the Light / Dark Mode: they are drawn with the colours of the
 * current theme and redrawn when the theme changes (event from app.js).
 */
(function () {
    'use strict';

    if (typeof window.Chart === 'undefined') {
        // Chart library could not load (e.g. no internet): show the tables only.
        document.querySelectorAll('[data-chart]').forEach(function (canvas) {
            var note = document.createElement('p');
            note.className = 'small text-muted mb-0';
            note.textContent = 'Chart unavailable (the chart library could not be loaded). The figures are shown in the table.';
            canvas.replaceWith(note);
        });
        return;
    }

    // Neutral chart colours per theme (match the style.css design tokens)
    var THEMES = {
        light: { text: '#647083', grid: '#e2e7ee', axis: '#cfd6df', tooltip: '#202938', surface: '#ffffff' },
        dark:  { text: '#cbd5e1', grid: '#374151', axis: '#4b5563', tooltip: '#0b1220', surface: '#1f2937' }
    };

    // Status and priority colours come from PHP (STATUS_COLORS, PRIORITY_COLORS
    // in includes/report_functions.php). In Dark Mode each is replaced by a
    // lighter equivalent that stays visible on the dark surface.
    var DARK_SERIES = {
        '#6b778a': '#9ca3af',   // Pending
        '#2f65a0': '#60a5fa',   // In Progress
        '#287a55': '#4ade80',   // Completed
        '#b93838': '#f87171',   // Overdue / Urgent
        '#8a94a6': '#6b7280',   // Low priority
        '#3e5068': '#cbd5e1',   // Medium priority
        '#946010': '#fbbf24'    // High priority
    };

    Chart.defaults.font.family = '"Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
    Chart.defaults.plugins.tooltip.boxPadding = 4;

    function themeName() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function seriesColors(colors, theme) {
        if (theme !== 'dark') {
            return colors;
        }
        var map = function (c) {
            return DARK_SERIES[String(c).toLowerCase()] || c;
        };
        return Array.isArray(colors) ? colors.map(map) : map(colors);
    }

    function build(canvas, theme) {
        var source = document.getElementById(canvas.getAttribute('data-chart'));
        if (!source) {
            return null;
        }
        var spec = JSON.parse(source.textContent);
        var isBar = spec.kind === 'bar';
        var t = THEMES[theme];

        // The value axis is x for horizontal bars and y for vertical bars;
        // the category axis only gets a light layout (no grid lines).
        var valueAxis = { stacked: !!spec.stacked, beginAtZero: true, max: spec.max || undefined, ticks: { precision: 0, color: t.text }, grid: { color: t.grid }, border: { display: false } };
        var categoryAxis = { stacked: !!spec.stacked, ticks: { color: t.text }, grid: { display: false }, border: { color: t.axis } };

        return new Chart(canvas, {
            type: spec.kind,
            data: {
                labels: spec.labels,
                datasets: spec.datasets.map(function (set) {
                    var colors = seriesColors(set.colors, theme);
                    return {
                        label: set.label,
                        data: set.data,
                        backgroundColor: colors,
                        borderColor: isBar ? colors : t.surface,
                        borderWidth: isBar ? 0 : 2,
                        borderRadius: isBar ? 4 : 0,
                        maxBarThickness: 28,
                        hoverOffset: isBar ? 0 : 4
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                color: t.text,
                borderColor: t.grid,
                indexAxis: spec.horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: !spec.hideLegend, position: 'bottom', labels: { color: t.text, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14 } },
                    tooltip: {
                        backgroundColor: t.tooltip,
                        callbacks: spec.percentSuffix ? {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + ctx.formattedValue + '%';
                            }
                        } : {}
                    }
                },
                scales: isBar ? (spec.horizontal
                    ? { x: valueAxis, y: categoryAxis }
                    : { x: categoryAxis, y: valueAxis }) : {},
                cutout: spec.kind === 'doughnut' ? '68%' : undefined
            }
        });
    }

    var canvases = Array.prototype.slice.call(document.querySelectorAll('canvas[data-chart]'));
    var charts = [];

    function drawAll() {
        charts.forEach(function (chart) {
            if (chart) {
                chart.destroy();
            }
        });
        var theme = themeName();
        charts = canvases.map(function (canvas) {
            return build(canvas, theme);
        });
    }

    drawAll();
    document.addEventListener('eodb:themechange', drawAll);
})();
