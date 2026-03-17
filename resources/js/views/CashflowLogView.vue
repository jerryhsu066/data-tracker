<template>
    <div class="max-w-2xl space-y-4">
    <!-- Header + Save -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Log Cashflow</h1>
        <div class="flex items-center gap-3">
            <span v-if="savedIndicator" class="text-sm font-medium text-emerald-500 dark:text-emerald-400">Saved ✓</span>
            <button
                @click="saveAll"
                :disabled="saving"
                class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
            >{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
    </div>

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
            <div class="flex items-center gap-2 px-5 py-2.5 bg-slate-50 dark:bg-slate-700/40 border-b border-slate-100 dark:border-slate-700">
                <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ type.name }}</span>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                    :class="type.is_expense
                        ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'"
                >{{ type.is_expense ? 'Expense' : 'Income' }}</span>
            </div>

            <!-- Subtype sections (or bare rows if no subtypes) -->
            <template v-for="section in typeSections(type)" :key="section.key">

                <!-- Subtype label row (only when type has subtypes) -->
                <div v-if="section.subtypeId" class="flex items-center gap-2 px-5 pt-2.5 pb-1">
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ section.label }}</span>
                </div>

                <!-- Entry rows -->
                <div
                    v-for="row in section.rows" :key="row.tempId"
                    class="flex items-center gap-2 px-5 py-1.5"
                >
                    <input
                        v-model.number="row.amount"
                        type="number" min="0" step="1" placeholder="0"
                        class="h-8 w-28 shrink-0 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'blur-sm select-none': privacy.hidden.value && sectionIsPrivate(type, section) }"
                    />
                    <input
                        v-model="row.note"
                        type="text" placeholder="note…"
                        class="h-8 flex-1 min-w-0 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400 dark:text-slate-500 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <button
                        @click="removeRow(section, row)"
                        class="shrink-0 w-6 text-slate-300 dark:text-slate-600 hover:text-red-500 transition-colors text-base leading-none"
                        title="Remove"
                    >×</button>
                </div>

                <!-- Add entry row -->
                <div class="px-5 pb-2.5 pt-1">
                    <button
                        @click="addRow(section)"
                        class="text-xs text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors"
                    >+ add entry</button>
                </div>
            </template>
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
import { usePrivacy } from '../stores/privacy';

const privacy = usePrivacy();

const now   = new Date();
const year  = ref(now.getFullYear());
const month = ref(now.getMonth() + 1);

const types   = ref([]);
const loading = ref(true);
const saving         = ref(false);
const savedIndicator = ref(false);

// sections[sectionKey] = { key, typeId, subtypeId, label, rows: [{tempId, id, amount, note}] }
const sections  = reactive({});
// IDs of records that were removed from the UI and need to be deleted on save
const pendingDeletes = ref([]);

let tempCounter = 0;
function nextTempId() { return ++tempCounter; }

// ── Month navigation ──────────────────────────────────────────────────────────

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

// ── Types / sections ──────────────────────────────────────────────────────────

const visibleTypes = computed(() => types.value.filter(t => !t.is_disabled));

function sectionKey(typeId, subtypeId) {
    return `${typeId}-${subtypeId ?? 'x'}`;
}

function sectionIsPrivate(type, section) {
    if (!section.subtypeId) return type.is_private;
    const sub = type.subtypes.find(s => s.id === section.subtypeId);
    return sub ? sub.is_private : type.is_private;
}

function typeSections(type) {
    const subs = type.subtypes.filter(s => !s.is_disabled);
    if (subs.length > 0) {
        return subs.map(s => sections[sectionKey(type.id, s.id)] ?? { key: sectionKey(type.id, s.id), typeId: type.id, subtypeId: s.id, label: s.name, rows: [] });
    }
    const k = sectionKey(type.id, null);
    return [sections[k] ?? { key: k, typeId: type.id, subtypeId: null, label: type.name, rows: [] }];
}

// ── Data loading ──────────────────────────────────────────────────────────────

function buildSections(typeList, recordList) {
    // Clear existing
    for (const k of Object.keys(sections)) delete sections[k];
    pendingDeletes.value = [];

    // Init one section per visible type/subtype
    for (const type of typeList) {
        if (type.is_disabled) continue;
        const subs = type.subtypes.filter(s => !s.is_disabled);
        const keys = subs.length > 0
            ? subs.map(s => ({ key: sectionKey(type.id, s.id), subtypeId: s.id, label: s.name }))
            : [{ key: sectionKey(type.id, null), subtypeId: null, label: type.name }];

        for (const { key, subtypeId, label } of keys) {
            sections[key] = { key, typeId: type.id, subtypeId, label, rows: [] };
        }
    }

    // Populate with existing records
    for (const rec of recordList) {
        const k = sectionKey(rec.cashflow_type_id, rec.cashflow_subtype_id);
        if (!sections[k]) continue; // type/subtype hidden or removed
        sections[k].rows.push({ tempId: nextTempId(), id: rec.id, amount: Number(rec.amount), note: rec.note ?? '' });
    }

    // Ensure every section has at least one blank row
    for (const sec of Object.values(sections)) {
        if (sec.rows.length === 0) {
            sec.rows.push({ tempId: nextTempId(), id: null, amount: 0, note: '' });
        }
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
        buildSections(typesRes.data, recordsRes.data);
    } finally {
        loading.value = false;
    }
}

watch([year, month], async () => {
    const { data } = await api.get('/cashflow/records', { params: { year: year.value, month: month.value } });
    buildSections(types.value, data);
});

// ── Row management ────────────────────────────────────────────────────────────

function addRow(section) {
    section.rows.push({ tempId: nextTempId(), id: null, amount: 0, note: '' });
}

function removeRow(section, row) {
    section.rows = section.rows.filter(r => r.tempId !== row.tempId);
    if (row.id) pendingDeletes.value.push(row.id);
    // Keep at least one blank row
    if (section.rows.length === 0) {
        section.rows.push({ tempId: nextTempId(), id: null, amount: 0, note: '' });
    }
}

// ── Save ──────────────────────────────────────────────────────────────────────

async function saveAll() {
    saving.value = true;
    const creates = [];
    const updates = [];

    for (const sec of Object.values(sections)) {
        for (const row of sec.rows) {
            if (!row.amount || row.amount <= 0) continue;
            if (row.id) {
                updates.push({ id: row.id, amount: row.amount, note: row.note || null });
            } else {
                creates.push({ cashflow_type_id: sec.typeId, cashflow_subtype_id: sec.subtypeId ?? null, amount: row.amount, note: row.note || null });
            }
        }
    }

    try {
        const { data } = await api.post('/cashflow/records/bulk', {
            year:    year.value,
            month:   month.value,
            creates,
            updates,
            deletes: pendingDeletes.value,
        });

        // Assign IDs back to new rows
        let ci = 0;
        for (const sec of Object.values(sections)) {
            for (const row of sec.rows) {
                if (!row.id && row.amount > 0 && data.created[ci]) {
                    row.id = data.created[ci++].id;
                }
            }
        }
        pendingDeletes.value = [];
        savedIndicator.value = true;
        setTimeout(() => { savedIndicator.value = false; }, 2000);
    } finally {
        saving.value = false;
    }
}

onMounted(loadData);
</script>
