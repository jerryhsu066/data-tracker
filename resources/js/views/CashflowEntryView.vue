<template>
    <div class="max-w-xl mx-auto px-4 py-8 space-y-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Log Cashflow</h1>

        <!-- Month navigation -->
        <div class="flex items-center gap-3">
            <button @click="prevMonth" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">‹</button>
            <span class="text-base font-semibold text-slate-700 dark:text-slate-200 w-36 text-center">{{ monthLabel }}</span>
            <button @click="nextMonth" :disabled="isCurrentMonth" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">›</button>
        </div>

        <!-- Entry form -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
            <form @submit.prevent="addRecord" class="space-y-4">
                <!-- Type + Subtype -->
                <div class="flex gap-3 flex-wrap">
                    <div class="flex-1 min-w-36">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Type</label>
                        <select v-model="form.type_id"
                            class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Select type…</option>
                            <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p class="h-[1.1rem] text-xs text-red-500">{{ formErrors.type_id?.[0] ?? '' }}</p>
                    </div>
                    <div v-if="selectedType?.subtypes?.length" class="flex-1 min-w-36">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ selectedType.name }}</label>
                        <select v-model="form.subtype_id"
                            class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Select…</option>
                            <option v-for="s in selectedType.subtypes" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p class="h-[1.1rem] text-xs text-red-500">{{ formErrors.subtype_id?.[0] ?? '' }}</p>
                    </div>
                </div>

                <!-- Amount + Date -->
                <div class="flex gap-3 flex-wrap">
                    <div class="flex-1 min-w-32">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Amount</label>
                        <input
                            v-model.number="form.amount"
                            type="number" min="0" step="1" placeholder="0"
                            class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p class="h-[1.1rem] text-xs text-red-500">{{ formErrors.amount?.[0] ?? '' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Date</label>
                        <input
                            v-model="form.recorded_at"
                            type="date"
                            class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p class="h-[1.1rem]"></p>
                    </div>
                </div>

                <!-- Note -->
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Note <span class="font-normal opacity-60">(optional)</span></label>
                    <input
                        v-model="form.note"
                        type="text" placeholder="e.g. Monthly Netflix"
                        class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <button
                    type="submit" :disabled="submitting"
                    class="w-full h-10 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-medium rounded-md transition-colors"
                >{{ submitting ? 'Adding…' : 'Add Record' }}</button>
            </form>
        </div>

        <!-- Records this month -->
        <div v-if="loadingRecords" class="text-center py-8 text-slate-400">Loading…</div>

        <template v-else>
            <div v-if="records.length === 0" class="text-center py-10 text-slate-400 text-sm">
                No records for {{ monthLabel }}.
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ monthLabel }}</span>
                    <span class="text-xs text-slate-400">{{ records.length }} record{{ records.length !== 1 ? 's' : '' }}</span>
                </div>
                <ul class="divide-y divide-slate-50 dark:divide-slate-700">
                    <li v-for="rec in records" :key="rec.id" class="flex items-center gap-3 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0" :class="typeBadgeClass(rec.type_id)">
                                    {{ typeNameById(rec.type_id) }}
                                </span>
                                <span v-if="rec.subtype_id" class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ subtypeNameById(rec.subtype_id) }}</span>
                            </div>
                            <p v-if="rec.note" class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 truncate">{{ rec.note }}</p>
                        </div>
                        <span class="text-sm font-semibold shrink-0" :class="isExpenseById(rec.type_id) ? 'text-slate-800 dark:text-slate-100' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ hidden ? '••••' : (isExpenseById(rec.type_id) ? '−' : '+') + fmt(rec.amount) }}
                        </span>
                        <span class="text-xs text-slate-400 dark:text-slate-500 shrink-0 w-16 text-right">{{ rec.recorded_at.slice(5, 10) }}</span>
                        <button
                            @click="confirmAndDelete(rec.id)"
                            class="shrink-0 text-slate-300 dark:text-slate-600 hover:text-red-500 transition-colors leading-none"
                            :class="{ 'text-red-500 animate-pulse': confirmingDelete === rec.id }"
                            title="Delete"
                        >✕</button>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import api from '../api';
import { usePrivacy } from '../stores/privacy';

const { hidden } = usePrivacy();

const now   = new Date();
const year  = ref(now.getFullYear());
const month = ref(now.getMonth() + 1);

const types         = ref([]);
const records       = ref([]);
const loadingRecords = ref(true);

const submitting   = ref(false);
const formErrors   = ref({});
const confirmingDelete = ref(null);

const form = ref(defaultForm());

function defaultForm() {
    return { type_id: '', subtype_id: '', amount: '', recorded_at: firstOfMonth(), note: '' };
}
function firstOfMonth() {
    return `${year.value}-${String(month.value).padStart(2, '0')}-01`;
}

const monthLabel = computed(() =>
    new Date(year.value, month.value - 1, 1).toLocaleString('en', { year: 'numeric', month: 'long' })
);
const isCurrentMonth = computed(() =>
    year.value === now.getFullYear() && month.value === now.getMonth() + 1
);

function prevMonth() {
    if (month.value === 1) { year.value--; month.value = 12; }
    else month.value--;
}
function nextMonth() {
    if (isCurrentMonth.value) return;
    if (month.value === 12) { year.value++; month.value = 1; }
    else month.value++;
}

// ── Lookups ───────────────────────────────────────────────────────────────────

const typeMap = computed(() => Object.fromEntries(types.value.map(t => [t.id, t])));
const subtypeMap = computed(() => {
    const m = {};
    for (const t of types.value) for (const s of t.subtypes) m[s.id] = s;
    return m;
});

function typeNameById(id)    { return typeMap.value[id]?.name ?? '—'; }
function subtypeNameById(id) { return subtypeMap.value[id]?.name ?? '—'; }
function isExpenseById(id)   { return typeMap.value[id]?.is_expense ?? true; }

const BADGE_COLORS = [
    'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-400',
    'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400',
    'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400',
    'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-400',
    'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400',
    'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-400',
];
const expenseTypeIds = computed(() => types.value.filter(t => t.is_expense).map(t => t.id));
function typeBadgeClass(id) {
    if (!isExpenseById(id)) return 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400';
    const idx = expenseTypeIds.value.indexOf(id);
    return BADGE_COLORS[idx % BADGE_COLORS.length] ?? 'bg-slate-100 text-slate-600';
}

const selectedType = computed(() => typeMap.value[form.value.type_id] ?? null);
watch(() => form.value.type_id, () => { form.value.subtype_id = ''; });

function fmt(v) {
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ── Data ──────────────────────────────────────────────────────────────────────

async function loadRecords() {
    loadingRecords.value = true;
    try {
        const { data } = await api.get('/cashflow/records', { params: { year: year.value, month: month.value } });
        records.value = data.slice().reverse(); // newest first
    } finally {
        loadingRecords.value = false;
    }
}

watch([year, month], () => {
    form.value.recorded_at = firstOfMonth();
    loadRecords();
});

// ── Actions ───────────────────────────────────────────────────────────────────

async function addRecord() {
    formErrors.value = {};
    submitting.value = true;
    try {
        const { data } = await api.post('/cashflow/records', {
            recorded_at: form.value.recorded_at,
            type_id:     form.value.type_id,
            subtype_id:  form.value.subtype_id || null,
            amount:      form.value.amount,
            note:        form.value.note || null,
        });
        records.value.unshift(data); // add to top (newest first)
        form.value = defaultForm();
    } catch (e) {
        if (e.response?.status === 422) formErrors.value = e.response.data.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

function confirmAndDelete(id) {
    if (confirmingDelete.value === id) {
        deleteRecord(id);
    } else {
        confirmingDelete.value = id;
        setTimeout(() => {
            if (confirmingDelete.value === id) confirmingDelete.value = null;
        }, 3000);
    }
}

async function deleteRecord(id) {
    confirmingDelete.value = null;
    await api.delete(`/cashflow/records/${id}`);
    records.value = records.value.filter(r => r.id !== id);
}

onMounted(async () => {
    const [, typesRes] = await Promise.all([
        loadRecords(),
        api.get('/cashflow/settings/types'),
    ]);
    types.value = typesRes.data;
});
</script>
