<template>
    <div class="max-w-4xl space-y-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Flight Settings</h1>

        <!-- Import / Export -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Import / Export</h2>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Export Flights</p>
                <div class="flex gap-2">
                    <button @click="doExport('csv')" class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">CSV</button>
                    <button @click="doExport('json')" class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">JSON</button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Import Flights</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">CSV or JSON — columns: flight_date, airline, flight_number, departure_airport, arrival_airport, departure_time, arrival_time, aircraft_type, seat_class, seat_number, booking_reference, ticket_price, tail_number, notes</p>
                <div class="flex items-center gap-3 flex-wrap">
                    <label class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors cursor-pointer flex items-center"
                           :class="previewing ? 'opacity-50 pointer-events-none' : ''">
                        {{ previewing ? 'Analyzing…' : 'Choose File' }}
                        <input ref="fileInputRef" type="file" accept=".csv,.json" class="hidden" @change="onFileSelected" :disabled="previewing || importing" />
                    </label>
                    <span v-if="importing" class="text-sm text-slate-400">Importing…</span>
                    <span v-if="importResult" class="text-sm font-medium text-emerald-500">Imported {{ importResult.imported }} row(s){{ importResult.skipped.length ? `, ${importResult.skipped.length} skipped` : '' }}</span>
                    <span v-if="importError" class="text-sm text-red-500">{{ importError }}</span>
                </div>
            </div>
        </div>

        <!-- API Keys -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">API Keys (Optional)</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Flight lookup tries free sources first. Add API keys below to enable fallback sources when free lookup fails.
            </p>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        AviationStack API Key
                        <span v-if="settings.has_aviationstack_key" class="ml-2 text-xs text-emerald-500 font-normal">configured</span>
                    </label>
                    <div class="flex gap-2">
                        <input v-model="apiKeys.aviationstack_key" type="password" placeholder="Enter API key…"
                            class="h-9 flex-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <button v-if="settings.has_aviationstack_key" @click="removeKey('aviationstack_key')"
                            class="h-9 px-3 text-xs text-red-500 hover:text-red-700 font-medium">Remove</button>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Free tier: 100 requests/month — <a href="https://aviationstack.com/" target="_blank" class="text-indigo-500 hover:underline">aviationstack.com</a></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        AeroDataBox (RapidAPI) Key
                        <span v-if="settings.has_aerodatabox_key" class="ml-2 text-xs text-emerald-500 font-normal">configured</span>
                    </label>
                    <div class="flex gap-2">
                        <input v-model="apiKeys.aerodatabox_key" type="password" placeholder="Enter RapidAPI key…"
                            class="h-9 flex-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <button v-if="settings.has_aerodatabox_key" @click="removeKey('aerodatabox_key')"
                            class="h-9 px-3 text-xs text-red-500 hover:text-red-700 font-medium">Remove</button>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Free tier: 150 requests/month — <a href="https://rapidapi.com/aedbx-aedbx/api/aerodatabox" target="_blank" class="text-indigo-500 hover:underline">rapidapi.com</a></p>
                </div>

                <button @click="saveKeys" :disabled="savingKeys"
                    class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50">
                    {{ savingKeys ? 'Saving…' : 'Save API Keys' }}
                </button>
                <span v-if="keysSaved" class="text-sm text-emerald-500 ml-2">Saved</span>
            </div>
        </div>

        <!-- Import Preview Modal -->
        <ImportPreviewModal
            v-if="preview"
            :preview="preview"
            @confirm="doImport"
            @cancel="preview = null"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { downloadExport, previewImport, uploadImport } from '../utils/importExport';
import ImportPreviewModal from '../components/ImportPreviewModal.vue';

const fileInputRef = ref(null);
const previewing = ref(false);
const importing = ref(false);
const importResult = ref(null);
const importError = ref('');
const preview = ref(null);
let importFile = null;
let importFormat = '';

const settings = ref({ has_aviationstack_key: false, has_aerodatabox_key: false });
const apiKeys = ref({ aviationstack_key: '', aerodatabox_key: '' });
const savingKeys = ref(false);
const keysSaved = ref(false);

async function fetchSettings() {
    try {
        const { data } = await api.get('/flights/settings');
        settings.value = data;
    } catch {}
}

function doExport(format) {
    downloadExport(`/flights/export?format=${format}`, `flights.${format}`);
}

async function onFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;

    importFile = file;
    importFormat = file.name.endsWith('.json') ? 'json' : 'csv';
    importResult.value = null;
    importError.value = '';
    previewing.value = true;

    try {
        preview.value = await previewImport('/flights/import/preview', file, importFormat);
    } catch (err) {
        importError.value = err.response?.data?.message || 'Preview failed';
    } finally {
        previewing.value = false;
        if (fileInputRef.value) fileInputRef.value.value = '';
    }
}

async function doImport(skipDuplicates) {
    preview.value = null;
    importing.value = true;
    importError.value = '';

    try {
        importResult.value = await uploadImport('/flights/import', importFile, importFormat, { skip_duplicates: skipDuplicates });
    } catch (err) {
        importError.value = err.response?.data?.message || 'Import failed';
    } finally {
        importing.value = false;
    }
}

async function saveKeys() {
    savingKeys.value = true;
    keysSaved.value = false;
    try {
        const payload = {};
        if (apiKeys.value.aviationstack_key) payload.aviationstack_key = apiKeys.value.aviationstack_key;
        if (apiKeys.value.aerodatabox_key) payload.aerodatabox_key = apiKeys.value.aerodatabox_key;
        const { data } = await api.patch('/flights/settings', payload);
        settings.value = data;
        apiKeys.value = { aviationstack_key: '', aerodatabox_key: '' };
        keysSaved.value = true;
        setTimeout(() => { keysSaved.value = false; }, 2000);
    } catch {}
    finally {
        savingKeys.value = false;
    }
}

async function removeKey(key) {
    savingKeys.value = true;
    try {
        const { data } = await api.patch('/flights/settings', { [key]: null });
        settings.value = data;
    } finally {
        savingKeys.value = false;
    }
}

onMounted(fetchSettings);
</script>
