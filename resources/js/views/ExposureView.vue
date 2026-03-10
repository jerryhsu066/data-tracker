<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-6">Exposure</h1>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Bundle tabs -->
            <div class="flex items-center gap-1 mb-6 border-b border-slate-200 dark:border-slate-700">
                <button
                    v-for="bundle in bundles"
                    :key="bundle.id"
                    class="relative group px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
                    :class="activeId === bundle.id
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'"
                    @click="activeId = bundle.id"
                >
                    <span v-if="renamingId !== bundle.id">{{ bundle.name }}</span>
                    <input
                        v-else
                        ref="renameInput"
                        v-model="renameValue"
                        type="text"
                        class="w-28 bg-transparent border-none outline-none text-sm font-medium"
                        @keydown.enter="commitRename"
                        @keydown.escape="renamingId = null"
                        @blur="commitRename"
                        @click.stop
                    />
                    <button
                        v-if="renamingId !== bundle.id"
                        class="ml-1.5 opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs"
                        title="Rename"
                        @click.stop="startRename(bundle)"
                    >✎</button>
                    <button
                        v-if="renamingId !== bundle.id && bundles.length > 1"
                        class="ml-0.5 opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-red-500 text-xs"
                        title="Delete bundle"
                        @click.stop="deleteBundle(bundle.id)"
                    >✕</button>
                </button>

                <div v-if="addingBundle" class="flex items-center gap-1 px-2 pb-2">
                    <input
                        ref="newBundleInput"
                        v-model="newBundleName"
                        type="text"
                        placeholder="Bundle name"
                        class="h-7 w-32 rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        @keydown.enter="commitNewBundle"
                        @keydown.escape="addingBundle = false"
                        @blur="commitNewBundle"
                    />
                </div>
                <button
                    v-else
                    class="px-3 py-2 text-sm text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors -mb-px border-b-2 border-transparent"
                    @click="startAddBundle"
                >+ New</button>
            </div>

            <template v-if="active">
                <!-- Summary cards -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Total Value</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ fmt(totalValue) }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Market Exposure</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ fmt(totalExposure) }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Exposure Ratio</p>
                        <p class="text-2xl font-bold mt-1" :class="exposureRatioPct > 100 ? 'text-amber-500' : 'text-indigo-600'">
                            {{ exposureRatioPct.toFixed(1) }}%
                        </p>
                    </div>
                </div>

                <!-- Exposure bar -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Market Exposure Ratio</span>
                        <span class="text-sm font-bold" :class="exposureRatioPct > 100 ? 'text-amber-500' : 'text-slate-900 dark:text-slate-100'">
                            {{ exposureRatioPct.toFixed(1) }}%
                        </span>
                    </div>
                    <div class="relative h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="absolute top-0 bottom-0 w-px bg-slate-400/60 dark:bg-slate-500/60" style="left: 50%"></div>
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="exposureRatioPct > 100 ? 'bg-amber-500' : 'bg-indigo-500'"
                            :style="`width: ${Math.min(exposureRatioPct, 200) / 2}%`"
                        ></div>
                    </div>
                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                        <span>0%</span>
                        <span>100%</span>
                        <span>200%</span>
                    </div>
                </div>

                <!-- Add entry form -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 mb-4">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Add Position</h2>
                    <form @submit.prevent="addEntry" class="flex gap-3 items-end flex-wrap">
                        <div class="flex-1 min-w-32">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Symbol</label>
                            <input
                                v-model="form.symbol"
                                type="text"
                                placeholder="e.g. 0050.TW"
                                class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div class="w-28">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Leverage</label>
                            <input
                                v-model.number="form.leverage"
                                type="number"
                                min="0"
                                step="0.5"
                                placeholder="1"
                                :disabled="form.isCash"
                                class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-40"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Cash proxy</label>
                            <label class="relative inline-flex items-center h-9 cursor-pointer">
                                <input type="checkbox" v-model="form.isCash" class="sr-only" />
                                <div
                                    class="w-10 h-5 rounded-full transition-colors"
                                    :class="form.isCash ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"
                                ></div>
                                <div
                                    class="absolute top-2 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                    :class="form.isCash ? 'translate-x-5' : 'translate-x-0'"
                                ></div>
                            </label>
                        </div>
                        <button
                            type="submit"
                            :disabled="!form.symbol || submitting"
                            class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
                        >
                            Add
                        </button>
                    </form>
                    <p class="h-[1.1rem] text-xs text-red-500 mt-2">{{ formError }}</p>
                </div>

                <!-- Positions table -->
                <div v-if="active.entries.length > 0 || active.cash > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Symbol</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Price</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Net Shares</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Value</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Leverage</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Exposure</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Allocation</th>
                                <th class="px-4 py-3 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                            <tr v-for="entry in active.entries" :key="entry.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">{{ entry.stock.symbol }}</div>
                                    <div class="text-xs" :class="entry.is_cash ? 'text-slate-400' : 'text-indigo-400'">
                                        {{ entry.is_cash ? 'cash proxy' : entry.leverage + 'x leverage' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">
                                    {{ fmt(entry.stock.current_price) }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">
                                    {{ Number(entry.net_shares).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">
                                    {{ fmt(Number(entry.net_shares) * Number(entry.stock.current_price)) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span v-if="entry.is_cash" class="text-slate-400">—</span>
                                    <span v-else class="text-slate-700 dark:text-slate-300">{{ entry.leverage }}x</span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                    <span v-if="!entry.is_cash">
                                        {{ fmt(Number(entry.net_shares) * Number(entry.stock.current_price) * Number(entry.leverage)) }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">
                                    <span v-if="totalValue > 0">
                                        {{ ((Number(entry.net_shares) * Number(entry.stock.current_price) / totalValue) * 100).toFixed(1) }}%
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="removeEntry(entry.id)"
                                        class="text-slate-300 hover:text-red-500 dark:text-slate-600 dark:hover:text-red-500 transition-colors text-base leading-none"
                                    >✕</button>
                                </td>
                            </tr>

                            <!-- Cash row -->
                            <tr class="bg-slate-50/60 dark:bg-slate-700/20">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">Cash</div>
                                    <div class="text-xs text-slate-400">actual cash</div>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-400">—</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        v-model.number="localCash"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        class="h-7 w-28 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ fmt(localCash || 0) }}</td>
                                <td class="px-4 py-3 text-right text-slate-400">—</td>
                                <td class="px-4 py-3 text-right text-slate-400">—</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">
                                    {{ totalValue > 0 ? (((localCash || 0) / totalValue) * 100).toFixed(1) + '%' : '—' }}
                                </td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
                    <p class="text-slate-400 text-lg">No positions yet.</p>
                    <p class="text-slate-400 text-sm mt-1">Use the form above to add stocks to this bundle.</p>
                </div>
            </template>

            <!-- No bundles at all -->
            <div v-else-if="!loading" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
                <p class="text-slate-400 text-lg">No bundles yet.</p>
                <p class="text-slate-400 text-sm mt-1">Click "+ New" to create your first exposure bundle.</p>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import api from '../api';

const bundles = ref([]);
const activeId = ref(null);
const stocks = ref([]);
const loading = ref(true);
const submitting = ref(false);

const form = ref({ symbol: '', leverage: 1, isCash: false });
const formError = ref('');

const renamingId = ref(null);
const renameValue = ref('');
const renameInput = ref(null);

const addingBundle = ref(false);
const newBundleName = ref('');
const newBundleInput = ref(null);

const localCash = ref(0);
let cashTimer = null;

const active = computed(() => bundles.value.find(b => b.id === activeId.value) ?? null);

watch(active, (b) => {
    if (b) localCash.value = b.cash;
}, { immediate: true });

watch(localCash, () => {
    if (!active.value) return;
    clearTimeout(cashTimer);
    cashTimer = setTimeout(saveCash, 600);
});

const totalValue = computed(() => {
    if (!active.value) return 0;
    const stockValue = active.value.entries.reduce((sum, e) => {
        return sum + Number(e.net_shares) * Number(e.stock.current_price);
    }, 0);
    return stockValue + (localCash.value || 0);
});

const totalExposure = computed(() => {
    if (!active.value) return 0;
    return active.value.entries.reduce((sum, e) => {
        if (e.is_cash) return sum;
        return sum + Number(e.net_shares) * Number(e.stock.current_price) * Number(e.leverage);
    }, 0);
});

const exposureRatioPct = computed(() =>
    totalValue.value > 0 ? (totalExposure.value / totalValue.value) * 100 : 0
);

async function loadBundles() {
    const res = await api.get('/exposure/bundles');
    bundles.value = res.data;
    if (!activeId.value && bundles.value.length > 0) {
        activeId.value = bundles.value[0].id;
    }
}

function replaceBundle(updated) {
    const idx = bundles.value.findIndex(b => b.id === updated.id);
    if (idx !== -1) bundles.value[idx] = updated;
}

async function saveCash() {
    if (!active.value) return;
    const res = await api.patch(`/exposure/bundles/${active.value.id}`, { cash: localCash.value || 0 });
    replaceBundle(res.data);
}

async function addEntry() {
    formError.value = '';
    const symbol = form.value.symbol.trim().toUpperCase();
    if (!symbol) { formError.value = 'Symbol is required.'; return; }

    const stock = stocks.value.find(s => s.symbol.toUpperCase() === symbol);
    if (!stock) { formError.value = `"${symbol}" not found — add it to your stocks list first.`; return; }

    submitting.value = true;
    try {
        const res = await api.post(`/exposure/bundles/${active.value.id}/entries`, {
            stock_id: stock.id,
            leverage: form.value.isCash ? 0 : (form.value.leverage ?? 1),
            is_cash: form.value.isCash,
        });
        replaceBundle(res.data);
        form.value = { symbol: '', leverage: 1, isCash: false };
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Failed to add entry.';
    } finally {
        submitting.value = false;
    }
}

async function removeEntry(entryId) {
    await api.delete(`/exposure/bundles/${active.value.id}/entries/${entryId}`);
    const bundle = bundles.value.find(b => b.id === active.value.id);
    if (bundle) bundle.entries = bundle.entries.filter(e => e.id !== entryId);
}

function startRename(bundle) {
    renamingId.value = bundle.id;
    renameValue.value = bundle.name;
    nextTick(() => renameInput.value?.[0]?.focus());
}

async function commitRename() {
    if (!renamingId.value) return;
    const id = renamingId.value;
    renamingId.value = null;
    const name = renameValue.value.trim();
    if (!name) return;
    const res = await api.patch(`/exposure/bundles/${id}`, { name });
    replaceBundle(res.data);
}

async function deleteBundle(id) {
    await api.delete(`/exposure/bundles/${id}`);
    const idx = bundles.value.findIndex(b => b.id === id);
    bundles.value.splice(idx, 1);
    if (activeId.value === id) {
        activeId.value = bundles.value[Math.max(0, idx - 1)]?.id ?? null;
    }
}

function startAddBundle() {
    addingBundle.value = true;
    newBundleName.value = '';
    nextTick(() => newBundleInput.value?.focus());
}

async function commitNewBundle() {
    addingBundle.value = false;
    const name = newBundleName.value.trim() || `Bundle ${bundles.value.length + 1}`;
    const res = await api.post('/exposure/bundles', { name });
    bundles.value.push(res.data);
    activeId.value = res.data.id;
}

onMounted(async () => {
    try {
        const [, stocksRes] = await Promise.all([
            loadBundles(),
            api.get('/stocks'),
        ]);
        stocks.value = stocksRes.data;
    } finally {
        loading.value = false;
    }
});

function fmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
