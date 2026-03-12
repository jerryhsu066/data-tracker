<template>
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Cashflow Settings</h1>

        <!-- Import / Export -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Import / Export</h2>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Export Records</p>
                <div class="flex gap-2">
                    <button @click="doExport('csv')" class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">CSV</button>
                    <button @click="doExport('json')" class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">JSON</button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Import Records</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">CSV or JSON — columns: year, month, type, subtype, amount, note</p>
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

        <!-- Add new type -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-3">Add New Type</h2>
            <form @submit.prevent="addType" class="flex gap-2 items-center flex-wrap">
                <input
                    v-model="newTypeName"
                    type="text" placeholder="Type name (e.g. Subscription)"
                    class="h-9 flex-1 min-w-40 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <div class="flex rounded-md overflow-hidden border border-slate-200 dark:border-slate-600 text-xs font-medium shrink-0">
                    <button type="button" @click="newTypeIsExpense = false"
                        class="px-2.5 py-1 transition-colors"
                        :class="!newTypeIsExpense ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                    >Income</button>
                    <button type="button" @click="newTypeIsExpense = true"
                        class="px-2.5 py-1 border-l border-slate-200 dark:border-slate-600 transition-colors"
                        :class="newTypeIsExpense ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                    >Expense</button>
                </div>
                <button
                    type="submit" :disabled="!newTypeName.trim()"
                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
                >Add Type</button>
            </form>
            <p class="h-[1.1rem] text-xs text-red-500 mt-1">{{ typeError }}</p>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <div
                v-for="type in types" :key="type.id"
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden transition-opacity"
                :class="{
                    'opacity-40': dragTypeId === type.id,
                    'ring-2 ring-indigo-400 dark:ring-indigo-500': dragOverTypeId === type.id,
                }"
                :draggable="!editingType && !editingSubtype"
                @dragstart="onTypeDragStart($event, type)"
                @dragover="onTypeDragOver($event, type)"
                @dragleave="onTypeDragLeave"
                @drop="onTypeDrop($event, type)"
                @dragend="onTypeDragEnd"
            >
                <!-- ── Type: view mode ── -->
                <template v-if="editingType?.id !== type.id">
                    <div class="flex items-center gap-3 px-3 py-3" :class="type.subtypes.length ? 'border-b border-slate-100 dark:border-slate-700' : ''">
                        <!-- grip handle -->
                        <span class="text-slate-300 dark:text-slate-600 cursor-grab active:cursor-grabbing shrink-0 select-none">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                            </svg>
                        </span>
                        <span class="flex-1 font-semibold text-slate-800 dark:text-slate-100">{{ type.name }}</span>
                        <span v-if="type.is_disabled" class="text-xs text-amber-500 dark:text-amber-400 font-medium">Disabled</span>
                        <span v-if="!type.is_private" class="text-xs text-sky-500 dark:text-sky-400 font-medium">Always visible</span>
                        <span v-if="type.merge_subtypes" class="text-xs text-violet-500 dark:text-violet-400 font-medium">Merged</span>
                        <span
                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                            :class="type.is_expense ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'"
                        >{{ type.is_expense ? 'Expense' : 'Income' }}</span>
                        <button @click="startEditType(type)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                    </div>
                </template>

                <!-- ── Type: edit mode ── -->
                <template v-else>
                    <div class="flex items-center gap-2 px-5 py-3 border-b border-slate-100 dark:border-slate-700">
                        <input
                            v-model="editingType.name"
                            type="text"
                            class="flex-1 min-w-0 h-7 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @keydown.enter="saveType"
                            @keydown.escape="cancelEdit"
                        />
                        <div class="flex rounded-md overflow-hidden border border-slate-200 dark:border-slate-600 text-xs font-medium shrink-0">
                            <button type="button" @click="editingType.is_expense = false"
                                class="px-2.5 py-1 transition-colors"
                                :class="!editingType.is_expense ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                            >Income</button>
                            <button type="button" @click="editingType.is_expense = true"
                                class="px-2.5 py-1 border-l border-slate-200 dark:border-slate-600 transition-colors"
                                :class="editingType.is_expense ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                            >Expense</button>
                        </div>
                        <button type="button" @click="editingType.is_disabled = !editingType.is_disabled"
                            class="px-2 py-0.5 text-xs font-medium rounded-full transition-colors shrink-0"
                            :class="editingType.is_disabled ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'"
                        >Disabled</button>
                        <button type="button" @click="editingType.is_private = !editingType.is_private"
                            class="px-2 py-0.5 text-xs font-medium rounded-full transition-colors shrink-0"
                            :class="editingType.is_private ? 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'"
                        >Private</button>
                        <button type="button" @click="editingType.merge_subtypes = !editingType.merge_subtypes"
                            class="px-2 py-0.5 text-xs font-medium rounded-full transition-colors shrink-0"
                            :class="editingType.merge_subtypes ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'"
                        >Merge</button>
                        <button @click="saveType"
                            class="px-2.5 py-0.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded-md transition-colors shrink-0"
                        >Save</button>
                        <button
                            @click.stop="confirmingDelete === `type-${editingType.id}` ? deleteType(editingType.id) : (confirmingDelete = `type-${editingType.id}`)"
                            class="px-2.5 py-0.5 text-xs font-medium text-white rounded-md transition-colors shrink-0"
                            :class="confirmingDelete === `type-${editingType.id}` ? 'bg-red-600 hover:bg-red-700 animate-pulse' : 'bg-red-500 hover:bg-red-600'"
                        >{{ confirmingDelete === `type-${editingType.id}` ? 'Confirm?' : 'Delete' }}</button>
                        <button @click="cancelEdit"
                            class="px-2.5 py-0.5 text-xs font-medium bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-md transition-colors shrink-0"
                        >Cancel</button>
                    </div>
                </template>

                <!-- ── Subtypes list ── -->
                <ul v-if="type.subtypes.length" class="divide-y divide-slate-50 dark:divide-slate-700/60 px-3">
                    <li
                        v-for="sub in type.subtypes" :key="sub.id"
                        class="flex items-center gap-3 py-2 transition-colors"
                        :class="{
                            'opacity-40': dragSubInfo?.subId === sub.id,
                            'bg-indigo-50 dark:bg-indigo-900/20': dragOverSubId === sub.id,
                        }"
                        :draggable="!editingSubtype"
                        @dragstart="onSubDragStart($event, type.id, sub)"
                        @dragover="onSubDragOver($event, sub)"
                        @dragleave="onSubDragLeave"
                        @drop="onSubDrop($event, type, sub)"
                        @dragend="onSubDragEnd"
                    >
                        <!-- Subtype: view mode -->
                        <template v-if="editingSubtype?.id !== sub.id">
                            <span class="text-slate-300 dark:text-slate-600 cursor-grab active:cursor-grabbing shrink-0 select-none">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                                </svg>
                            </span>
                            <span class="flex-1 text-sm text-slate-700 dark:text-slate-300">{{ sub.name }}</span>
                            <span v-if="sub.is_disabled" class="text-xs text-amber-500 dark:text-amber-400 font-medium">Disabled</span>
                            <span v-if="!sub.is_private" class="text-xs text-sky-500 dark:text-sky-400 font-medium">Always visible</span>
                            <button @click="startEditSubtype(type, sub)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                        </template>

                        <!-- Subtype: edit mode -->
                        <template v-else>
                            <input
                                v-model="editingSubtype.name"
                                type="text"
                                class="flex-1 min-w-0 h-7 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 ml-1"
                                @keydown.enter="saveSubtype(type)"
                                @keydown.escape="cancelEdit"
                            />
                            <button type="button" @click="editingSubtype.is_disabled = !editingSubtype.is_disabled"
                                class="px-2 py-0.5 text-xs font-medium rounded-full transition-colors shrink-0"
                                :class="editingSubtype.is_disabled ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'"
                            >Disabled</button>
                            <button type="button"
                                @click="!editingSubtype.parentMerged && (editingSubtype.is_private = !editingSubtype.is_private)"
                                class="px-2 py-0.5 text-xs font-medium rounded-full transition-colors shrink-0"
                                :disabled="editingSubtype.parentMerged"
                                :class="editingSubtype.parentMerged
                                    ? 'bg-slate-100 dark:bg-slate-700 text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                    : editingSubtype.is_private
                                        ? 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400'
                                        : 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'"
                                :title="editingSubtype.parentMerged ? 'Controlled by parent type' : ''"
                            >Private</button>
                            <button @click="saveSubtype(type)"
                                class="px-2.5 py-0.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded-md transition-colors shrink-0"
                            >Save</button>
                            <button
                                @click.stop="confirmingDelete === `sub-${editingSubtype.id}` ? deleteSubtype(type, editingSubtype.id) : (confirmingDelete = `sub-${editingSubtype.id}`)"
                                class="px-2.5 py-0.5 text-xs font-medium text-white rounded-md transition-colors shrink-0"
                                :class="confirmingDelete === `sub-${editingSubtype.id}` ? 'bg-red-600 hover:bg-red-700 animate-pulse' : 'bg-red-500 hover:bg-red-600'"
                            >{{ confirmingDelete === `sub-${editingSubtype.id}` ? 'Confirm?' : 'Delete' }}</button>
                            <button @click="cancelEdit"
                                class="px-2.5 py-0.5 text-xs font-medium bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-md transition-colors shrink-0"
                            >Cancel</button>
                        </template>
                    </li>
                </ul>

                <!-- ── Add subtype ── -->
                <form @submit.prevent="addSubtype(type)" class="flex gap-2 px-5 py-3" :class="type.subtypes.length ? 'border-t border-slate-100 dark:border-slate-700' : ''">
                    <input
                        v-model="newSubtypeName[type.id]"
                        type="text" :placeholder="`Add subtype under ${type.name}…`"
                        class="h-9 flex-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <button
                        type="submit" :disabled="!newSubtypeName[type.id]?.trim()"
                        class="h-9 px-3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 disabled:opacity-40 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors"
                    >Add</button>
                </form>
            </div>
        </template>
    </div>

    <!-- Import preview modal -->
    <ImportPreviewModal
        v-if="previewData"
        :preview="previewData"
        @confirm="confirmImport"
        @cancel="cancelImport"
    />
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, watch } from 'vue';
import api from '../api';
import { downloadExport, previewImport, uploadImport } from '../utils/importExport';
import ImportPreviewModal from '../components/ImportPreviewModal.vue';

// Import / Export
const fileInputRef  = ref(null);
const previewing    = ref(false);
const previewData   = ref(null);
const pendingFile   = ref(null);
const importing     = ref(false);
const importResult  = ref(null);
const importError   = ref('');

async function doExport(format) {
    await downloadExport(`/cashflow/export?format=${format}`, `cashflow.${format}`);
}

async function onFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    previewing.value   = true;
    importResult.value = null;
    importError.value  = '';
    try {
        const format = file.name.endsWith('.json') ? 'json' : 'csv';
        const preview = await previewImport('/cashflow/import/preview', file, format);

        // No issues at all — import directly without showing the modal
        if (preview.invalid.length === 0 && preview.duplicates.length === 0) {
            previewing.value = false;
            if (preview.total === 0) {
                importError.value = 'No rows found in file.';
                clearFile();
                return;
            }
            importing.value = true;
            try {
                importResult.value = await uploadImport('/cashflow/import', file, format, { skip_duplicates: true });
            } catch (err) {
                importError.value = err.response?.data?.message ?? 'Import failed.';
            } finally {
                importing.value = false;
                clearFile();
            }
            return;
        }

        // Has invalid or duplicate rows — show the modal
        pendingFile.value = file;
        previewData.value = preview;
    } catch (err) {
        importError.value = err.response?.data?.message ?? 'Failed to read file.';
        clearFile();
    } finally {
        previewing.value = false;
    }
}

async function confirmImport(skipDuplicates) {
    if (!pendingFile.value) return;
    importing.value    = true;
    importResult.value = null;
    importError.value  = '';
    const file = pendingFile.value;
    previewData.value  = null;
    pendingFile.value  = null;
    try {
        const format = file.name.endsWith('.json') ? 'json' : 'csv';
        importResult.value = await uploadImport('/cashflow/import', file, format, { skip_duplicates: skipDuplicates });
    } catch (err) {
        importError.value = err.response?.data?.message ?? 'Import failed.';
    } finally {
        importing.value = false;
        clearFile();
    }
}

function cancelImport() {
    previewData.value = null;
    pendingFile.value = null;
    clearFile();
}

function clearFile() {
    if (fileInputRef.value) fileInputRef.value.value = '';
}

const types          = ref([]);
const loading        = ref(true);

const newTypeName      = ref('');
const newTypeIsExpense = ref(true);
const typeError        = ref('');
const editingType      = ref(null);

const newSubtypeName   = reactive({});
const editingSubtype   = ref(null);
const confirmingDelete = ref(null);

function resetConfirm() { confirmingDelete.value = null; }
watch(confirmingDelete, (val) => {
    if (val) document.addEventListener('click', resetConfirm);
    else     document.removeEventListener('click', resetConfirm);
});
onUnmounted(() => document.removeEventListener('click', resetConfirm));

onMounted(async () => {
    try {
        const { data } = await api.get('/cashflow/settings/types');
        types.value = data;
    } finally {
        loading.value = false;
    }
});

// ── Types CRUD ────────────────────────────────────────────────────────────────

async function addType() {
    const name = newTypeName.value.trim();
    if (!name) return;
    typeError.value = '';
    try {
        const { data } = await api.post('/cashflow/settings/types', { name, is_expense: newTypeIsExpense.value });
        types.value.push(data);
        newTypeName.value = '';
        newTypeIsExpense.value = true;
    } catch {
        typeError.value = 'Failed to add type.';
    }
}

function startEditType(type) {
    editingSubtype.value = null;
    confirmingDelete.value = null;
    editingType.value = {
        id: type.id,
        name: type.name,
        is_expense: type.is_expense,
        is_disabled: type.is_disabled,
        is_private: type.is_private,
        merge_subtypes: type.merge_subtypes,
    };
}

async function saveType() {
    if (!editingType.value) return;
    const { id, name, is_expense, is_disabled, is_private, merge_subtypes } = editingType.value;
    editingType.value = null;
    const { data } = await api.patch(`/cashflow/settings/types/${id}`, { name, is_expense, is_disabled, is_private, merge_subtypes });
    const idx = types.value.findIndex(t => t.id === id);
    if (idx !== -1) types.value[idx] = { ...types.value[idx], ...data };
}

async function deleteType(id) {
    confirmingDelete.value = null;
    editingType.value = null;
    await api.delete(`/cashflow/settings/types/${id}`);
    types.value = types.value.filter(t => t.id !== id);
}

function cancelEdit() {
    editingType.value = null;
    editingSubtype.value = null;
    confirmingDelete.value = null;
}

// ── Subtypes CRUD ─────────────────────────────────────────────────────────────

async function addSubtype(type) {
    const name = (newSubtypeName[type.id] ?? '').trim();
    if (!name) return;
    const { data } = await api.post(`/cashflow/settings/types/${type.id}/subtypes`, { name });
    type.subtypes.push(data);
    newSubtypeName[type.id] = '';
}

function startEditSubtype(type, sub) {
    editingType.value = null;
    confirmingDelete.value = null;
    editingSubtype.value = {
        id: sub.id,
        name: sub.name,
        is_disabled: sub.is_disabled,
        is_private: sub.is_private,
        parentMerged: type.merge_subtypes,
    };
}

async function saveSubtype(type) {
    if (!editingSubtype.value) return;
    const { id, name, is_disabled, is_private } = editingSubtype.value;
    editingSubtype.value = null;
    const { data } = await api.patch(`/cashflow/settings/subtypes/${id}`, { name, is_disabled, is_private });
    const idx = type.subtypes.findIndex(s => s.id === id);
    if (idx !== -1) type.subtypes[idx] = { ...type.subtypes[idx], ...data };
}

async function deleteSubtype(type, id) {
    confirmingDelete.value = null;
    editingSubtype.value = null;
    await api.delete(`/cashflow/settings/subtypes/${id}`);
    type.subtypes = type.subtypes.filter(s => s.id !== id);
}

// ── Drag & drop — types ───────────────────────────────────────────────────────

const dragTypeId     = ref(null);
const dragOverTypeId = ref(null);

function onTypeDragStart(e, type) {
    dragTypeId.value = type.id;
    e.dataTransfer.effectAllowed = 'move';
}

function onTypeDragOver(e, type) {
    if (!dragTypeId.value || dragSubInfo.value) return;
    if (dragTypeId.value === type.id) return;
    e.preventDefault();
    dragOverTypeId.value = type.id;
}

function onTypeDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) dragOverTypeId.value = null;
}

async function onTypeDrop(e, targetType) {
    if (dragSubInfo.value) return;
    e.preventDefault();
    const fromId = dragTypeId.value;
    dragTypeId.value = null;
    dragOverTypeId.value = null;
    if (!fromId || fromId === targetType.id) return;

    const fromIdx = types.value.findIndex(t => t.id === fromId);
    const toIdx   = types.value.findIndex(t => t.id === targetType.id);
    if (fromIdx === -1 || toIdx === -1) return;

    const [item] = types.value.splice(fromIdx, 1);
    types.value.splice(toIdx, 0, item);

    await Promise.all(types.value.map((t, i) =>
        api.patch(`/cashflow/settings/types/${t.id}`, { sort_order: i })
    ));
}

function onTypeDragEnd() {
    dragTypeId.value = null;
    dragOverTypeId.value = null;
}

// ── Drag & drop — subtypes ────────────────────────────────────────────────────

const dragSubInfo   = ref(null);
const dragOverSubId = ref(null);

function onSubDragStart(e, typeId, sub) {
    e.stopPropagation();
    dragSubInfo.value = { typeId, subId: sub.id };
    e.dataTransfer.effectAllowed = 'move';
}

function onSubDragOver(e, sub) {
    if (!dragSubInfo.value || dragSubInfo.value.subId === sub.id) return;
    e.preventDefault();
    e.stopPropagation();
    dragOverSubId.value = sub.id;
}

function onSubDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) dragOverSubId.value = null;
}

async function onSubDrop(e, type, targetSub) {
    e.preventDefault();
    e.stopPropagation();
    const info = dragSubInfo.value;
    dragSubInfo.value = null;
    dragOverSubId.value = null;
    if (!info || info.subId === targetSub.id || info.typeId !== type.id) return;

    const subs    = type.subtypes;
    const fromIdx = subs.findIndex(s => s.id === info.subId);
    const toIdx   = subs.findIndex(s => s.id === targetSub.id);
    if (fromIdx === -1 || toIdx === -1) return;

    const [item] = subs.splice(fromIdx, 1);
    subs.splice(toIdx, 0, item);

    await Promise.all(subs.map((s, i) =>
        api.patch(`/cashflow/settings/subtypes/${s.id}`, { sort_order: i })
    ));
}

function onSubDragEnd() {
    dragSubInfo.value = null;
    dragOverSubId.value = null;
}
</script>
