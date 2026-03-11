<template>
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Log Cashflow</h1>

        <!-- Month navigation -->
        <div class="flex items-center gap-3">
            <button @click="prevMonth" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">‹</button>
            <span class="text-base font-semibold text-slate-700 dark:text-slate-200 w-36 text-center">{{ monthLabel }}</span>
            <button @click="nextMonth" :disabled="isCurrentMonth" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">›</button>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-400">Loading…</div>

        <template v-else>
            <div v-for="type in visibleTypes" :key="type.id" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">

                <!-- Type header -->
                <div class="flex items-center gap-2 px-5 py-2.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/40">
                    <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ type.name }}</span>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                        :class="type.is_expense ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'"
                    >{{ type.is_expense ? 'Expense' : 'Income' }}</span>
                </div>

                <!-- Rows: one per subtype, or one for the type itself -->
                <div class="divide-y divide-slate-50 dark:divide-slate-700/60">
                    <div
                        v-for="row in typeRows(type)" :key="row.key"
                        class="flex items-center gap-3 px-5 py-2.5"
                    >
                        <!-- Subtype label (only when type has subtypes) -->
                        <span v-if="row.subtypeId" class="w-32 shrink-0 text-sm text-slate-600 dark:text-slate-300 truncate">{{ row.label }}</span>

                        <!-- Amount -->
                        <input
                            v-model.number="amounts[row.key]"
                            type="number" min="0" step="1" placeholder="0"
                            class="h-8 w-28 shrink-0 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @keydown.enter="save(row)"
                        />

                        <!-- Note -->
                        <input
                            v-model="notes[row.key]"
                            type="text" placeholder="note…"
                            class="h-8 flex-1 min-w-0 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @keydown.enter="save(row)"
                        />

                        <!-- Save / status -->
                        <div class="shrink-0 w-14 flex justify-end">
                            <span v-if="saved[row.key]" class="text-xs font-medium text-emerald-500">Saved ✓</span>
                            <button
                                v-else
                                @click="save(row)"
                                :disabled="saving[row.key]"
                                class="px-2.5 py-0.5 text-xs font-medium rounded-md transition-colors disabled:opacity-40"
                                :class="existing[row.key]
                                    ? 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200'
                                    : 'bg-indigo-600 hover:bg-indigo-500 text-white'"
                            >{{ existing[row.key] ? 'Update' : 'Add' }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="visibleTypes.length === 0" class="text-center py-16 text-slate-400 text-sm">
                No types configured. Add some in
                <RouterLink to="/cashflow/settings" class="text-indigo-500 hover:underline">Settings</RouterLink>.
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';

const now   = new Date();
const year  = ref(now.getFullYear());
const month = ref(now.getMonth() + 1);

const types   = ref([]);
const loading = ref(true);

// Per-row state keyed by rowKey
const amounts = reactive({});
const notes   = reactive({});
const existing = reactive({}); // rowKey → record id (or null)
const saving  = reactive({});
const saved   = reactive({});

// ── Helpers ───────────────────────────────────────────────────────────────────

function rowKey(typeId, subtypeId) {
    return `${typeId}-${subtypeId ?? 'x'}`;
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

const visibleTypes = computed(() =>
    types.value.filter(t => !t.is_hidden)
);

function visibleSubtypes(type) {
    return type.subtypes.filter(s => !s.is_hidden);
}

function typeRows(type) {
    const subs = visibleSubtypes(type);
    if (subs.length > 0) {
        return subs.map(s => ({ key: rowKey(type.id, s.id), typeId: type.id, subtypeId: s.id, label: s.name }));
    }
    return [{ key: rowKey(type.id, null), typeId: type.id, subtypeId: null, label: type.name }];
}

// ── Data loading ──────────────────────────────────────────────────────────────

function initRows(recordList) {
    // Reset state for all rows
    for (const type of types.value) {
        for (const row of typeRows(type)) {
            amounts[row.key] = 0;
            notes[row.key]   = '';
            existing[row.key] = null;
            saving[row.key]  = false;
            saved[row.key]   = false;
        }
    }
    // Pre-fill from existing records
    for (const rec of recordList) {
        const key = rowKey(rec.type_id, rec.subtype_id);
        amounts[key]  = Number(rec.amount);
        notes[key]    = rec.note ?? '';
        existing[key] = rec.id;
    }
}

async function loadData() {
    loading.value = true;
    try {
        const [typesRes, recordsRes] = await Promise.all([
            api.get('/cashflow/settings/types'),
            api.get('/cashflow/records', { params: { year: year.value, month: month.value } }),
        ]);
        types.value = typesRes.data;
        initRows(recordsRes.data);
    } finally {
        loading.value = false;
    }
}

async function reloadRecords() {
    const { data } = await api.get('/cashflow/records', { params: { year: year.value, month: month.value } });
    initRows(data);
}

watch([year, month], reloadRecords);

// ── Save ──────────────────────────────────────────────────────────────────────

async function save(row) {
    saving[row.key] = true;
    saved[row.key]  = false;
    const amount    = amounts[row.key] || 0;
    const note      = notes[row.key] || null;
    const recordedAt = `${year.value}-${String(month.value).padStart(2, '0')}-01`;

    try {
        if (existing[row.key]) {
            if (amount === 0) {
                await api.delete(`/cashflow/records/${existing[row.key]}`);
                existing[row.key] = null;
                amounts[row.key]  = 0;
                notes[row.key]    = '';
            } else {
                const { data } = await api.patch(`/cashflow/records/${existing[row.key]}`, { amount, note });
                existing[row.key] = data.id;
            }
        } else if (amount > 0) {
            const { data } = await api.post('/cashflow/records', {
                recorded_at: recordedAt,
                type_id:     row.typeId,
                subtype_id:  row.subtypeId ?? null,
                amount,
                note,
            });
            existing[row.key] = data.id;
        }
        saved[row.key] = true;
        setTimeout(() => { saved[row.key] = false; }, 1500);
    } finally {
        saving[row.key] = false;
    }
}

onMounted(loadData);
</script>
