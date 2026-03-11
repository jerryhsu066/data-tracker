<template>
    <div class="max-w-4xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Cashflow</h1>
            <button
                @click="showForm = !showForm"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                :class="showForm
                    ? 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200'
                    : 'bg-indigo-600 hover:bg-indigo-500 text-white'"
            >{{ showForm ? '✕ Cancel' : '+ Add Record' }}</button>
        </div>

        <!-- Month navigation -->
        <div class="flex items-center gap-3 mb-6">
            <button @click="prevMonth" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">‹</button>
            <span class="text-base font-semibold text-slate-700 dark:text-slate-200 w-32 text-center">{{ monthLabel }}</span>
            <button @click="nextMonth" :disabled="isCurrentMonth" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">›</button>
        </div>

        <!-- Add form -->
        <div v-if="showForm" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 mb-6">
            <form @submit.prevent="addRecord" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Type</label>
                    <select v-model="form.type_id" class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>Select type…</option>
                        <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <p class="h-[1.1rem] text-xs text-red-500">{{ formErrors.type_id?.[0] ?? '' }}</p>
                </div>
                <div v-if="selectedType?.subtypes?.length">
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">{{ selectedType.name }}</label>
                    <select v-model="form.subtype_id" class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>Select…</option>
                        <option v-for="s in selectedType.subtypes" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p class="h-[1.1rem] text-xs text-red-500">{{ formErrors.subtype_id?.[0] ?? '' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Amount</label>
                    <input
                        v-model.number="form.amount"
                        type="number" min="0" step="1" placeholder="0"
                        class="h-9 w-36 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ formErrors.amount?.[0] ?? '' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Date</label>
                    <input
                        v-model="form.recorded_at"
                        type="date"
                        class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p class="h-[1.1rem]"></p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Note</label>
                    <input
                        v-model="form.note"
                        type="text" placeholder="optional"
                        class="h-9 w-40 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p class="h-[1.1rem]"></p>
                </div>
                <div>
                    <label class="invisible block text-xs mb-1">_</label>
                    <button type="submit" :disabled="submitting" class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors">
                        {{ submitting ? '…' : 'Add' }}
                    </button>
                    <p class="h-[1.1rem]"></p>
                </div>
            </form>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Summary cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Income</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ hidden ? '••••' : fmt(totalIncome) }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Expenses</p>
                    <p class="text-2xl font-bold text-red-500 mt-1">{{ hidden ? '••••' : fmt(totalExpenses) }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Net</p>
                    <p class="text-2xl font-bold mt-1" :class="net >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-500'">
                        {{ hidden ? '••••' : fmt(net) }}
                    </p>
                </div>
            </div>

            <!-- Records table -->
            <div v-if="records.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm text-slate-400">
                No records for this month.
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Type</th>
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Subtype</th>
                                <th class="text-right px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Amount</th>
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Note</th>
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Date</th>
                                <th class="w-8 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                            <template v-for="rec in records" :key="rec.id">
                                <!-- Edit row -->
                                <tr v-if="editingId === rec.id" class="bg-slate-50 dark:bg-slate-700/30">
                                    <td colspan="6" class="px-4 py-3">
                                        <div class="flex flex-wrap gap-3 items-end">
                                            <div>
                                                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Type</label>
                                                <select v-model="editForm.type_id" class="h-8 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                    <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                                                </select>
                                            </div>
                                            <div v-if="editSelectedType?.subtypes?.length">
                                                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">{{ editSelectedType.name }}</label>
                                                <select v-model="editForm.subtype_id" class="h-8 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                    <option :value="null">None</option>
                                                    <option v-for="s in editSelectedType.subtypes" :key="s.id" :value="s.id">{{ s.name }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Amount</label>
                                                <input v-model.number="editForm.amount" type="number" min="0" step="1" class="h-8 w-32 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Date</label>
                                                <input v-model="editForm.recorded_at" type="date" class="h-8 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Note</label>
                                                <input v-model="editForm.note" type="text" placeholder="optional" class="h-8 w-36 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                            </div>
                                            <div class="ml-auto flex gap-2">
                                                <button @click="saveEdit" :disabled="saving" class="h-8 px-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors">{{ saving ? '…' : 'Save' }}</button>
                                                <button
                                                    @click="confirmingDelete ? deleteRecord(rec.id) : (confirmingDelete = true)"
                                                    class="h-8 px-3 text-white text-sm font-medium rounded-md transition-colors"
                                                    :class="confirmingDelete ? 'bg-red-600 hover:bg-red-700 animate-pulse' : 'bg-red-500 hover:bg-red-600'"
                                                >{{ confirmingDelete ? 'Confirm?' : 'Delete' }}</button>
                                                <button @click="cancelEdit" class="h-8 px-3 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">Cancel</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Display row -->
                                <tr v-else class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="typeBadgeClass(rec.type_id)">
                                            {{ typeNameById(rec.type_id) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                        {{ subtypeNameById(rec.subtype_id) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium" :class="isExpenseById(rec.type_id) ? 'text-slate-900 dark:text-slate-100' : 'text-emerald-600'">
                                        {{ hidden ? '••••' : (isExpenseById(rec.type_id) ? '-' : '+') + fmt(rec.amount) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-400 dark:text-slate-500">{{ rec.note ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ rec.recorded_at.slice(0, 10) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="startEdit(rec)" class="text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors text-sm leading-none" title="Edit">✎</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
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

const records = ref([]);
const types   = ref([]);
const loading = ref(true);

const showForm   = ref(false);
const submitting = ref(false);
const formErrors = ref({});

const editingId       = ref(null);
const editForm        = ref({});
const saving          = ref(false);
const confirmingDelete = ref(false);

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

// Type lookup helpers
const typeMap    = computed(() => Object.fromEntries(types.value.map(t => [t.id, t])));
const subtypeMap = computed(() => {
    const m = {};
    for (const t of types.value) {
        for (const s of t.subtypes) m[s.id] = s;
    }
    return m;
});

function typeNameById(id)    { return typeMap.value[id]?.name ?? '—'; }
function subtypeNameById(id) { return id ? (subtypeMap.value[id]?.name ?? '—') : '—'; }
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

const selectedType     = computed(() => typeMap.value[form.value.type_id] ?? null);
const editSelectedType = computed(() => typeMap.value[editForm.value.type_id] ?? null);

const totalIncome   = computed(() => records.value.filter(r => !isExpenseById(r.type_id)).reduce((s, r) => s + Number(r.amount), 0));
const totalExpenses = computed(() => records.value.filter(r => isExpenseById(r.type_id)).reduce((s, r) => s + Number(r.amount), 0));
const net           = computed(() => totalIncome.value - totalExpenses.value);

function fmt(v) {
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

async function loadRecords() {
    loading.value = true;
    try {
        const { data } = await api.get('/cashflow/records', { params: { year: year.value, month: month.value } });
        records.value = data;
    } finally {
        loading.value = false;
    }
}

watch([year, month], () => {
    form.value.recorded_at = firstOfMonth();
    loadRecords();
});

// Reset subtype when type changes
watch(() => form.value.type_id, () => { form.value.subtype_id = ''; });
watch(() => editForm.value.type_id, () => { editForm.value.subtype_id = null; });

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
        records.value.push(data);
        form.value = defaultForm();
        showForm.value = false;
    } catch (e) {
        if (e.response?.status === 422) formErrors.value = e.response.data.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

function startEdit(rec) {
    confirmingDelete.value = false;
    editingId.value = rec.id;
    editForm.value = {
        type_id:     rec.type_id,
        subtype_id:  rec.subtype_id ?? null,
        amount:      Number(rec.amount),
        recorded_at: rec.recorded_at.slice(0, 10),
        note:        rec.note ?? '',
    };
}

function cancelEdit() {
    editingId.value = null;
    confirmingDelete.value = false;
}

async function saveEdit() {
    saving.value = true;
    try {
        const id = editingId.value;
        const { data } = await api.patch(`/cashflow/records/${id}`, {
            type_id:     editForm.value.type_id,
            subtype_id:  editForm.value.subtype_id,
            amount:      editForm.value.amount,
            recorded_at: editForm.value.recorded_at,
            note:        editForm.value.note || null,
        });
        const idx = records.value.findIndex(r => r.id === id);
        if (idx !== -1) records.value[idx] = data;
        cancelEdit();
    } finally {
        saving.value = false;
    }
}

async function deleteRecord(id) {
    await api.delete(`/cashflow/records/${id}`);
    records.value = records.value.filter(r => r.id !== id);
    cancelEdit();
}

onMounted(async () => {
    const [, typesRes] = await Promise.all([
        loadRecords(),
        api.get('/cashflow/settings/types'),
    ]);
    types.value = typesRes.data;
});
</script>
