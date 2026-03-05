/**
 * DomoGlass Pro - Module Charts (Chart.js)
 * Graphiques énergie en temps réel
 */

import { store } from './store.js';

const MAX_POINTS = 48; // 48 relevés = 24h si 1 point / 30 min

export const Charts = {
    energyChart: null,
    energyData:  [],

    // ─── Initialisation ───────────────────────────────────────────────────────

    init() {
        this.#initEnergyChart();
        this.#initResponsiveResize();
    },

    // ─── Graphique énergie ────────────────────────────────────────────────────

    #initEnergyChart() {
        const canvas = document.getElementById('energyChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Données initiales simulées (dernières 12h)
        const labels = Array.from({ length: 12 }, (_, i) => {
            const d = new Date();
            d.setHours(d.getHours() - (11 - i));
            return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        });

        const data = Array.from({ length: 12 }, () =>
            parseFloat((0.5 + Math.random() * 1.5).toFixed(2))
        );

        this.energyData = data;

        const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
        gradient.addColorStop(0, 'rgba(245, 158, 11, 0.4)');
        gradient.addColorStop(1, 'rgba(245, 158, 11, 0.02)');

        this.energyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Consommation (kW)',
                    data,
                    borderColor:     '#f59e0b',
                    backgroundColor: gradient,
                    borderWidth:     2.5,
                    pointRadius:     3,
                    pointHoverRadius:6,
                    pointBackgroundColor: '#f59e0b',
                    tension:         0.4,
                    fill:            true,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                animation: {
                    duration: 400,
                    easing:   'easeInOutQuart',
                },
                interaction: {
                    intersect: false,
                    mode:      'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.9)',
                        titleColor:      '#f8fafc',
                        bodyColor:       '#94a3b8',
                        borderColor:     'rgba(148,163,184,0.2)',
                        borderWidth:     1,
                        padding:         10,
                        callbacks: {
                            label: (ctx) => ` ${ctx.parsed.y.toFixed(2)} kW`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(148,163,184,0.08)',
                            drawBorder: false,
                        },
                        ticks: {
                            color:     '#64748b',
                            maxTicksLimit: 6,
                            font: { size: 10 },
                        },
                    },
                    y: {
                        min: 0,
                        grid: {
                            color: 'rgba(148,163,184,0.08)',
                            drawBorder: false,
                        },
                        ticks: {
                            color:     '#64748b',
                            font: { size: 10 },
                            callback: (v) => v.toFixed(1) + ' kW',
                        },
                    },
                },
            },
        });
    },

    // ─── Ajout d'un point en temps réel ──────────────────────────────────────

    addEnergyDataPoint(powerWatts, timestamp = null) {
        if (!this.energyChart) return;

        const label = timestamp
            ? new Date(timestamp).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
            : new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        const kw = parseFloat((powerWatts / 1000).toFixed(2));

        this.energyChart.data.labels.push(label);
        this.energyChart.data.datasets[0].data.push(kw);

        // Limite le nombre de points
        if (this.energyChart.data.labels.length > MAX_POINTS) {
            this.energyChart.data.labels.shift();
            this.energyChart.data.datasets[0].data.shift();
        }

        // Met à jour le min/max dynamique
        const data = this.energyChart.data.datasets[0].data;
        const max  = Math.max(...data);
        this.energyChart.options.scales.y.max = Math.ceil(max * 1.2 * 10) / 10;

        this.energyChart.update('none'); // 'none' = pas d'animation pour le temps réel
    },

    // ─── Graphique historique (7 jours) ──────────────────────────────────────

    initWeekChart(canvasId, weeklyData) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: weeklyData.map(d => d.label),
                datasets: [{
                    label: 'kWh / jour',
                    data:  weeklyData.map(d => d.kwh),
                    backgroundColor: weeklyData.map((_, i) =>
                        i === weeklyData.length - 1
                            ? 'rgba(245, 158, 11, 0.8)'
                            : 'rgba(245, 158, 11, 0.3)'
                    ),
                    borderRadius: 6,
                    borderWidth:  0,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid:  { display: false },
                        ticks: { color: '#64748b', font: { size: 10 } },
                    },
                    y: {
                        grid:  { color: 'rgba(148,163,184,0.08)' },
                        ticks: { color: '#64748b', font: { size: 10 } },
                    },
                },
            },
        });
    },

    // ─── Responsive ──────────────────────────────────────────────────────────

    #initResponsiveResize() {
        const observer = new ResizeObserver(() => {
            this.energyChart?.resize();
        });

        const container = document.querySelector('.chart-container');
        if (container) observer.observe(container);
    },

    // ─── Destruction ─────────────────────────────────────────────────────────

    destroy() {
        this.energyChart?.destroy();
        this.energyChart = null;
    },
};
