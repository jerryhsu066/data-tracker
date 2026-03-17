<template>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-6">Monthly Overview</h1>

    <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

    <template v-else>
        <!-- No types configured -->
        <div v-if="columns.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
            <p class="text-slate-400 text-lg">No types configured.</p>
            <p class="text-slate-400 text-sm mt-1">Add types in
                <RouterLink to="/cashflow/settings" class="text-indigo-500 hover:underline">Settings</RouterLink>
                first.
            </p>
        </div>

        <template v-else>
            <div v-for="section in sections" :key="section.year" class="mb-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-x-auto">
                    <table class="text-sm border-collapse w-full">
                        <thead>
                            <!-- Year + column headers (clickable for past years) -->
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
                                    :class="colHeaderClass(col)"
                                >
                                    <div class="text-xs uppercase tracking-wide opacity-70">{{ col.typeLabel }}</div>
                                    <div>{{ col.label }}<span v-if="col.merged" class="ml-1 opacity-50 text-xs normal-case tracking-normal">∑</span></div>
                                </th>
                                <th class="px-4 py-3 font-medium text-right text-slate-500 dark:text-slate-400 min-w-32 whitespace-nowrap">
                                    Net
                                    <span v-if="section.year !== currentYear" class="ml-1 text-xs opacity-60">{{ section.expanded ? '▲' : '▼' }}</span>
                                </th>
                            </tr>
                            <!-- Totals row — always visible -->
                            <tr class="bg-slate-50 dark:bg-slate-700/30 font-semibold border-b-2 border-slate-200 dark:border-slate-600">
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 sticky left-0 bg-slate-50 dark:bg-slate-700/30 z-10 text-sm">Total</td>
                                <td v-for="col in columns" :key="col.key" class="px-3 py-3 text-right" :class="colHeaderClass(col)">
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

const MAX_SEARCH_YEARS = 6;

const types   = ref([]);
const loading = ref(true);
const sections = ref([]);

const prefetchedRecords = {};

const editingCell  = ref(null);
const editingValue = ref(0);
const cellInput    = ref(null);

// ── Columns ───────────────────────────────────────────────────────────────────
// A type with subtypes becomes one column per subtype.
// A type without subtypes becomes one column for the type itself.

const columns = computed(() => {
    const cols = [];
    for (const type of types.value) {
        if (type.is_hidden) continue;

        const visibleSubs = type.subtypes.filter(s => !s.is_hidden);

        if (visibleSubs.length > 0) {
            if (type.merge_subtypes) {
                // One merged column summing all visible subtypes
                cols.push({
                    key:          `${type.id}-merged`,
                    typeId:       type.id,
                    subtypeId:    null,
                    subtypeIds:   visibleSubs.map(s => s.id), // for aggregation
                    typeLabel:    type.name,
                    label:        type.name,
                    isExpense:    type.is_expense,
                    merged:       true,
                });
            } else {
                for (const sub of visibleSubs) {
                    cols.push({
                        key:       `${type.id}-${sub.id}`,
                        typeId:    type.id,
                        subtypeId: sub.id,
                        typeLabel: type.name,
                        label:     sub.name,
                        isExpense: type.is_expense,
                        merged:    false,
                    });
                }
            }
        } else if (type.subtypes.length === 0) {
            // Type with no subtypes at all
            cols.push({
                key:       `${type.id}`,
                typeId:    type.id,
                subtypeId: null,
                typeLabel: type.name,
                label:     type.name,
                isExpense: type.is_expense,
                merged:    false,
            });
        }
    }
    return cols;
});

// ── Per-section helpers ───────────────────────────────────────────────────────

function getRows(section) {
    const maxMonth = section.year === currentYear ? currentMonth : 12;
    return Array.from({ length: maxMonth }, (_, i) => {
        const m = maxMonth - i;
        const cells = {};
        for (const col of columns.value) {
            if (col.merged) {
                // Sum all visible subtype records for this type/month
                const sum = section.records.reduce((total, r) => {
                    const rm = new Date(r.recorded_at).getMonth() + 1;
                    if (rm !== m || r.cashflow_type_id !== col.typeId) return total;
                    if (!col.subtypeIds.includes(r.cashflow_subtype_id)) return total;
                    return total + Number(r.amount);
                }, 0);
                cells[col.key] = sum > 0 ? { amount: sum, _merged: true } : null;
            } else {
                cells[col.key] = section.records.find(r => {
                    const rm = new Date(r.recorded_at).getMonth() + 1;
                    if (rm !== m) return false;
                    if (r.cashflow_type_id !== col.typeId) return false;
                    return col.subtypeId !== null
                        ? r.cashflow_subtype_id === col.subtypeId
                        : r.cashflow_subtype_id === null;
                }) ?? null;
            }
        }
        return { month: m, cells };
    });
}

function rowNet(row) {
    return columns.value.reduce((net, col) => {
        const rec = row.cells[col.key];
        if (!rec) return net;
        return net + (col.isExpense ? -Number(rec.amount) : Number(rec.amount));
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

const EXPENSE_COLORS = [
    'text-orange-600 dark:text-orange-400',
    'text-blue-600 dark:text-blue-400',
    'text-purple-600 dark:text-purple-400',
    'text-rose-600 dark:text-rose-400',
    'text-amber-600 dark:text-amber-400',
    'text-cyan-600 dark:text-cyan-400',
];
// Stable color per type based on its index among expense types
const expenseTypeIds = computed(() =>
    types.value.filter(t => t.is_expense).map(t => t.id)
);
function colHeaderClass(col) {
    if (!col.isExpense) return 'text-emerald-700 dark:text-emerald-400';
    const idx = expenseTypeIds.value.indexOf(col.typeId);
    return EXPENSE_COLORS[idx % EXPENSE_COLORS.length] ?? 'text-slate-500 dark:text-slate-400';
}
function cellValueClass(row, col) {
    const rec = row.cells[col.key];
    const base = col.merged ? 'cursor-default' : '';
    if (!rec) return `${base} text-slate-300 dark:text-slate-600 text-xs`;
    const color = col.isExpense ? 'text-slate-700 dark:text-slate-300' : 'text-emerald-600 dark:text-emerald-400 font-medium';
    return `${base} ${color} ${col.merged ? 'font-semibold' : ''}`;
}
function cellDisplay(row, col) {
    const rec = row.cells[col.key];
    return rec ? fmt(rec.amount) : '—';
}
function fmt(v) {
    return Number(v).toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ── Section management ────────────────────────────────────────────────────────

function syncSections() {
    const withRecords = sections.value.filter(s => s.records.length > 0);
    if (withRecords.length === 0) return;

    const oldestYear  = Math.min(...withRecords.map(s => s.year));
    const needed      = oldestYear - 1;
    const minExisting = Math.min(...sections.value.map(s => s.year));

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
    if (col.merged) return; // merged columns are read-only aggregates
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

    const section = sections.value.find(s => s.year === year);
    const col     = columns.value.find(c => c.key === colKey);
    const row     = getRows(section).find(r => r.month === rowMonth);
    if (!section || !col || !row) return;

    const existing   = row.cells[colKey];
    const amount     = editingValue.value;
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
            cashflow_type_id:    col.typeId,
            cashflow_subtype_id: col.subtypeId ?? null,
            amount,
        });
        section.records.push(data);
        syncSections();
    }
}

// ── Mount ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
    try {
        const yearsToFetch = Array.from({ length: MAX_SEARCH_YEARS }, (_, i) => currentYear - i);

        const [typesRes, ...recordsResults] = await Promise.all([
            api.get('/cashflow/settings/types'),
            ...yearsToFetch.map(y => api.get('/cashflow/records', { params: { year: y } })),
        ]);

        types.value = typesRes.data;

        yearsToFetch.forEach((y, i) => {
            prefetchedRecords[y] = recordsResults[i].data;
        });

        let oldestWithRecords = null;
        for (let i = yearsToFetch.length - 1; i >= 0; i--) {
            if (prefetchedRecords[yearsToFetch[i]].length > 0) {
                oldestWithRecords = yearsToFetch[i];
                break;
            }
        }

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
