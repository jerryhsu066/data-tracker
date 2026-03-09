<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Transactions</h1>
            <button
                @click="showForm = !showForm"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
                + Add Transaction
            </button>
        </div>

        <!-- Add transaction form -->
        <div v-if="showForm" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 mb-6">
            <form @submit.prevent="submit" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Stock</label>
                    <select v-model="form.stock_id" required class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>Select…</option>
                        <option v-for="s in stocks" :key="s.id" :value="s.id">{{ s.symbol }} — {{ s.name }}</option>
                    </select>
                    <p v-if="errors.stock_id" class="text-xs text-red-500 mt-1">{{ errors.stock_id[0] }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Type</label>
                    <select v-model="form.type" class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="buy">Buy</option>
                        <option value="sell">Sell</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Shares</label>
                    <input v-model="form.shares" type="number" step="1" min="1" required placeholder="1000"
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="errors.shares" class="text-xs text-red-500 mt-1">{{ errors.shares[0] }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Price / Share</label>
                    <input v-model="form.price_per_share" type="number" step="0.01" min="0.01" required placeholder="850.00"
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="errors.price_per_share" class="text-xs text-red-500 mt-1">{{ errors.price_per_share[0] }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Date</label>
                    <input v-model="form.transacted_at" type="date" required
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Notes</label>
                    <input v-model="form.notes" type="text" placeholder="optional"
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 py-2 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <button type="submit" :disabled="submitting"
                    :class="form.type === 'sell' ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-600 hover:bg-emerald-700'"
                    class="px-5 py-2 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors capitalize"
                >
                    {{ submitting ? '…' : form.type }}
                </button>
                <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">Cancel</button>
            </form>
        </div>

        <!-- Summary strip -->
        <div v-if="!loading" class="flex gap-4 mb-4 text-sm text-slate-500 dark:text-slate-400">
            <span>{{ transactions.length }} transactions</span>
            <span>·</span>
            <span class="text-emerald-600 font-medium">{{ buyCount }} buys</span>
            <span>·</span>
            <span class="text-red-500 font-medium">{{ sellCount }} sells</span>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <div v-else-if="transactions.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm text-slate-400">
            No transactions recorded yet.
        </div>

        <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Stock</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Type</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Shares</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Price</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Total</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                    <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ tx.transacted_at }}</td>
                        <td class="px-4 py-3">
                            <RouterLink :to="`/stocks/${tx.stock?.symbol}`" class="font-medium text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ tx.stock?.symbol }}
                            </RouterLink>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="tx.type === 'buy' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400'">
                                {{ tx.type.toUpperCase() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ Number(tx.shares).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ fmt(tx.price_per_share) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                            {{ tx.type === 'buy'
                                ? fmt(tx.shares * tx.price_per_share + Number(tx.handling_fee))
                                : fmt(tx.shares * tx.price_per_share - Number(tx.handling_fee) - Number(tx.transaction_tax)) }}
                        </td>
                        <td class="px-4 py-3 text-slate-400 dark:text-slate-500 max-w-xs truncate">{{ tx.notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button @click="del(tx.id)" class="text-slate-300 dark:text-slate-600 hover:text-red-400 transition-colors">✕</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api';

const transactions = ref([]);
const stocks = ref([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const errors = ref({});

const form = ref({ stock_id: '', type: 'buy', shares: '', price_per_share: '', transacted_at: today(), notes: '' });

const buyCount = computed(() => transactions.value.filter(t => t.type === 'buy').length);
const sellCount = computed(() => transactions.value.filter(t => t.type === 'sell').length);

function today() { return new Date().toISOString().slice(0, 10); }
function fmt(v) { return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

async function load() {
    try {
        const [txRes, stockRes] = await Promise.all([
            // Fetch transactions per stock and flatten — or we build a combined list from portfolio stocks
            api.get('/stocks'),
            api.get('/portfolio'),
        ]);
        stocks.value = txRes.data;
        // Collect all transactions from each tracked stock for this user
        const all = [];
        for (const s of txRes.data) {
            const { data } = await api.get(`/stocks/${s.symbol}/transactions`);
            all.push(...data);
        }
        transactions.value = all.sort((a, b) => b.transacted_at.localeCompare(a.transacted_at));
    } finally {
        loading.value = false;
    }
}

async function submit() {
    errors.value = {};
    submitting.value = true;
    try {
        const { data } = await api.post('/transactions', form.value);
        transactions.value.unshift(data);
        transactions.value.sort((a, b) => b.transacted_at.localeCompare(a.transacted_at));
        form.value = { stock_id: '', type: 'buy', shares: '', price_per_share: '', transacted_at: today(), notes: '' };
        showForm.value = false;
    } catch (e) {
        if (e.response?.status === 422) errors.value = e.response.data.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

async function del(id) {
    if (!confirm('Delete this transaction?')) return;
    await api.delete(`/transactions/${id}`);
    transactions.value = transactions.value.filter(t => t.id !== id);
}

onMounted(load);
</script>
