<template>
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
        <!-- Header -->
        <div v-if="stock" class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4 flex-wrap">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">{{ stock.symbol }}</h1>
                    <p class="text-slate-400">{{ stock.name }}</p>
                </div>
                <div class="ml-auto text-right">
                    <div v-if="stock.current_price">
                        <div class="text-3xl font-semibold text-slate-900">{{ fmt(stock.current_price) }}</div>
                        <div v-if="stock.change_percent" class="text-sm font-medium mt-0.5" :class="stock.change_percent >= 0 ? 'text-emerald-600' : 'text-red-500'">
                            {{ stock.change_percent >= 0 ? '+' : '' }}{{ Number(stock.change_percent).toFixed(2) }}%
                        </div>
                    </div>
                    <button @click="refreshPrice" :disabled="refreshing"
                        class="mt-2 px-3 py-1 text-xs bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-600 rounded-lg transition-colors">
                        {{ refreshing ? 'Refreshing…' : '↻ Refresh price' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Price chart -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Price History</h2>
            <div v-if="priceHistory.length === 0" class="text-slate-400 text-sm py-8 text-center">
                No price history yet. Trigger a price fetch to start recording.
            </div>
            <canvas v-else ref="chartCanvas" height="120"></canvas>
        </div>

        <!-- Add transaction -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Record Transaction</h2>
            <form @submit.prevent="submitTransaction" class="flex flex-wrap gap-3 items-end">
                <!-- Type -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Type</label>
                    <select v-model="txForm.type" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="buy">Buy</option>
                        <option value="sell">Sell</option>
                    </select>
                </div>
                <!-- Shares -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Shares</label>
                    <input v-model="txForm.shares" type="number" step="1" min="1" required placeholder="1000"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="txErrors.shares" class="text-xs text-red-500 mt-1">{{ txErrors.shares[0] }}</p>
                </div>
                <!-- Price -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Price / Share</label>
                    <input v-model="txForm.price_per_share" type="number" step="0.01" min="0.01" required placeholder="850.00"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="txErrors.price_per_share" class="text-xs text-red-500 mt-1">{{ txErrors.price_per_share[0] }}</p>
                </div>
                <!-- Date -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Date</label>
                    <input v-model="txForm.transacted_at" type="date" required
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <!-- Handling fee -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Handling Fee</label>
                    <input v-model="txForm.handling_fee" type="number" step="1" min="0" placeholder="0"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <!-- Transaction tax -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Transaction Tax</label>
                    <input v-model="txForm.transaction_tax" type="number" step="1" min="0" placeholder="0"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <!-- Notes -->
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Notes (optional)</label>
                    <input v-model="txForm.notes" type="text" placeholder="optional"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <!-- Total preview -->
                <div class="text-sm text-slate-500 self-center">
                    Total: <span class="font-semibold text-slate-800">{{ txTotal }}</span>
                </div>
                <button type="submit" :disabled="submitting"
                    :class="txForm.type === 'sell' ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-600 hover:bg-emerald-700'"
                    class="px-5 py-2 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors capitalize"
                >
                    {{ submitting ? '…' : txForm.type }}
                </button>
            </form>
            <p v-if="txError" class="mt-2 text-sm text-red-500">{{ txError }}</p>
        </div>

        <!-- Transaction history -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide">My Transactions</h2>
            </div>
            <div v-if="transactions.length === 0" class="text-center py-8 text-slate-400 text-sm">No transactions yet.</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-500">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-500">Type</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Shares</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Price</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Fee</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Tax</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600">{{ tx.transacted_at }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="tx.type === 'buy' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'">
                                {{ tx.type.toUpperCase() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ Number(tx.shares).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ fmt(tx.price_per_share) }}</td>
                        <td class="px-4 py-3 text-right text-slate-500">{{ Number(tx.handling_fee) > 0 ? fmt(tx.handling_fee) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-slate-500">{{ Number(tx.transaction_tax) > 0 ? fmt(tx.transaction_tax) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">{{ fmt(tx.shares * tx.price_per_share) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button @click="deleteTransaction(tx.id)" class="text-slate-300 hover:text-red-400 transition-colors">✕</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import { Chart, LineElement, PointElement, LinearScale, TimeScale, Filler, Tooltip, CategoryScale } from 'chart.js';

Chart.register(LineElement, PointElement, LinearScale, Filler, Tooltip, CategoryScale);

const route = useRoute();
const symbol = computed(() => route.params.symbol);

const stock = ref(null);
const priceHistory = ref([]);
const transactions = ref([]);
const chartCanvas = ref(null);
let chartInstance = null;

const txForm = ref({ type: 'buy', shares: '', price_per_share: '', handling_fee: 0, transaction_tax: 0, transacted_at: today(), notes: '' });
const txErrors = ref({});
const txError = ref('');
const submitting = ref(false);
const refreshing = ref(false);

const txTotal = computed(() => {
    const s = Number(txForm.value.shares);
    const p = Number(txForm.value.price_per_share);
    if (!s || !p) return '—';
    return (s * p).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    if (!chartCanvas.value || priceHistory.value.length === 0) return;
    chartInstance?.destroy();

    const labels = priceHistory.value.map(p => p.date);
    const prices = priceHistory.value.map(p => Number(p.close_price));
    const rising = prices[prices.length - 1] >= prices[0];

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: prices,
                borderColor: rising ? '#059669' : '#ef4444',
                backgroundColor: rising ? 'rgba(5,150,105,0.08)' : 'rgba(239,68,68,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: priceHistory.value.length > 30 ? 0 : 3,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 11 } } },
                y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
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
    try {
        const { data } = await api.post('/transactions', { stock_id: stock.value.id, ...txForm.value });
        transactions.value.unshift(data);
        txForm.value = { type: 'buy', shares: '', price_per_share: '', handling_fee: 0, transaction_tax: 0, transacted_at: today(), notes: '' };
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
    await api.delete(`/transactions/${id}`);
    transactions.value = transactions.value.filter(t => t.id !== id);
}

onMounted(load);
watch(symbol, load);
</script>
