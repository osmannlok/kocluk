// public/assets/js/admin-dashboard.js
// Dashboard satış grafiği: veri canvas üzerindeki data-chart özniteliğinden okunur.
(() => {
    'use strict';

    const canvas = document.getElementById('salesChart');
    if (!canvas) {
        return;
    }

    const box = canvas.closest('.chart-box');
    const empty = document.getElementById('salesChartEmpty');

    const showEmpty = (text) => {
        if (box) {
            box.classList.add('d-none');
        }
        if (empty) {
            if (text) {
                empty.textContent = text;
            }
            empty.classList.remove('d-none');
        }
    };

    let data = {};
    try {
        data = JSON.parse(canvas.getAttribute('data-chart') || '{}');
    } catch (err) {
        data = {};
    }

    const labels = Array.isArray(data.labels) ? data.labels : [];
    // Tutarlar sunucudan "1234.50" string gelir; yalnızca gösterim için sayıya çevrilir.
    const revenue = (Array.isArray(data.revenue) ? data.revenue : []).map(Number);
    const orders = (Array.isArray(data.orders) ? data.orders : []).map(Number);

    const hasData = labels.length > 0 && (revenue.some((v) => v > 0) || orders.some((v) => v > 0));
    if (!hasData) {
        showEmpty();
        return;
    }
    if (typeof window.Chart === 'undefined') {
        showEmpty('Grafik yüklenemedi.');
        return;
    }

    const tl = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });

    new window.Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Ciro (₺)',
                    data: revenue,
                    backgroundColor: 'rgba(154, 74, 28, 0.75)',
                    borderRadius: 3,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Sipariş adedi',
                    data: orders,
                    borderColor: '#0d6efd',
                    backgroundColor: '#0d6efd',
                    tension: 0.25,
                    pointRadius: 3,
                    yAxisID: 'y1',
                    order: 1,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => (ctx.dataset.yAxisID === 'y'
                            ? ctx.dataset.label + ': ' + tl.format(ctx.parsed.y)
                            : ctx.dataset.label + ': ' + ctx.parsed.y),
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: { callback: (v) => tl.format(Number(v)) },
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { precision: 0 },
                },
            },
        },
    });
})();