<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Tracked Stocks</h1>
            <button
                @click="showForm = !showForm"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
                + Add Stock
            </button>
        </div>

        <!-- Add stock form -->
        <div v-if="showForm" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">Add a stock to track</h2>
            <form @submit.prevent="addStock" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Symbol</label>
                    <input
                        v-model="form.symbol"
                        placeholder="e.g. 2330.TW"
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 py-2 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.symbol" class="text-xs text-red-500 mt-1">{{ errors.symbol[0] }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Name</label>
                    <input
                        v-model="form.name"
                        placeholder="e.g. TSMC"
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 py-2 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.name" class="text-xs text-red-500 mt-1">{{ errors.name[0] }}</p>
                </div>
                <button type="submit" :disabled="adding" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
                    {{ adding ? 'Adding…' : 'Add' }}
                </button>
                <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                    Cancel
                </button>
            </form>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <div v-else-if="stocks.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm text-slate-400">
            No stocks tracked yet. Add one above.
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="stock in stocks"
                :key="stock.symbol"
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 flex flex-col gap-3"
            >
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <RouterLink :to="`/stocks/${stock.symbol}`" class="hover:text-indigo-600 transition-colors">
                        <div class="font-bold text-slate-900 dark:text-slate-100 text-lg">{{ stock.symbol }}</div>
                        <div class="text-sm text-slate-400 dark:text-slate-500">{{ stock.name }}</div>
                    </RouterLink>
                    <button
                        @click="deleteStock(stock.symbol)"
                        class="text-slate-300 dark:text-slate-600 hover:text-red-400 transition-colors text-lg leading-none"
                        title="Untrack"
                    >✕</button>
                </div>

                <!-- Price -->
                <div>
                    <div v-if="stock.current_price" class="flex items-baseline gap-2">
                        <span class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ fmt(stock.current_price) }}</span>
                        <span v-if="stock.change_percent" class="text-sm font-medium" :class="stock.change_percent >= 0 ? 'text-emerald-600' : 'text-red-500'">
                            {{ stock.change_percent >= 0 ? '+' : '' }}{{ Number(stock.change_percent).toFixed(2) }}%
                        </span>
                    </div>
                    <div v-else class="text-slate-400 dark:text-slate-500 text-sm">No price data</div>
                    <div v-if="stock.last_fetched_at" class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                        Updated {{ timeAgo(stock.last_fetched_at) }}
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-1">
                    <button
                        @click="fetchPrice(stock.symbol)"
                        :disabled="fetching === stock.symbol"
                        class="flex-1 py-1.5 text-xs bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 disabled:opacity-50 text-slate-600 dark:text-slate-300 rounded-lg transition-colors"
                    >
                        {{ fetching === stock.symbol ? 'Refreshing…' : '↻ Refresh price' }}
                    </button>
                    <RouterLink
                        :to="`/stocks/${stock.symbol}`"
                        class="flex-1 py-1.5 text-xs bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-lg text-center transition-colors"
                    >
                        View details →
                    </RouterLink>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';

const stocks = ref([]);
const loading = ref(true);
const showForm = ref(false);
const adding = ref(false);
const fetching = ref(null);
const form = ref({ symbol: '', name: '' });
const errors = ref({});

function fmt(v) {
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
    if (diff < 1) return 'just now';
    if (diff < 60) return `${diff}m ago`;
    if (diff < 1440) return `${Math.floor(diff / 60)}h ago`;
    return `${Math.floor(diff / 1440)}d ago`;
}

async function load() {
    try {
        const { data } = await api.get('/stocks');
        stocks.value = data;
    } finally {
        loading.value = false;
    }
}

async function addStock() {
    errors.value = {};
    adding.value = true;
    try {
        const { data } = await api.post('/stocks', form.value);
        stocks.value.unshift(data);
        form.value = { symbol: '', name: '' };
        showForm.value = false;
    } catch (e) {
        if (e.response?.status === 422) errors.value = e.response.data.errors ?? {};
    } finally {
        adding.value = false;
    }
}

async function deleteStock(symbol) {
    if (!confirm(`Untrack ${symbol}?`)) return;
    await api.delete(`/stocks/${symbol}`);
    stocks.value = stocks.value.filter(s => s.symbol !== symbol);
}

async function fetchPrice(symbol) {
    fetching.value = symbol;
    try {
        const { data } = await api.post(`/stocks/${symbol}/fetch`);
        const idx = stocks.value.findIndex(s => s.symbol === symbol);
        if (idx !== -1) stocks.value[idx] = data;
    } finally {
        fetching.value = null;
    }
}

onMounted(load);
</script>
