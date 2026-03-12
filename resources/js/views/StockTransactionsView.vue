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
                <div class="basis-full">
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Stock</label>
                    <select v-model="form.stock_id" required class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>Select…</option>
                        <option v-for="s in stocks" :key="s.id" :value="s.id">{{ s.symbol }} — {{ s.name }}</option>
                    </select>
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.stock_id?.[0] ?? '' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Type</label>
                    <select v-model="form.type" class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="buy">Buy</option>
                        <option value="sell">Sell</option>
                    </select>
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Shares</label>
                    <input v-model="form.shares" type="number" step="1" min="1" required placeholder="1000"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.shares?.[0] ?? '' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Price / Share</label>
                    <input v-model="form.price_per_share" type="number" step="0.01" min="0.01" required placeholder="850.00"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.price_per_share?.[0] ?? '' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Date</label>
                    <input v-model="form.transacted_at" type="date" required
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Handling Fee ({{ feeRates.handling }}%)</label>
                    <input v-model="form.handling_fee" type="number" step="0.01" min="0"
                        class="h-9 w-40 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Transaction Tax ({{ feeRates.tax }}%)</label>
                    <input v-model="form.transaction_tax" type="number" step="0.01" min="0"
                        class="h-9 w-40 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Notes</label>
                    <input v-model="form.notes" type="text" placeholder="optional"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500"></p>
                </div>
                <div>
                    <label class="block text-xs mb-1 invisible">_</label>
                    <div class="h-9 flex items-center text-sm text-slate-500 dark:text-slate-400 px-1">
                        Total: <span class="ml-1 font-semibold text-slate-800 dark:text-slate-200">{{ hidden ? '••••' : txTotal }}</span>
                    </div>
                    <p class="h-[1.1rem]"></p>
                </div>
                <div>
                    <label class="block text-xs mb-1 invisible">_</label>
                    <button type="submit" :disabled="submitting"
                        :class="form.type === 'sell' ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-600 hover:bg-emerald-700'"
                        class="h-9 px-5 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors capitalize"
                    >
                        {{ submitting ? '…' : form.type }}
                    </button>
                    <p class="h-[1.1rem]"></p>
                </div>
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
            <div class="overflow-x-auto">
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
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Notes</label>
                                        <input v-model="editForm.notes" type="text" placeholder="optional"
                                            class="h-8 w-32 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div class="ml-auto flex gap-2 items-end">
                                        <button @click="saveEdit(tx.id)" :disabled="saving" class="h-8 px-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                                            {{ saving ? '…' : 'Save' }}
                                        </button>
                                        <button
                                            @click="confirmingDelete ? del(tx.id) : (confirmingDelete = true)"
                                            class="h-8 px-3 text-white text-sm font-medium rounded-lg transition-colors"
                                            :class="confirmingDelete ? 'bg-red-600 hover:bg-red-700 animate-pulse' : 'bg-red-500 hover:bg-red-600'"
                                        >{{ confirmingDelete ? 'Confirm?' : 'Delete' }}</button>
                                        <button @click="editingId = null; confirmingDelete = false" class="h-8 px-3 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-lg transition-colors">Cancel</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Display row -->
                        <tr v-else class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
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
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ hidden ? '••••' : fmt(tx.price_per_share) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                {{ hidden ? '••••' : (tx.type === 'buy'
                                    ? fmt(tx.shares * tx.price_per_share + Number(tx.handling_fee))
                                    : fmt(tx.shares * tx.price_per_share - Number(tx.handling_fee) - Number(tx.transaction_tax))) }}
                            </td>
                            <td class="px-4 py-3 text-slate-400 dark:text-slate-500 max-w-xs truncate">{{ tx.notes ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="startEdit(tx)" class="text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors text-sm leading-none" title="Edit">✎</button>
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
import { ref, computed, onMounted, watchEffect } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';
import { usePrivacy } from '../stores/privacy';

const { hidden } = usePrivacy();
const { state: authState } = useAuth();

const STANDARD_HANDLING_RATE = 0.1425;
const feeRates = computed(() => {
    const discount = Number(authState.user?.handling_fee_discount ?? 0);
    return {
        handling: +(STANDARD_HANDLING_RATE * (1 - discount)).toFixed(4),
        tax: 0.3,
    };
});

const transactions = ref([]);
const stocks = ref([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const errors = ref({});
const editingId = ref(null);
const editForm = ref({});
const saving = ref(false);
const confirmingDelete = ref(false);

const form = ref({ stock_id: '', type: 'buy', shares: '', price_per_share: '', handling_fee: 0, transaction_tax: 0, transacted_at: today(), notes: '' });

watchEffect(() => {
    const { handling: handlingRate, tax: taxRate } = feeRates.value;
    const tradeValue = Number(form.value.shares) * Number(form.value.price_per_share);
    if (!tradeValue) return;
    form.value.handling_fee = Math.max(20, Math.floor(tradeValue * handlingRate / 100));
    form.value.transaction_tax = form.value.type === 'sell'
        ? Math.floor(tradeValue * taxRate / 100)
        : 0;
});

const txTotal = computed(() => {
    const s = Number(form.value.shares);
    const p = Number(form.value.price_per_share);
    if (!s || !p) return '—';
    const base = s * p;
    const fee = Number(form.value.handling_fee);
    const tax = Number(form.value.transaction_tax);
    return (form.value.type === 'buy' ? base + fee : base - fee - tax)
        .toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});

const buyCount = computed(() => transactions.value.filter(t => t.type === 'buy').length);
const sellCount = computed(() => transactions.value.filter(t => t.type === 'sell').length);

function today() { return new Date().toISOString().slice(0, 10); }
function fmt(v) { return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

async function load() {
    try {
        const [txRes, stockRes] = await Promise.all([
            // Fetch transactions per stock and flatten — or we build a combined list from portfolio stocks
            api.get('/stocks'),
            api.get('/stocks/portfolio'),
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
        const { data } = await api.post('/stocks/transactions', form.value);
        transactions.value.unshift(data);
        transactions.value.sort((a, b) => b.transacted_at.localeCompare(a.transacted_at));
        form.value = { stock_id: '', type: 'buy', shares: '', price_per_share: '', handling_fee: 0, transaction_tax: 0, transacted_at: today(), notes: '' };
        showForm.value = false;
    } catch (e) {
        if (e.response?.status === 422) errors.value = e.response.data.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

async function del(id) {
    await api.delete(`/stocks/transactions/${id}`);
    transactions.value = transactions.value.filter(t => t.id !== id);
    editingId.value = null;
}

function startEdit(tx) {
    confirmingDelete.value = false;
    editingId.value = tx.id;
    editForm.value = {
        type: tx.type,
        shares: tx.shares,
        price_per_share: tx.price_per_share,
        handling_fee: tx.handling_fee,
        transaction_tax: tx.transaction_tax,
        transacted_at: String(tx.transacted_at).slice(0, 10),
        notes: tx.notes ?? '',
    };
}

async function saveEdit(id) {
    saving.value = true;
    try {
        const { data } = await api.put(`/stocks/transactions/${id}`, editForm.value);
        const idx = transactions.value.findIndex(t => t.id === id);
        if (idx !== -1) transactions.value[idx] = data;
        transactions.value.sort((a, b) => b.transacted_at.localeCompare(a.transacted_at));
        editingId.value = null;
        confirmingDelete.value = false;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
