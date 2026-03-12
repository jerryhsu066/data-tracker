<template>
    <div class="max-w-lg mx-auto px-4 py-8 space-y-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Settings</h1>

        <!-- Import / Export -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Import / Export</h2>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Export Transactions</p>
                <div class="flex gap-2">
                    <button @click="doExport('csv')" class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">CSV</button>
                    <button @click="doExport('json')" class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">JSON</button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Import Transactions</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">CSV or JSON — columns: date, symbol, type, shares, price_per_share, handling_fee, transaction_tax, notes</p>
                <div class="flex items-center gap-3 flex-wrap">
                    <label class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors cursor-pointer flex items-center">
                        Choose File
                        <input type="file" accept=".csv,.json" class="hidden" @change="doImport" :disabled="importing" />
                    </label>
                    <span v-if="importing" class="text-sm text-slate-400">Importing…</span>
                    <span v-if="importResult" class="text-sm font-medium text-emerald-500">Imported {{ importResult.imported }} row(s){{ importResult.skipped.length ? `, ${importResult.skipped.length} skipped` : '' }}</span>
                    <span v-if="importError" class="text-sm text-red-500">{{ importError }}</span>
                </div>
            </div>
        </div>

        <!-- Brokerage Fees -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-6">
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Brokerage Fees</h2>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Handling Fee Discount</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">
                    Standard rate is 0.1425%. Enter your broker's discount (e.g. 40 for 40% off).
                    Effective rate: <span class="font-medium text-slate-600 dark:text-slate-300">{{ effectiveRate }}%</span>
                </p>
                <div class="flex items-center gap-3">
                    <input
                        v-model.number="discountPercent"
                        type="number" min="0" max="100" step="1"
                        class="border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="0"
                    />
                    <span class="text-sm text-slate-500 dark:text-slate-400">% off</span>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button @click="save" :disabled="saving"
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
                <span v-if="saved" class="text-sm text-emerald-600">Saved!</span>
                <span v-if="error" class="text-sm text-red-500">{{ error }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';
import { downloadExport, uploadImport } from '../utils/importExport';

const { state, updateUser } = useAuth();

const STANDARD_RATE = 0.1425;

const discountPercent = ref(Math.round(Number(state.user?.handling_fee_discount ?? 0) * 100));

onMounted(async () => {
    try {
        const { data } = await api.get('/stocks/settings');
        discountPercent.value = Math.round(Number(data.handling_fee_discount ?? 0) * 100);
    } catch {}
});
const saving = ref(false);
const saved = ref(false);
const error = ref('');

const effectiveRate = computed(() =>
    (STANDARD_RATE * (1 - discountPercent.value / 100)).toFixed(4)
);

// Import / Export
const importing    = ref(false);
const importResult = ref(null);
const importError  = ref('');

async function doExport(format) {
    await downloadExport(`/stocks/export?format=${format}`, `transactions.${format}`);
}

async function doImport(e) {
    const file = e.target.files[0];
    if (!file) return;
    importing.value = true;
    importResult.value = null;
    importError.value  = '';
    try {
        const format = file.name.endsWith('.json') ? 'json' : 'csv';
        importResult.value = await uploadImport('/stocks/import', file, format);
    } catch (err) {
        importError.value = err.response?.data?.message ?? 'Import failed.';
    } finally {
        importing.value = false;
        e.target.value = '';
    }
}

async function save() {
    saving.value = true;
    saved.value = false;
    error.value = '';
    try {
        const { data } = await api.patch('/stocks/settings', {
            handling_fee_discount: discountPercent.value / 100,
        });
        updateUser({ handling_fee_discount: data.handling_fee_discount });
        saved.value = true;
        setTimeout(() => { saved.value = false; }, 2000);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to save.';
    } finally {
        saving.value = false;
    }
}
</script>
