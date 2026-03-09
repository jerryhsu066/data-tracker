<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-slate-900 mb-6">Portfolio</h1>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-sm text-slate-500">Total Value</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ fmt(totalValue) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-sm text-slate-500">Unrealized Gain</p>
                <p class="text-2xl font-bold mt-1" :class="totalUnrealized >= 0 ? 'text-emerald-600' : 'text-red-500'">
                    {{ sign(totalUnrealized) }}{{ fmt(Math.abs(totalUnrealized)) }}
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-sm text-slate-500">Realized Gain</p>
                <p class="text-2xl font-bold mt-1" :class="totalRealized >= 0 ? 'text-emerald-600' : 'text-red-500'">
                    {{ sign(totalRealized) }}{{ fmt(Math.abs(totalRealized)) }}
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12 text-slate-400">Loading portfolio…</div>

        <!-- Empty state -->
        <div v-else-if="positions.length === 0" class="text-center py-16 bg-white rounded-xl shadow-sm">
            <p class="text-slate-400 text-lg">No positions yet.</p>
            <RouterLink to="/stocks" class="mt-3 inline-block text-indigo-600 hover:underline text-sm">
                Add stocks and record transactions →
            </RouterLink>
        </div>

        <!-- Positions table -->
        <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-500">Stock</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Shares</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Avg Cost</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Current Price</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Value</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Unrealized</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-500">Realized</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr
                        v-for="pos in positions"
                        :key="pos.stock.symbol"
                        class="hover:bg-slate-50 cursor-pointer transition-colors"
                        @click="$router.push(`/stocks/${pos.stock.symbol}`)"
                    >
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ pos.stock.symbol }}</div>
                            <div class="text-xs text-slate-400">{{ pos.stock.name }}</div>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ num(pos.net_shares) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ fmt(pos.average_cost) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="text-slate-900">{{ fmt(pos.stock.current_price) }}</div>
                            <div v-if="pos.stock.change_percent" class="text-xs" :class="pos.stock.change_percent >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                {{ pos.stock.change_percent >= 0 ? '+' : '' }}{{ Number(pos.stock.change_percent).toFixed(2) }}%
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">{{ fmt(pos.current_value) }}</td>
                        <td class="px-4 py-3 text-right font-medium" :class="pos.unrealized_gain >= 0 ? 'text-emerald-600' : 'text-red-500'">
                            {{ sign(pos.unrealized_gain) }}{{ fmt(Math.abs(pos.unrealized_gain)) }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium" :class="pos.realized_gain >= 0 ? 'text-emerald-600' : 'text-red-500'">
                            {{ sign(pos.realized_gain) }}{{ fmt(Math.abs(pos.realized_gain)) }}
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

const positions = ref([]);
const loading = ref(true);

const totalValue = computed(() => positions.value.reduce((s, p) => s + Number(p.current_value), 0));
const totalUnrealized = computed(() => positions.value.reduce((s, p) => s + Number(p.unrealized_gain), 0));
const totalRealized = computed(() => positions.value.reduce((s, p) => s + Number(p.realized_gain), 0));

function fmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function num(v) { return Number(v).toLocaleString(); }
function sign(v) { return Number(v) >= 0 ? '+' : '-'; }

onMounted(async () => {
    try {
        const { data } = await api.get('/portfolio');
        positions.value = data;
    } finally {
        loading.value = false;
    }
});
</script>
