<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Overview</h1>
            <div class="flex items-center gap-3">
                <span v-if="fetchedAgoLabel && !syncing" class="text-xs text-slate-400 dark:text-slate-500">
                    Prices: {{ fetchedAgoLabel }}
                </span>
                <button
                    @click="syncPrices"
                    :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    <span :class="syncing ? 'animate-spin' : ''">↻</span>
                    {{ syncing ? 'Syncing…' : 'Sync Prices' }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Market Indices -->
            <template v-if="indices.length > 0">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Market Indices</h2>
                    <div class="flex gap-1">
                        <button
                            v-for="p in PERIODS" :key="p"
                            @click="setIndexPeriod(p)"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors"
                            :class="indexPeriod === p
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >{{ p }}</button>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div
                        v-for="(idx, i) in indices"
                        :key="idx.stock.symbol"
                        class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-4"
                    >
                        <p class="font-semibold text-sm text-slate-900 dark:text-slate-100">{{ idx.stock.name }}</p>
                        <div class="flex items-baseline gap-2 mb-3">
                            <span class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ fmtPrice(idx.stock.current_price) }}</span>
                            <span v-if="idx.stock.change_percent" class="text-xs font-medium" :class="idx.stock.change_percent >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                {{ idx.stock.change_percent >= 0 ? '+' : '' }}{{ Number(idx.stock.change_percent).toFixed(2) }}%
                            </span>
                        </div>
                        <canvas :ref="el => { if (el) indexCanvases[i] = el }" height="80"></canvas>
                    </div>
                </div>
            </template>

            <!-- Portfolio summary -->
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Portfolio</h2>
                <RouterLink to="/dashboard" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Details →</RouterLink>
            </div>
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

            <!-- Exposure Bundles -->
            <template v-if="bundlesWithStats.length > 0">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Exposure Bundles</h2>
                    <RouterLink to="/exposure" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Manage →</RouterLink>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <RouterLink
                        v-for="b in bundlesWithStats"
                        :key="b.id"
                        to="/exposure"
                        class="block bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 hover:ring-2 hover:ring-indigo-400 dark:hover:ring-indigo-500 transition-all"
                    >
                        <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mb-4">{{ b.name }}</h3>

                        <div class="grid grid-cols-3 gap-x-4 mb-4">
                            <div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Exposure Rate</p>
                                <p class="text-2xl font-bold mt-1" :class="b.stats.exposureRatioPct > 100 ? 'text-amber-500' : 'text-indigo-500 dark:text-indigo-400'">
                                    {{ b.stats.exposureRatioPct.toFixed(1) }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Gain / Loss</p>
                                <p class="text-2xl font-bold mt-1" :class="b.stats.gainLossPct >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                    {{ b.stats.gainLossPct >= 0 ? '+' : '' }}{{ b.stats.gainLossPct.toFixed(2) }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Invest Value</p>
                                <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ hidden ? '••••' : fmt(b.stats.investValue) }}</p>
                            </div>
                        </div>

                        <!-- Allocation bar -->
                        <div v-if="b.stats.segments.length > 0" class="space-y-1">
                            <div class="flex h-10 rounded-lg overflow-hidden gap-px">
                                <div
                                    v-for="(seg, i) in b.stats.segments"
                                    :key="seg.label"
                                    class="flex flex-col items-center justify-center overflow-hidden"
                                    :style="`width:${seg.pct}%;background:${PALETTE[i % PALETTE.length]}`"
                                    :title="`${seg.label}: ${seg.pct.toFixed(1)}%`"
                                >
                                    <template v-if="seg.pct >= 8">
                                        <span class="text-sm font-bold text-white leading-none drop-shadow">{{ seg.label }}</span>
                                        <span class="text-sm font-semibold text-white leading-none mt-0.5 drop-shadow">{{ seg.pct.toFixed(0) }}%</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip } from 'chart.js';
import api from '../api';
import { useTheme } from '../stores/theme';
import { usePrivacy } from '../stores/privacy';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip);

const PERIODS = ['1M', '3M', '6M', '1Y', 'All'];
const { hidden } = usePrivacy();

const PALETTE = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];

const positions = ref([]);
const bundles   = ref([]);
const indices      = ref([]);
const indexPeriod  = ref('3M');
const loading       = ref(true);
const syncing       = ref(false);
const now           = ref(Date.now());
let indexCanvases = [];
let indexCharts = [];
let ticker = null;

// ── "Prices: X ago" label ────────────────────────────────────────────────────
const oldestFetchedAt = computed(() => {
    const timestamps = positions.value
        .map(p => p.stock.last_fetched_at)
        .filter(Boolean)
        .map(t => new Date(t).getTime());
    return timestamps.length ? Math.min(...timestamps) : null;
});

const fetchedAgoLabel = computed(() => {
    if (!oldestFetchedAt.value) return null;
    const diff = Math.floor((now.value - oldestFetchedAt.value) / 1000);
    if (diff < 60)    return `${diff}s ago`;
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
});

// ── Portfolio computeds ──────────────────────────────────────────────────────
const totalValue      = computed(() => positions.value.reduce((s, p) => s + Number(p.current_value), 0));
const totalUnrealized = computed(() => positions.value.reduce((s, p) => s + Number(p.unrealized_gain), 0));
const totalCostBasis  = computed(() => positions.value.reduce((s, p) => s + Number(p.average_cost) * Number(p.net_shares), 0));
const gainPct         = computed(() => totalCostBasis.value > 0 ? (totalUnrealized.value / totalCostBasis.value) * 100 : 0);

// ── Exposure bundle stats ────────────────────────────────────────────────────
function bundleStats(bundle) {
    const cash = bundle.cash || 0;
    const stockValue = bundle.entries.reduce((sum, e) => sum + Number(e.net_shares) * Number(e.stock.current_price), 0);
    const totalVal = stockValue + cash;
    const totalExposure = bundle.entries.reduce((sum, e) => {
        if (e.is_cash) return sum;
        return sum + Number(e.net_shares) * Number(e.stock.current_price) * Number(e.leverage);
    }, 0);
    const exposureRatioPct = totalVal > 0 ? (totalExposure / totalVal) * 100 : 0;
    const investValue = bundle.entries.reduce((sum, e) => {
        const pos = positions.value.find(p => p.stock.id === e.stock.id);
        const avgCost = pos ? Number(pos.average_cost) : Number(e.stock.current_price);
        return sum + Number(e.net_shares) * avgCost;
    }, 0) + cash;
    const gainLoss = totalVal - investValue;
    const gainLossPct = investValue > 0 ? (gainLoss / investValue) * 100 : 0;

    const cashProxyValue = bundle.entries.filter(e => e.is_cash).reduce((sum, e) => sum + Number(e.net_shares) * Number(e.stock.current_price), 0);
    const totalCash = cash + cashProxyValue;
    const barTotal = stockValue + cash;
    const segments = barTotal > 0
        ? bundle.entries.filter(e => !e.is_cash).map(e => ({
            label: `${Number(e.leverage)}×`,
            pct: (Number(e.net_shares) * Number(e.stock.current_price) / barTotal) * 100,
        }))
        : [];
    if (totalCash > 0 && barTotal > 0) segments.push({ label: 'Cash', pct: (totalCash / barTotal) * 100 });

    return { totalVal, totalExposure, exposureRatioPct, investValue, gainLoss, gainLossPct, segments };
}

const bundlesWithStats = computed(() => bundles.value.map(b => ({ ...b, stats: bundleStats(b) })));

// ── Index charts ─────────────────────────────────────────────────────────────
function filteredIndexHistory(idx) {
    const valid = idx.history.filter(p => Number(p.close_price) > 0);
    if (indexPeriod.value === 'All') return valid;
    const days = { '1M': 30, '3M': 90, '6M': 180, '1Y': 365 }[indexPeriod.value];
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - days);
    return valid.filter(p => p.date >= cutoff.toISOString().slice(0, 10));
}

function chartColors() {
    const dark = document.documentElement.classList.contains('dark');
    return { grid: dark ? '#334155' : '#f1f5f9', border: dark ? '#334155' : '#e2e8f0' };
}

function renderIndexCharts() {
    indexCharts.forEach(c => c?.destroy());
    indexCharts = [];

    indices.value.forEach((idx, i) => {
        const canvas = indexCanvases[i];
        if (!canvas) return;
        const history = filteredIndexHistory(idx);
        if (history.length === 0) return;

        const prices = history.map(p => Number(p.close_price));
        const rising = prices[prices.length - 1] >= prices[0];
        const color  = rising ? '#059669' : '#ef4444';
        const colors = chartColors();

        const syncLeavePlugin = {
            id: 'syncLeave',
            afterEvent(chart, { event }) {
                if (event.type === 'mouseout') {
                    indexCharts.forEach(c => {
                        if (!c) return;
                        c.setActiveElements([]);
                        c.tooltip.setActiveElements([], { x: 0, y: 0 });
                        c.update('none');
                    });
                }
            },
        };

        indexCharts[i] = new Chart(canvas, {
            type: 'line',
            plugins: [syncLeavePlugin],
            data: {
                labels: history.map(p => p.date),
                datasets: [{
                    data: prices,
                    borderColor: color,
                    backgroundColor: rising ? 'rgba(5,150,105,0.08)' : 'rgba(239,68,68,0.08)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                onHover(_, elements, chart) {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    indexCharts.forEach(c => {
                        if (!c || c === chart) return;
                        c.setActiveElements([{ datasetIndex: 0, index: idx }]);
                        c.tooltip.setActiveElements([{ datasetIndex: 0, index: idx }], { x: 0, y: 0 });
                        c.update('none');
                    });
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: ([item]) => item?.label ?? '',
                            label: (item) => ` ${fmtPrice(item.raw)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: colors.border },
                        afterBuildTicks(axis) {
                            const lastIdx = history.length - 1;
                            const picks = new Set([0]);
                            for (let k = 1; k <= 3; k++) {
                                picks.add(Math.round((k / 4) * lastIdx));
                            }
                            picks.add(lastIdx);
                            axis.ticks = [...picks].sort((a, b) => a - b).map(v => ({ value: v }));
                        },
                        ticks: {
                            font: { size: 10 },
                            color: '#94a3b8',
                            autoSkip: false,
                            maxRotation: 0,
                            callback(_, tickIndex, ticks) {
                                const label = history[ticks[tickIndex]?.value]?.date;
                                if (!label) return '';
                                const d = new Date(label + 'T00:00:00');
                                const period = indexPeriod.value;
                                if (period === '1Y' || period === 'All') return d.toLocaleDateString('en-US', { year: '2-digit', month: 'short' });
                                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            },
                        },
                    },
                    y: {
                        position: 'right',
                        grid: { color: colors.grid },
                        border: { color: colors.border },
                        ticks: { font: { size: 11 }, color: '#94a3b8' },
                    },
                },
            },
        });
    });
}

function setIndexPeriod(period) {
    indexPeriod.value = period;
}

watch(indexPeriod, () => nextTick(renderIndexCharts));

const { dark } = useTheme();
watch(dark, () => nextTick(renderIndexCharts));

// ── Helpers ──────────────────────────────────────────────────────────────────
// Full precision for index prices (they don't need k/M abbreviation)
function fmtPrice(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmt(v) {
    if (v == null) return '—';
    const n = Number(v);
    if (Math.abs(n) >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (Math.abs(n) >= 1_000) return (n / 1_000).toFixed(1) + 'k';
    return n.toFixed(1);
}
function sign(v) { return Number(v) >= 0 ? '+' : '-'; }

// ── Data loading ─────────────────────────────────────────────────────────────
async function loadIndices() {
    const stocksRes = await api.get('/stocks');
    const indexStocks = stocksRes.data.filter(s => s.symbol.startsWith('^'));
    if (indexStocks.length === 0) { indices.value = []; return; }

    const histories = await Promise.all(indexStocks.map(s => api.get(`/stocks/${s.symbol}/prices`)));
    indices.value = indexStocks.map((stock, i) => ({ stock, history: histories[i].data }));
}

async function loadData() {
    const [portfolio, bundlesRes] = await Promise.all([
        api.get('/stocks/portfolio'),
        api.get('/stocks/exposure/bundles'),
    ]);
    positions.value = portfolio.data;
    bundles.value   = bundlesRes.data;
}

async function syncPrices() {
    syncing.value = true;
    try {
        const symbols = [...new Set(positions.value.map(p => p.stock.symbol))];
        await Promise.all(symbols.map(sym => api.post(`/stocks/${sym}/fetch`)));
        await Promise.all([loadData(), loadIndices()]);
        await nextTick();
        renderIndexCharts();
    } finally {
        syncing.value = false;
    }
}

onMounted(async () => {
    try {
        await Promise.all([loadData(), loadIndices()]);
    } finally {
        loading.value = false;
    }
    await nextTick();
    renderIndexCharts();
    ticker = setInterval(() => { now.value = Date.now(); }, 30_000);
});

onUnmounted(() => clearInterval(ticker));
</script>
