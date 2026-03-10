<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-6">Portfolio</h1>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                <p class="text-sm text-slate-500 dark:text-slate-400">Total Value</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ hidden ? '••••' : fmt(totalValue) }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                <p class="text-sm text-slate-500 dark:text-slate-400">Unrealized Gain</p>
                <p class="text-2xl font-bold mt-1" :class="totalUnrealized >= 0 ? 'text-emerald-600' : 'text-red-500'">
                    {{ hidden ? '••••' : (sign(totalUnrealized) + fmt(Math.abs(totalUnrealized))) }}
                </p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                <p class="text-sm text-slate-500 dark:text-slate-400">Gain / Loss %</p>
                <p class="text-2xl font-bold mt-1" :class="gainPct >= 0 ? 'text-emerald-600' : 'text-red-500'">
                    {{ sign(gainPct) }}{{ Math.abs(gainPct).toFixed(2) }}%
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12 text-slate-400">Loading portfolio…</div>

        <!-- Empty state -->
        <div v-else-if="positions.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
            <p class="text-slate-400 text-lg">No positions yet.</p>
            <RouterLink to="/stocks" class="mt-3 inline-block text-indigo-600 hover:underline text-sm">
                Add stocks and record transactions →
            </RouterLink>
        </div>

        <template v-else>
            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <!-- Allocation doughnut -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 flex flex-col">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Allocation</h2>
                    <div class="flex flex-1 items-center justify-center gap-6">
                        <div class="relative w-2/5 flex-shrink-0">
                            <canvas ref="donutCanvas"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <p class="text-xs text-slate-400">Total</p>
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ hidden ? '••••' : fmt(totalValue) }}</p>
                            </div>
                        </div>
                        <ul class="flex flex-col gap-2">
                            <li v-for="(pos, i) in positions" :key="pos.stock.symbol" class="flex items-center gap-2 text-sm">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${PALETTE[i % PALETTE.length]}`"></span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ pos.stock.symbol }}</span>
                                <span class="text-slate-400 ml-auto pl-2 flex-shrink-0">{{ allocationPct(pos) }}%</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Unrealized gain/loss bar -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Unrealized Gain / Loss</h2>
                    <canvas ref="barCanvas" height="160"></canvas>
                </div>
            </div>

            <!-- Portfolio value history line chart -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Portfolio Value</h2>
                    <div class="flex gap-1">
                        <button
                            v-for="p in PERIODS" :key="p"
                            @click="selectedPeriod = p"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors"
                            :class="selectedPeriod === p ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >{{ p }}</button>
                    </div>
                </div>
                <canvas ref="lineCanvas" height="140"></canvas>
            </div>

            <!-- Positions table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Stock</th>
                            <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Shares</th>
                            <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Avg Cost</th>
                            <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Current Price</th>
                            <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Value</th>
                            <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Unrealized</th>
                            <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Realized</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                        <tr
                            v-for="pos in positions"
                            :key="pos.stock.symbol"
                            class="hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors"
                            @click="$router.push(`/stocks/${pos.stock.symbol}`)"
                        >
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ pos.stock.symbol }}</div>
                                <div class="text-xs text-slate-400 dark:text-slate-500">{{ pos.stock.name }}</div>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ num(pos.net_shares) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ hidden ? '••••' : fmt(pos.average_cost) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="text-slate-900 dark:text-slate-100">{{ fmt(pos.stock.current_price) }}</div>
                                <div v-if="pos.stock.change_percent" class="text-xs" :class="pos.stock.change_percent >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                    {{ pos.stock.change_percent >= 0 ? '+' : '' }}{{ Number(pos.stock.change_percent).toFixed(2) }}%
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">{{ hidden ? '••••' : fmt(pos.current_value) }}</td>
                            <td class="px-4 py-3 text-right font-medium" :class="pos.unrealized_gain >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                {{ hidden ? '••••' : (sign(pos.unrealized_gain) + fmt(Math.abs(pos.unrealized_gain))) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium" :class="pos.realized_gain >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                {{ hidden ? '••••' : (sign(pos.realized_gain) + fmt(Math.abs(pos.realized_gain))) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { Chart, DoughnutController, BarController, LineController, ArcElement, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler, Legend } from 'chart.js';
import api from '../api';
import { useTheme } from '../stores/theme';
import { usePrivacy } from '../stores/privacy';

Chart.register(DoughnutController, BarController, LineController, ArcElement, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler, Legend);

const PALETTE = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];

const PERIODS = ['1M', '3M', '6M', '1Y', 'All'];

const positions = ref([]);
const loading = ref(true);
const donutCanvas = ref(null);
const barCanvas = ref(null);
const lineCanvas = ref(null);
const portfolioHistory = ref([]);
const selectedPeriod = ref('1Y');
let donutChart = null;
let barChart = null;
let lineChart = null;

const filteredHistory = computed(() => {
    const valid = portfolioHistory.value
        .map(p => ({ ...p, date: String(p.date).slice(0, 10) }))
        .filter(p => p.value > 0 && p.cost_basis > 0);
    if (selectedPeriod.value === 'All') return valid;
    const days = { '1M': 30, '3M': 90, '6M': 180, '1Y': 365 }[selectedPeriod.value];
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - days);
    const cutoffStr = cutoff.toISOString().slice(0, 10);
    return valid.filter(p => p.date >= cutoffStr);
});

const totalValue      = computed(() => positions.value.reduce((s, p) => s + Number(p.current_value),   0));
const totalUnrealized = computed(() => positions.value.reduce((s, p) => s + Number(p.unrealized_gain), 0));
const totalCostBasis  = computed(() => positions.value.reduce((s, p) => s + Number(p.average_cost) * Number(p.net_shares), 0));
const gainPct         = computed(() => totalCostBasis.value > 0 ? (totalUnrealized.value / totalCostBasis.value) * 100 : 0);

const { hidden } = usePrivacy();

function chartColors() {
    const dark = document.documentElement.classList.contains('dark');
    return {
        grid: dark ? '#334155' : '#f1f5f9',
        border: dark ? '#334155' : '#e2e8f0',
        tick: '#94a3b8',
    };
}

function allocationPct(pos) {
    if (!totalValue.value) return '0.0';
    return ((Number(pos.current_value) / totalValue.value) * 100).toFixed(1);
}

function fmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function num(v) { return Number(v).toLocaleString(); }
function sign(v) { return Number(v) >= 0 ? '+' : '-'; }

function renderLineChart() {
    lineChart?.destroy();
    const history = filteredHistory.value;
    if (!lineCanvas.value || history.length === 0) return;

    lineChart = new Chart(lineCanvas.value, {
        type: 'line',
        data: {
            labels: history.map(p => p.date),
            datasets: [
                {
                    label: 'Portfolio Value',
                    data: history.map(p => p.value),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#6366f1',
                    pointBorderWidth: 0,
                    borderWidth: 2,
                },
                {
                    label: 'Cost Basis',
                    data: history.map(p => p.cost_basis),
                    borderColor: '#f59e0b',
                    borderWidth: 1.5,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    tension: 0,
                },
            ],
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 24,
                        boxHeight: 2,
                        padding: 12,
                        font: { size: 11 },
                        color: '#94a3b8',
                    },
                },
                tooltip: {
                    callbacks: {
                        title: (items) => items[0].label,
                        label: (item) => {
                            if (hidden.value) return item.datasetIndex === 0 ? [' Portfolio:  ••••', ' Gain/Loss: ••••'] : ' Cost Basis: ••••';
                            if (item.datasetIndex === 1) {
                                return ` Cost Basis: ${fmt(item.raw)}`;
                            }
                            const p = history[item.dataIndex];
                            const gain = p.value - p.cost_basis;
                            const pct = p.cost_basis > 0 ? (gain / p.cost_basis) * 100 : 0;
                            const sign = gain >= 0 ? '+' : '';
                            return [
                                ` Portfolio:  ${fmt(p.value)}`,
                                ` Gain/Loss: ${sign}${fmt(gain)}  (${sign}${pct.toFixed(2)}%)`,
                            ];
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: chartColors().border },
                    ticks: {
                        font: { size: 11 }, color: '#94a3b8',
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        callback(_, index) {
                            return String(this.getLabelForValue(index)).slice(0, 10);
                        },
                    },
                },
                y: {
                    grid: { color: chartColors().grid },
                    border: { color: chartColors().border },
                    ticks: {
                        font: { size: 11 }, color: '#94a3b8',
                        callback(value) {
                            if (Math.abs(value) >= 1_000_000) return (value / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
                            if (Math.abs(value) >= 1_000) return (value / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
                            return value;
                        },
                    },
                },
            },
        },
    });
}

function renderCharts() {
    const pos = positions.value;

    donutChart?.destroy();
    if (donutCanvas.value && pos.length > 0) {
        donutChart = new Chart(donutCanvas.value, {
            type: 'doughnut',
            data: {
                labels: pos.map(p => p.stock.symbol),
                datasets: [{
                    data: pos.map(p => Number(p.current_value)),
                    backgroundColor: pos.map((_, i) => PALETTE[i % PALETTE.length]),
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) => {
                                if (hidden.value) return ' ••••';
                                const total = item.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : 0;
                                return ` ${fmt(item.raw)}  (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    barChart?.destroy();
    if (barCanvas.value && pos.length > 0) {
        const gains = pos.map(p => Number(p.unrealized_gain));
        const pcts = pos.map(p => {
            const cost = Number(p.average_cost) * Number(p.net_shares);
            return cost > 0 ? (Number(p.unrealized_gain) / cost) * 100 : 0;
        });

        const pctLabelPlugin = {
            id: 'pctLabels',
            afterDraw(chart) {
                const { ctx } = chart;
                const zeroX = chart.scales.x.getPixelForValue(0);
                ctx.save();
                ctx.font = 'bold 11px sans-serif';
                ctx.textBaseline = 'middle';
                ctx.textAlign = 'left';
                chart.getDatasetMeta(0).data.forEach((bar, i) => {
                    const pct = pcts[i];
                    ctx.fillStyle = gains[i] >= 0 ? '#10b981' : '#ef4444';
                    const xPos = Math.max(bar.x, zeroX) + 5;
                    ctx.fillText(`${pct >= 0 ? '+' : ''}${pct.toFixed(2)}%`, xPos, bar.y);
                });
                ctx.restore();
            },
        };

        barChart = new Chart(barCanvas.value, {
            type: 'bar',
            data: {
                labels: pos.map(p => p.stock.symbol),
                datasets: [{
                    data: gains,
                    backgroundColor: gains.map(g => g >= 0 ? '#10b981' : '#ef4444'),
                    borderRadius: 4,
                    barThickness: 18,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                layout: { padding: { left: 0, right: 52 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) => hidden.value ? ' ••••' : ` ${sign(item.raw)}${fmt(Math.abs(item.raw))}  (${pcts[item.dataIndex] >= 0 ? '+' : ''}${pcts[item.dataIndex].toFixed(2)}%)`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { color: chartColors().grid },
                        border: { color: chartColors().border },
                        ticks: {
                            font: { size: 11 }, color: '#94a3b8',
                            callback(v) {
                                if (Math.abs(v) >= 1_000_000) return (v / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
                                if (Math.abs(v) >= 1_000) return (v / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
                                return v;
                            },
                        },
                    },
                    y: {
                        grid: { display: false },
                        border: { color: chartColors().border },
                        ticks: { font: { size: 11 }, color: '#94a3b8' },
                    },
                },
            },
            plugins: [pctLabelPlugin],
        });
    }
}

const { dark } = useTheme();
watch(selectedPeriod, () => nextTick(renderLineChart));
watch(dark, () => nextTick(() => { renderCharts(); renderLineChart(); }));

onMounted(async () => {
    try {
        const [portfolio, history] = await Promise.all([
            api.get('/stocks/portfolio'),
            api.get('/stocks/portfolio/history'),
        ]);
        positions.value = portfolio.data;
        portfolioHistory.value = history.data;
    } finally {
        loading.value = false;
    }
    await nextTick();
    renderCharts();
    renderLineChart();
});
</script>
