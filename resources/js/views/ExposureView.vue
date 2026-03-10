<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-6">Exposure</h1>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Bundle tabs -->
            <div class="flex items-center gap-1 mb-6 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
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
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Invest Value</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ hidden ? '••••' : fmt(investValue) }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Market Exposure</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ hidden ? '••••' : fmt(totalExposure) }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Exposure Rate</p>
                        <p class="text-2xl font-bold mt-1" :class="exposureRatioPct > 100 ? 'text-amber-500' : 'text-indigo-500 dark:text-indigo-400'">
                            {{ exposureRatioPct.toFixed(1) }}%
                        </p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Gain / Loss</p>
                        <p class="text-2xl font-bold mt-1" :class="gainLoss >= 0 ? 'text-emerald-600' : 'text-red-500'">
                            {{ gainLoss >= 0 ? '+' : '' }}{{ gainLossPct.toFixed(2) }}%
                        </p>
                    </div>
                </div>

                <!-- Allocation bar -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 mb-6">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Allocation</h2>
                    <div class="flex h-8 rounded-lg overflow-hidden gap-px bg-slate-100 dark:bg-slate-700">
                        <div
                            v-for="(seg, i) in allocationSegments"
                            :key="seg.label"
                            :style="{ width: seg.pct + '%', backgroundColor: PALETTE[i % PALETTE.length] }"
                            :title="`${seg.label}: ${seg.pct.toFixed(1)}%`"
                            class="relative flex items-center justify-center transition-all duration-300 overflow-hidden"
                        >
                            <span
                                v-if="seg.pct >= 7"
                                class="text-white text-xs font-semibold leading-none select-none drop-shadow"
                            >{{ seg.pct.toFixed(1) }}%</span>
                        </div>
                    </div>
                    <ul class="flex flex-wrap gap-x-4 gap-y-1 mt-3">
                        <li
                            v-for="(seg, i) in allocationSegments"
                            :key="seg.label"
                            class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400"
                        >
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: PALETTE[i % PALETTE.length] }"></span>
                            <span>{{ seg.label }}</span>
                            <span class="text-slate-400 dark:text-slate-500">{{ seg.pct.toFixed(1) }}%</span>
                        </li>
                    </ul>
                </div>

                <!-- Positions table header row with Add button -->
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Positions</span>
                    <button
                        @click="showAddForm = !showAddForm"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                        :class="showAddForm
                            ? 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200'
                            : 'bg-indigo-600 hover:bg-indigo-500 text-white'"
                    >
                        <span>{{ showAddForm ? '✕ Cancel' : '+ Add Position' }}</span>
                    </button>
                </div>

                <!-- Collapsible add entry form -->
                <div v-if="showAddForm" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 mb-3">
                    <form @submit.prevent="addEntry" class="flex gap-3 items-end flex-wrap">
                        <div class="flex-1 min-w-40">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Symbol</label>
                            <select
                                v-model="form.stockId"
                                class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="" disabled>Select a stock…</option>
                                <option v-for="s in stocks" :key="s.id" :value="s.id">
                                    {{ s.symbol }} — {{ s.name }}
                                </option>
                            </select>
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Shares
                                <span v-if="form.sharesIsAuto" class="ml-1 text-indigo-400">(auto)</span>
                            </label>
                            <input
                                v-model.number="form.shares"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                @input="form.sharesIsAuto = false"
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
                            :disabled="!form.stockId || submitting"
                            class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
                        >
                            Add
                        </button>
                    </form>
                    <p class="h-[1.1rem] text-xs text-red-500 mt-2">{{ formError }}</p>
                </div>

                <!-- Positions table -->
                <div v-if="active.entries.length > 0 || active.cash > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Symbol</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Price</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Shares</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Value</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Leverage</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Exposure</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Allocation</th>
                                <th class="px-4 py-3 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                            <template v-for="entry in active.entries" :key="entry.id">
                                <!-- View row -->
                                <tr v-if="editingEntry?.id !== entry.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
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
                                        {{ hidden ? '••••' : fmt(Number(entry.net_shares) * Number(entry.stock.current_price)) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span v-if="entry.is_cash" class="text-slate-400">—</span>
                                        <span v-else class="text-slate-700 dark:text-slate-300">{{ entry.leverage }}x</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                        <span v-if="!entry.is_cash">
                                            {{ hidden ? '••••' : fmt(Number(entry.net_shares) * Number(entry.stock.current_price) * Number(entry.leverage)) }}
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
                                            @click="startEdit(entry)"
                                            class="text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors text-sm leading-none"
                                            title="Edit"
                                        >✎</button>
                                    </td>
                                </tr>

                                <!-- Edit row -->
                                <tr v-else class="bg-indigo-50/60 dark:bg-indigo-900/10">
                                    <td colspan="8" class="px-4 py-3">
                                        <div class="flex items-center gap-4 flex-wrap">
                                            <span class="font-semibold text-slate-900 dark:text-slate-100 min-w-16">{{ entry.stock.symbol }}</span>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Shares</label>
                                                <input
                                                    v-model.number="editingEntry.shares"
                                                    type="number"
                                                    min="0"
                                                    class="h-8 w-28 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Leverage</label>
                                                <input
                                                    v-model.number="editingEntry.leverage"
                                                    type="number"
                                                    min="0"
                                                    step="0.5"
                                                    :disabled="editingEntry.isCash"
                                                    class="h-8 w-20 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-40"
                                                />
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Cash proxy</label>
                                                <label class="relative inline-flex items-center h-8 cursor-pointer">
                                                    <input type="checkbox" v-model="editingEntry.isCash" class="sr-only" />
                                                    <div class="w-10 h-5 rounded-full transition-colors" :class="editingEntry.isCash ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"></div>
                                                    <div class="absolute top-1.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="editingEntry.isCash ? 'translate-x-5' : 'translate-x-0'"></div>
                                                </label>
                                            </div>
                                            <div class="flex gap-2 ml-auto">
                                                <button
                                                    @click="saveEdit(entry)"
                                                    class="h-8 px-3 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors"
                                                >Save</button>
                                                <button
                                                    @click="confirmingDelete ? removeEntry(entry.id) : (confirmingDelete = true)"
                                                    class="h-8 px-3 text-white text-sm font-medium rounded-md transition-colors"
                                                    :class="confirmingDelete ? 'bg-red-600 hover:bg-red-700 animate-pulse' : 'bg-red-500 hover:bg-red-600'"
                                                >{{ confirmingDelete ? 'Confirm?' : 'Delete' }}</button>
                                                <button
                                                    @click="cancelEdit"
                                                    class="h-8 px-3 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors"
                                                >Cancel</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Cash row - view -->
                            <tr v-if="!editingCash" class="bg-slate-50/60 dark:bg-slate-700/20 hover:bg-slate-100/60 dark:hover:bg-slate-700/40">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">Cash</div>
                                    <div class="text-xs text-slate-400">actual cash</div>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-400">—</td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ hidden ? '••••' : fmt(active.cash || 0) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ hidden ? '••••' : fmt(active.cash || 0) }}</td>
                                <td class="px-4 py-3 text-right text-slate-400">—</td>
                                <td class="px-4 py-3 text-right text-slate-400">—</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">
                                    {{ totalValue > 0 ? (((active.cash || 0) / totalValue) * 100).toFixed(1) + '%' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="editingCash = true; cashInput = active.cash || 0"
                                        class="text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors text-sm leading-none"
                                        title="Edit"
                                    >✎</button>
                                </td>
                            </tr>

                            <!-- Cash row - edit -->
                            <tr v-else class="bg-indigo-50/60 dark:bg-indigo-900/10">
                                <td colspan="8" class="px-4 py-3">
                                    <div class="flex items-center gap-4">
                                        <span class="font-semibold text-slate-900 dark:text-slate-100">Cash</span>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Amount</label>
                                            <input
                                                v-model.number="cashInput"
                                                type="number"
                                                min="0"
                                                class="h-8 w-36 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                @keydown.enter="saveCash"
                                                @keydown.escape="editingCash = false"
                                            />
                                        </div>
                                        <div class="flex gap-2 ml-auto">
                                            <button
                                                @click="saveCash"
                                                class="h-8 px-3 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors"
                                            >Save</button>
                                            <button
                                                @click="editingCash = false"
                                                class="h-8 px-3 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors"
                                            >Cancel</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
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
import { usePrivacy } from '../stores/privacy';

const PALETTE = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];

const bundles = ref([]);
const activeId = ref(null);
const stocks = ref([]);
const portfolio = ref([]); // for auto-filling shares in add form
const loading = ref(true);
const submitting = ref(false);

const form = ref({ stockId: '', shares: 0, sharesIsAuto: true, leverage: 1, isCash: false });
const formError = ref('');
const showAddForm = ref(false);

const renamingId = ref(null);
const renameValue = ref('');
const renameInput = ref(null);

const addingBundle = ref(false);
const newBundleName = ref('');
const newBundleInput = ref(null);

const editingCash = ref(false);
const cashInput = ref(0);

const editingEntry = ref(null); // { id, shares, leverage, isCash }
const confirmingDelete = ref(false);

const active = computed(() => bundles.value.find(b => b.id === activeId.value) ?? null);

// When stock is selected in form, auto-fill shares from portfolio
watch(() => form.value.stockId, (stockId) => {
    if (!stockId) { form.value.shares = 0; form.value.sharesIsAuto = true; return; }
    const pos = portfolio.value.find(p => p.stock.id === stockId);
    form.value.shares = pos ? Number(pos.net_shares) : 0;
    form.value.sharesIsAuto = true;
});

watch(active, () => {
    showAddForm.value = false;
    formError.value = '';
    editingEntry.value = null;
    editingCash.value = false;
    confirmingDelete.value = false;
}, { immediate: true });

const totalValue = computed(() => {
    if (!active.value) return 0;
    const stockValue = active.value.entries.reduce((sum, e) => {
        return sum + Number(e.net_shares) * Number(e.stock.current_price);
    }, 0);
    return stockValue + (active.value.cash || 0);
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

// Cost basis: sum of (net_shares × average_cost) from portfolio, plus cash
const investValue = computed(() => {
    if (!active.value) return 0;
    const stockCost = active.value.entries.reduce((sum, e) => {
        const pos = portfolio.value.find(p => p.stock.id === e.stock.id);
        const avgCost = pos ? Number(pos.average_cost) : Number(e.stock.current_price);
        return sum + Number(e.net_shares) * avgCost;
    }, 0);
    return stockCost + (active.value.cash || 0);
});

const gainLoss = computed(() => totalValue.value - investValue.value);
const gainLossPct = computed(() => investValue.value > 0 ? (gainLoss.value / investValue.value) * 100 : 0);

const allocationSegments = computed(() => {
    if (!active.value || totalValue.value === 0) return [];
    const segs = active.value.entries.map(e => ({
        label: e.stock.symbol,
        pct: (Number(e.net_shares) * Number(e.stock.current_price) / totalValue.value) * 100,
    }));
    const cash = active.value.cash || 0;
    if (cash > 0) {
        segs.push({ label: 'Cash', pct: (cash / totalValue.value) * 100 });
    }
    return segs;
});

async function loadBundles() {
    const res = await api.get('/stocks/exposure/bundles');
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
    const res = await api.patch(`/stocks/exposure/bundles/${active.value.id}`, { cash: cashInput.value || 0 });
    replaceBundle(res.data);
    editingCash.value = false;
}

async function addEntry() {
    formError.value = '';
    if (!form.value.stockId) { formError.value = 'Please select a stock.'; return; }

    // shares_override: null when auto (user didn't touch it), value when manually set
    const sharesOverride = form.value.sharesIsAuto ? null : form.value.shares;

    submitting.value = true;
    try {
        const res = await api.post(`/stocks/exposure/bundles/${active.value.id}/entries`, {
            stock_id:        form.value.stockId,
            shares_override: sharesOverride,
            leverage:        form.value.isCash ? 0 : (form.value.leverage ?? 1),
            is_cash:         form.value.isCash,
        });
        replaceBundle(res.data);
        form.value = { stockId: '', shares: 0, sharesIsAuto: true, leverage: 1, isCash: false };
        showAddForm.value = false;
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Failed to add entry.';
    } finally {
        submitting.value = false;
    }
}

function startEdit(entry) {
    confirmingDelete.value = false;
    editingEntry.value = {
        id: entry.id,
        shares: Number(entry.net_shares),
        leverage: Number(entry.leverage),
        isCash: entry.is_cash,
    };
}

function cancelEdit() {
    editingEntry.value = null;
    confirmingDelete.value = false;
}

async function saveEdit(entry) {
    const e = editingEntry.value;
    if (!e) return;
    const res = await api.patch(`/stocks/exposure/bundles/${active.value.id}/entries/${entry.id}`, {
        shares_override: e.shares,
        leverage: e.isCash ? 0 : e.leverage,
        is_cash: e.isCash,
    });
    replaceBundle(res.data);
    editingEntry.value = null;
    confirmingDelete.value = false;
}

async function removeEntry(entryId) {
    await api.delete(`/stocks/exposure/bundles/${active.value.id}/entries/${entryId}`);
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
    const res = await api.patch(`/stocks/exposure/bundles/${id}`, { name });
    replaceBundle(res.data);
}

async function deleteBundle(id) {
    await api.delete(`/stocks/exposure/bundles/${id}`);
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
    const res = await api.post('/stocks/exposure/bundles', { name });
    bundles.value.push(res.data);
    activeId.value = res.data.id;
}

onMounted(async () => {
    try {
        const [, stocksRes, portfolioRes] = await Promise.all([
            loadBundles(),
            api.get('/stocks'),
            api.get('/stocks/portfolio'),
        ]);
        stocks.value = stocksRes.data;
        portfolio.value = portfolioRes.data;
    } finally {
        loading.value = false;
    }
});

const { hidden } = usePrivacy();

function fmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
