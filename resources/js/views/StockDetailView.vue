<template>
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
        <!-- Header -->
        <div v-if="stock" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4 flex-wrap">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ stock.symbol }}</h1>
                    <p class="text-slate-400 dark:text-slate-500">{{ stock.name }}</p>
                </div>
                <div class="ml-auto text-right">
                    <div v-if="stock.current_price">
                        <div class="text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ fmt(stock.current_price) }}</div>
                        <div v-if="stock.change_percent" class="text-sm font-medium mt-0.5" :class="stock.change_percent >= 0 ? 'text-emerald-600' : 'text-red-500'">
                            {{ stock.change_percent >= 0 ? '+' : '' }}{{ Number(stock.change_percent).toFixed(2) }}%
                        </div>
                    </div>
                    <button @click="refreshPrice" :disabled="refreshing"
                        class="mt-2 px-3 py-1 text-xs bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 disabled:opacity-50 text-slate-600 dark:text-slate-300 rounded-lg transition-colors">
                        {{ refreshing ? 'Refreshing…' : '↻ Refresh price' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Price chart -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Price History</h2>
                <div v-if="priceHistory.length > 0" class="flex gap-1">
                    <button v-for="p in PERIODS" :key="p" @click="selectedPeriod = p"
                        :class="selectedPeriod === p
                            ? 'bg-indigo-600 text-white'
                            : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-600'"
                        class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors">
                        {{ p }}
                    </button>
                </div>
            </div>

            <div v-if="priceHistory.length === 0" class="text-slate-400 dark:text-slate-500 text-sm py-8 text-center">
                No price history yet. Trigger a price fetch to start recording.
            </div>
            <div v-else>
                <canvas ref="chartCanvas" height="110"></canvas>
                <div class="flex items-center gap-4 mt-3 text-xs text-slate-400 dark:text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-0 h-0 border-l-4 border-r-4 border-b-[7px] border-l-transparent border-r-transparent border-b-emerald-500"></span>
                        Buy
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-0 h-0 border-l-4 border-r-4 border-t-[7px] border-l-transparent border-r-transparent border-t-red-500"></span>
                        Sell
                    </span>
                </div>
            </div>
        </div>

        <!-- Add transaction -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Record Transaction</h2>
            <form @submit.prevent="submitTransaction" class="flex flex-wrap gap-3 items-end">
                <!-- Type -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Type</label>
                    <select v-model="txForm.type" class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="buy">Buy</option>
                        <option value="sell">Sell</option>
                    </select>
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <!-- Shares -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Shares</label>
                    <input v-model="txForm.shares" type="number" step="1" min="1" required placeholder="1000"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ txErrors.shares?.[0] ?? '' }}</p>
                </div>
                <!-- Price -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Price / Share</label>
                    <input v-model="txForm.price_per_share" type="number" step="0.01" min="0.01" required placeholder="850.00"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ txErrors.price_per_share?.[0] ?? '' }}</p>
                </div>
                <!-- Date -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Date</label>
                    <input v-model="txForm.transacted_at" type="date" required
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <!-- Handling fee (auto-calculated, overwritable) -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Handling Fee ({{ feeRates.handling }}%)</label>
                    <input v-model="txForm.handling_fee" type="number" step="0.01" min="0"
                        class="h-9 w-40 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <!-- Transaction tax (auto-calculated, overwritable) -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Transaction Tax ({{ feeRates.tax }}%)</label>
                    <input v-model="txForm.transaction_tax" type="number" step="0.01" min="0"
                        class="h-9 w-40 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <!-- Notes -->
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Notes (optional)</label>
                    <input v-model="txForm.notes" type="text" placeholder="optional"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <!-- Total preview -->
                <div>
                    <label class="block text-xs mb-1 invisible">_</label>
                    <div class="h-9 flex items-center text-sm text-slate-500 dark:text-slate-400 px-1">
                        Total: <span class="ml-1 font-semibold text-slate-800 dark:text-slate-200">{{ hidden ? '••••' : txTotal }}</span>
                    </div>
                    <p class="h-[1.1rem]"></p>
                </div>
                <!-- Submit -->
                <div>
                    <label class="block text-xs mb-1 invisible">_</label>
                    <button type="submit" :disabled="submitting"
                        :class="txForm.type === 'sell' ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-600 hover:bg-emerald-700'"
                        class="h-9 px-5 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors capitalize"
                    >
                        {{ submitting ? '…' : txForm.type }}
                    </button>
                    <p class="h-[1.1rem]"></p>
                </div>
            </form>
            <p v-if="txError" class="mt-2 text-sm text-red-500">{{ txError }}</p>
        </div>

        <!-- Transaction history -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">My Transactions</h2>
            </div>
            <div v-if="transactions.length === 0" class="text-center py-8 text-slate-400 dark:text-slate-500 text-sm">No transactions yet.</div>
            <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Type</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Shares</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Price</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Fee</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Tax</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                    <template v-for="tx in transactions" :key="tx.id">
                        <!-- Edit row -->
                        <tr v-if="editingId === tx.id" class="bg-slate-50 dark:bg-slate-700/50">
                            <td colspan="8" class="px-4 py-3">
                                <div class="flex flex-wrap gap-2 items-end">
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Type</label>
                                        <select v-model="editForm.type" class="h-8 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <option value="buy">Buy</option>
                                            <option value="sell">Sell</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Shares</label>
                                        <input v-model="editForm.shares" type="number" step="1" min="1"
                                            class="h-8 w-24 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Price</label>
                                        <input v-model="editForm.price_per_share" type="number" step="0.01" min="0.01"
                                            class="h-8 w-28 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Handling Fee</label>
                                        <input v-model="editForm.handling_fee" type="number" step="0.01" min="0"
                                            class="h-8 w-24 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Tax</label>
                                        <input v-model="editForm.transaction_tax" type="number" step="0.01" min="0"
                                            class="h-8 w-24 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Date</label>
                                        <input v-model="editForm.transacted_at" type="date"
                                            class="h-8 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div class="ml-auto flex gap-2 items-end">
                                        <button @click="saveEdit(tx.id)" :disabled="saving" class="h-8 px-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
                                            {{ saving ? '…' : 'Save' }}
                                        </button>
                                        <button @click="editingId = null" class="h-8 px-3 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-lg">Cancel</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Display row -->
                        <tr v-else class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ tx.transacted_at }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="tx.type === 'buy' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400'">
                                    {{ tx.type.toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ Number(tx.shares).toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ hidden ? '••••' : fmt(tx.price_per_share) }}</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ hidden ? '••••' : (Number(tx.handling_fee) > 0 ? fmt(tx.handling_fee) : '—') }}</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ hidden ? '••••' : (Number(tx.transaction_tax) > 0 ? fmt(tx.transaction_tax) : '—') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                {{ hidden ? '••••' : (tx.type === 'buy'
                                    ? fmt(tx.shares * tx.price_per_share + Number(tx.handling_fee))
                                    : fmt(tx.shares * tx.price_per_share - Number(tx.handling_fee) - Number(tx.transaction_tax))) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button @click="startEdit(tx)" class="text-slate-300 dark:text-slate-600 hover:text-indigo-400 transition-colors mr-2">✎</button>
                                <button @click="deleteTransaction(tx.id)" class="text-slate-300 dark:text-slate-600 hover:text-red-400 transition-colors">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, watchEffect, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import { useAuth } from '../stores/auth';
import { usePrivacy } from '../stores/privacy';
import { Chart, LineController, LineElement, PointElement, LinearScale, Filler, Tooltip, CategoryScale } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, Filler, Tooltip, CategoryScale);

const PERIODS = ['1M', '3M', '6M', '1Y', 'All'];

const { hidden } = usePrivacy();
const route = useRoute();
const symbol = computed(() => route.params.symbol);

const stock = ref(null);
const priceHistory = ref([]);
const transactions = ref([]);
const chartCanvas = ref(null);
const selectedPeriod = ref('3M');
let chartInstance = null;

const filteredHistory = computed(() => {
    const valid = priceHistory.value.filter(p => Number(p.close_price) > 0);
    if (selectedPeriod.value === 'All') return valid;
    const days = { '1M': 30, '3M': 90, '6M': 180, '1Y': 365 }[selectedPeriod.value];
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - days);
    const cutoffStr = cutoff.toISOString().slice(0, 10);
    return valid.filter(p => p.date >= cutoffStr);
});

const { state: authState } = useAuth();

const STANDARD_HANDLING_RATE = 0.1425; // %
// Effective rate accounts for the user's broker discount stored in their profile.
const feeRates = computed(() => {
    const discount = Number(authState.user?.handling_fee_discount ?? 0);
    return {
        handling: +(STANDARD_HANDLING_RATE * (1 - discount)).toFixed(4),
        tax: 0.3,
    };
});

const txForm = ref({ type: 'buy', shares: '', price_per_share: '', handling_fee: 0, transaction_tax: 0, transacted_at: today(), notes: '' });
const txErrors = ref({});
const txError = ref('');
const submitting = ref(false);
const refreshing = ref(false);
const editingId = ref(null);
const editForm = ref({});
const saving = ref(false);

// Auto-calculate fees whenever trade value or rates change.
// feeRates is destructured BEFORE the early return so Vue always tracks it
// as a reactive dependency — even when no trade value has been entered yet.
watchEffect(() => {
    const { handling: handlingRate, tax: taxRate } = feeRates.value;
    const tradeValue = Number(txForm.value.shares) * Number(txForm.value.price_per_share);
    if (!tradeValue) return;
    txForm.value.handling_fee = Math.max(20, Math.floor(tradeValue * handlingRate / 100));
    txForm.value.transaction_tax = txForm.value.type === 'sell'
        ? Math.floor(tradeValue * taxRate / 100)
        : 0;
});

const txTotal = computed(() => {
    const s = Number(txForm.value.shares);
    const p = Number(txForm.value.price_per_share);
    if (!s || !p) return '—';
    const base = s * p;
    const fee = Number(txForm.value.handling_fee);
    const tax = Number(txForm.value.transaction_tax);
    const total = txForm.value.type === 'buy' ? base + fee : base - fee - tax;
    return total.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});

function today() {
    return new Date().toISOString().slice(0, 10);
}

function fmt(v) {
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function load() {
    const [stockRes, historyRes, txRes] = await Promise.all([
        api.get(`/stocks/${symbol.value}`),
        api.get(`/stocks/${symbol.value}/prices`),
        api.get(`/stocks/${symbol.value}/transactions`),
    ]);
    stock.value = stockRes.data;
    priceHistory.value = historyRes.data;
    transactions.value = txRes.data;
    await nextTick();
    renderChart();
}

function renderChart() {
    if (!chartCanvas.value || filteredHistory.value.length === 0) return;
    chartInstance?.destroy();

    const history = filteredHistory.value;
    const labels = history.map(p => p.date);
    const prices = history.map(p => Number(p.close_price));
    const rising = prices[prices.length - 1] >= prices[0];
    const color = rising ? '#059669' : '#ef4444';

    // Overlay buy/sell markers at the close price on each transaction date.
    const buyData = labels.map((date, i) =>
        transactions.value.some(t => t.transacted_at === date && t.type === 'buy') ? prices[i] : null
    );
    const sellData = labels.map((date, i) =>
        transactions.value.some(t => t.transacted_at === date && t.type === 'sell') ? prices[i] : null
    );

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data: prices,
                    borderColor: color,
                    backgroundColor: rising ? 'rgba(5,150,105,0.08)' : 'rgba(239,68,68,0.08)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                    order: 2,
                },
                {
                    data: buyData,
                    borderColor: '#059669',
                    backgroundColor: '#059669',
                    pointStyle: 'triangle',
                    pointRadius: 7,
                    pointHoverRadius: 9,
                    pointRotation: 0,
                    showLine: false,
                    order: 1,
                },
                {
                    data: sellData,
                    borderColor: '#ef4444',
                    backgroundColor: '#ef4444',
                    pointStyle: 'triangle',
                    pointRadius: 7,
                    pointHoverRadius: 9,
                    pointRotation: 180,
                    showLine: false,
                    order: 1,
                },
            ],
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: ([item]) => item?.label ?? '',
                        label: (item) => {
                            if (item.raw === null) return null;
                            if (item.datasetIndex === 0) return ` Close  ${fmt(item.raw)}`;
                            if (item.datasetIndex === 1) return ` Buy ▲  ${fmt(item.raw)}`;
                            return ` Sell ▼  ${fmt(item.raw)}`;
                        },
                        filter: (item) => item.raw !== null,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxTicksLimit: 8, font: { size: 11 }, color: '#94a3b8' },
                },
                y: {
                    position: 'right',
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { size: 11 }, color: '#94a3b8' },
                },
            },
        },
    });
}

async function refreshPrice() {
    refreshing.value = true;
    try {
        const { data } = await api.post(`/stocks/${symbol.value}/fetch`);
        stock.value = data;
        // Reload price history and re-render chart
        const { data: history } = await api.get(`/stocks/${symbol.value}/prices`);
        priceHistory.value = history;
        await nextTick();
        renderChart();
    } finally {
        refreshing.value = false;
    }
}

async function submitTransaction() {
    txErrors.value = {};
    txError.value = '';
    submitting.value = true;
    // Recalculate fees synchronously before posting in case watchEffect hasn't flushed yet.
    const tradeValue = Number(txForm.value.shares) * Number(txForm.value.price_per_share);
    if (tradeValue > 0) {
        const { handling: handlingRate, tax: taxRate } = feeRates.value;
        txForm.value.handling_fee = Math.max(20, Math.floor(tradeValue * handlingRate / 100));
        txForm.value.transaction_tax = txForm.value.type === 'sell'
            ? Math.floor(tradeValue * taxRate / 100)
            : 0;
    }
    try {
        const { data } = await api.post('/stocks/transactions', { stock_id: stock.value.id, ...txForm.value });
        transactions.value.unshift(data);
        txForm.value = { type: txForm.value.type, shares: '', price_per_share: '', handling_fee: 0, transaction_tax: 0, transacted_at: today(), notes: '' };
    } catch (e) {
        if (e.response?.status === 422) {
            txErrors.value = e.response.data.errors ?? {};
            txError.value = e.response.data.errors?.shares?.[0] ?? '';
        }
    } finally {
        submitting.value = false;
    }
}

async function deleteTransaction(id) {
    if (!confirm('Delete this transaction?')) return;
    await api.delete(`/stocks/transactions/${id}`);
    transactions.value = transactions.value.filter(t => t.id !== id);
}

function startEdit(tx) {
    editingId.value = tx.id;
    editForm.value = {
        type: tx.type,
        shares: tx.shares,
        price_per_share: tx.price_per_share,
        handling_fee: tx.handling_fee,
        transaction_tax: tx.transaction_tax,
        transacted_at: String(tx.transacted_at).slice(0, 10),
    };
}

async function saveEdit(id) {
    saving.value = true;
    try {
        const { data } = await api.put(`/stocks/transactions/${id}`, editForm.value);
        const idx = transactions.value.findIndex(t => t.id === id);
        if (idx !== -1) transactions.value[idx] = data;
        editingId.value = null;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
watch(symbol, load);
watch(selectedPeriod, () => nextTick(renderChart));
</script>
