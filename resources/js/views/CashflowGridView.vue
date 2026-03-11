<template>
    <div class="max-w-full px-4 py-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-6">Monthly Overview</h1>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- No settings warning -->
            <div v-if="columns.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
                <p class="text-slate-400 text-lg">No categories configured.</p>
                <p class="text-slate-400 text-sm mt-1">Add companies and banks in
                    <RouterLink to="/cashflow/settings" class="text-indigo-500 hover:underline">Settings</RouterLink>
                    first.
                </p>
            </div>

            <template v-else>
                <div v-for="section in sections" :key="section.year" class="mb-4">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-x-auto">
                        <table class="text-sm border-collapse w-full">
                            <thead>
                                <tr
                                    class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 transition-colors"
                                    :class="section.year !== currentYear ? 'cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-600/50 select-none' : ''"
                                    @click="section.year !== currentYear && (section.expanded = !section.expanded)"
                                >
                                    <th class="text-left px-4 py-4 sticky left-0 bg-slate-50 dark:bg-slate-700/50 z-10 min-w-28">
                                        <span class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ section.year }}</span>
                                    </th>
                                    <th
                                        v-for="col in columns" :key="col.key"
                                        class="px-3 py-3 font-medium text-right min-w-32 whitespace-nowrap"
                                        :class="colHeaderClass(col.type)"
                                    >
                                        <div class="text-xs uppercase tracking-wide opacity-70">{{ typeLabel(col.type) }}</div>
                                        <div>{{ col.label }}</div>
                                    </th>
                                    <th class="px-4 py-3 font-medium text-right text-slate-500 dark:text-slate-400 min-w-32 whitespace-nowrap">
                                        Net
                                        <span v-if="section.year !== currentYear" class="ml-1 text-xs opacity-60">{{ section.expanded ? '▲' : '▼' }}</span>
                                    </th>
                                </tr>
                                <!-- Totals row — always visible, acts like a summary just below the header -->
                                <tr class="bg-slate-50 dark:bg-slate-700/30 font-semibold border-b-2 border-slate-200 dark:border-slate-600">
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400 sticky left-0 bg-slate-50 dark:bg-slate-700/30 z-10 text-sm">Total</td>
                                    <td v-for="col in columns" :key="col.key" class="px-3 py-3 text-right" :class="colHeaderClass(col.type)">
                                        {{ hidden ? '••••' : (colTotal(col, section) === 0 ? '—' : fmt(colTotal(col, section))) }}
                                    </td>
                                    <td class="px-4 py-3 text-right" :class="sectionNet(section) >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-500'">
                                        {{ hidden ? '••••' : fmt(sectionNet(section)) }}
                                    </td>
                                </tr>
                            </thead>
                            <tbody v-if="section.expanded" class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr
                                    v-for="row in getRows(section)" :key="row.month"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors"
                                    :class="row.month === currentMonth && section.year === currentYear ? 'ring-1 ring-inset ring-indigo-300 dark:ring-indigo-700' : ''"
                                >
                                    <td class="px-4 py-2 font-medium text-slate-600 dark:text-slate-400 sticky left-0 bg-white dark:bg-slate-800 z-10">
                                        {{ monthName(row.month) }}
                                    </td>
                                    <td v-for="col in columns" :key="col.key" class="px-3 py-2 text-right">
                                        <div
                                            v-if="editingCell?.year === section.year && editingCell?.rowMonth === row.month && editingCell?.colKey === col.key"
                                            class="flex justify-end"
                                        >
                                            <input
                                                ref="cellInput"
                                                v-model.number="editingValue"
                                                type="number" min="0" step="1"
                                                class="w-28 h-7 rounded border border-indigo-400 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                                @keydown.enter="commitCell"
                                                @keydown.escape="cancelCell"
                                                @blur="commitCell"
                                            />
                                        </div>
                                        <button
                                            v-else
                                            @click="startEdit(section, row, col)"
                                            class="w-full text-right px-1 py-0.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                            :class="cellValueClass(row, col)"
                                        >
                                            {{ hidden ? '••••' : cellDisplay(row, col) }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold" :class="rowNet(row) >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-500'">
                                        {{ hidden ? '••••' : (rowNet(row) === 0 ? '—' : fmt(rowNet(row))) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import { usePrivacy } from '../stores/privacy';

const { hidden } = usePrivacy();

const now          = new Date();
const currentYear  = now.getFullYear();
const currentMonth = now.getMonth() + 1;

// How many years back to search for records
const MAX_SEARCH_YEARS = 6;

const companies = ref([]);
const banks     = ref([]);
const loading   = ref(true);
const sections  = ref([]);

// Pre-fetched records keyed by year — kept outside onMounted so commitCell can extend sections
const prefetchedRecords = {};

const editingCell  = ref(null); // { year, rowMonth, colKey }
const editingValue = ref(0);
const cellInput    = ref(null);

// ── Columns ───────────────────────────────────────────────────────────────────

const columns = computed(() => {
    const cols = [];
    for (const c of companies.value) {
        cols.push({ key: `income_${c.id}`, type: 'income', label: c.name, company_id: c.id, bank_id: null });
    }
    cols.push({ key: 'rent', type: 'rent', label: 'Rent', company_id: null, bank_id: null });
    for (const b of banks.value) {
        cols.push({ key: `credit_card_${b.id}`, type: 'credit_card', label: b.name, company_id: null, bank_id: b.id });
    }
    cols.push({ key: 'other', type: 'other', label: 'Other', company_id: null, bank_id: null });
    return cols;
});

// ── Per-section helpers ───────────────────────────────────────────────────────

function getRows(section) {
    const maxMonth = section.year === currentYear ? currentMonth : 12;
    return Array.from({ length: maxMonth }, (_, i) => {
        const m = maxMonth - i;
        const cells = {};
        for (const col of columns.value) {
            cells[col.key] = section.records.find(r => {
                const rm = new Date(r.recorded_at).getMonth() + 1;
                if (rm !== m) return false;
                if (r.type !== col.type) return false;
                if (col.type === 'income')      return r.company_id === col.company_id;
                if (col.type === 'credit_card') return r.bank_id    === col.bank_id;
                return true;
            }) ?? null;
        }
        return { month: m, cells };
    });
}

function rowNet(row) {
    return columns.value.reduce((net, col) => {
        const rec = row.cells[col.key];
        if (!rec) return net;
        return net + (col.type === 'income' ? Number(rec.amount) : -Number(rec.amount));
    }, 0);
}

function colTotal(col, section) {
    return getRows(section).reduce((sum, row) => {
        const rec = row.cells[col.key];
        return sum + (rec ? Number(rec.amount) : 0);
    }, 0);
}

function sectionNet(section) {
    return getRows(section).reduce((sum, row) => sum + rowNet(row), 0);
}

// ── Display helpers ───────────────────────────────────────────────────────────

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function monthName(m) { return MONTHS[m - 1]; }
function typeLabel(type) {
    return { income: 'Income', rent: 'Rent', credit_card: 'Card', other: 'Other' }[type];
}
function colHeaderClass(type) {
    return {
        income:      'text-emerald-700 dark:text-emerald-400',
        rent:        'text-orange-600 dark:text-orange-400',
        credit_card: 'text-blue-600 dark:text-blue-400',
        other:       'text-slate-500 dark:text-slate-400',
    }[type];
}
function cellValueClass(row, col) {
    const rec = row.cells[col.key];
    if (!rec) return 'text-slate-300 dark:text-slate-600 text-xs';
    return col.type === 'income'
        ? 'text-emerald-600 dark:text-emerald-400 font-medium'
        : 'text-slate-700 dark:text-slate-300';
}
function cellDisplay(row, col) {
    const rec = row.cells[col.key];
    return rec ? fmt(rec.amount) : '—';
}
function fmt(v) {
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ── Section management ────────────────────────────────────────────────────────

// After a new record is created, ensure a section exists for the year before
// the oldest section that now has records (so the user can enter prior-year data).
function syncSections() {
    const withRecords = sections.value.filter(s => s.records.length > 0);
    if (withRecords.length === 0) return;

    const oldestYear    = Math.min(...withRecords.map(s => s.year));
    const needed        = oldestYear - 1;
    const minExisting   = Math.min(...sections.value.map(s => s.year));

    if (needed < minExisting) {
        sections.value.push({
            year:     needed,
            expanded: false,
            records:  prefetchedRecords[needed] ?? [],
        });
    }
}

// ── Cell editing ──────────────────────────────────────────────────────────────

function startEdit(section, row, col) {
    const rec = row.cells[col.key];
    editingCell.value  = { year: section.year, rowMonth: row.month, colKey: col.key };
    editingValue.value = rec ? Number(rec.amount) : 0;
    nextTick(() => {
        const el = Array.isArray(cellInput.value) ? cellInput.value[0] : cellInput.value;
        el?.focus();
        el?.select();
    });
}

function cancelCell() {
    editingCell.value = null;
}

async function commitCell() {
    if (!editingCell.value) return;
    const { year, rowMonth, colKey } = editingCell.value;
    editingCell.value = null;

    const section  = sections.value.find(s => s.year === year);
    const col      = columns.value.find(c => c.key === colKey);
    const row      = getRows(section).find(r => r.month === rowMonth);
    if (!section || !col || !row) return;

    const existing = row.cells[colKey];
    const amount   = editingValue.value;

    if (!amount && !existing) return;

    const recordedAt = `${year}-${String(rowMonth).padStart(2, '0')}-01`;

    if (existing) {
        if (amount === Number(existing.amount)) return;
        if (!amount) {
            await api.delete(`/cashflow/records/${existing.id}`);
            const idx = section.records.findIndex(r => r.id === existing.id);
            if (idx !== -1) section.records.splice(idx, 1);
        } else {
            const { data } = await api.patch(`/cashflow/records/${existing.id}`, { amount });
            const idx = section.records.findIndex(r => r.id === existing.id);
            if (idx !== -1) section.records[idx] = data;
        }
    } else {
        if (!amount) return;
        const { data } = await api.post('/cashflow/records', {
            recorded_at: recordedAt,
            type:        col.type,
            amount,
            company_id:  col.company_id ?? null,
            bank_id:     col.bank_id ?? null,
        });
        section.records.push(data);
        syncSections();
    }
}

// ── Mount ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
    try {
        const yearsToFetch = Array.from({ length: MAX_SEARCH_YEARS }, (_, i) => currentYear - i);

        const [cRes, bRes, ...recordsResults] = await Promise.all([
            api.get('/cashflow/settings/companies'),
            api.get('/cashflow/settings/banks'),
            ...yearsToFetch.map(y => api.get('/cashflow/records', { params: { year: y } })),
        ]);

        companies.value = cRes.data;
        banks.value     = bRes.data;

        yearsToFetch.forEach((y, i) => {
            prefetchedRecords[y] = recordsResults[i].data;
        });

        // Find oldest year with any records
        let oldestWithRecords = null;
        for (let i = yearsToFetch.length - 1; i >= 0; i--) {
            if (prefetchedRecords[yearsToFetch[i]].length > 0) {
                oldestWithRecords = yearsToFetch[i];
                break;
            }
        }

        // Show from currentYear down to one year before the oldest record
        const stopYear = oldestWithRecords ? oldestWithRecords - 1 : currentYear;

        sections.value = [];
        for (let y = currentYear; y >= stopYear; y--) {
            sections.value.push({
                year:     y,
                expanded: y === currentYear,
                records:  prefetchedRecords[y] ?? [],
            });
        }
    } finally {
        loading.value = false;
    }
});
</script>
