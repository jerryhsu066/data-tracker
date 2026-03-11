<template>
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Cashflow Settings</h1>

        <!-- Add new type (always on top) -->
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
            <!-- Existing types -->
            <div v-for="type in types" :key="type.id" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">

                <!-- Type header row -->
                <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-100 dark:border-slate-700">
                    <template v-if="editingType?.id !== type.id">
                        <span class="flex-1 font-semibold text-slate-800 dark:text-slate-100" :class="type.is_hidden ? 'line-through opacity-50' : ''">{{ type.name }}</span>
                        <span
                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                            :class="type.is_expense ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'"
                        >{{ type.is_expense ? 'Expense' : 'Income' }}</span>
                        <button @click="startEditType(type)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                        <button
                            @click.stop="confirmingDelete === `type-${type.id}` ? deleteType(type.id) : (confirmingDelete = `type-${type.id}`)"
                            class="text-xs font-medium transition-colors"
                            :class="confirmingDelete === `type-${type.id}` ? 'text-red-500 animate-pulse' : 'text-slate-400 hover:text-red-500'"
                        >{{ confirmingDelete === `type-${type.id}` ? 'Confirm?' : 'Delete' }}</button>
                    </template>
                    <template v-else>
                        <input
                            v-model="editingType.name"
                            type="text"
                            class="flex-1 h-8 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @keydown.enter="saveType"
                            @keydown.escape="editingType = null"
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
                        <button @click="saveType" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Save</button>
                        <button @click="editingType = null" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                    </template>
                </div>

                <!-- Type options row (hide / merge) -->
                <div class="flex items-center gap-4 px-5 py-2 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <button
                        @click="toggleTypeHidden(type)"
                        class="flex items-center gap-1.5 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                        :class="type.is_hidden ? 'text-amber-600 dark:text-amber-400' : ''"
                    >
                        <span class="w-3.5 h-3.5 inline-flex items-center justify-center rounded border transition-colors"
                            :class="type.is_hidden ? 'border-amber-500 bg-amber-500 text-white' : 'border-slate-300 dark:border-slate-600'"
                        >
                            <svg v-if="type.is_hidden" class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 16 16"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/></svg>
                        </span>
                        Hidden
                    </button>
                    <button
                        @click="toggleTypeMerge(type)"
                        class="flex items-center gap-1.5 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                        :class="type.merge_subtypes ? 'text-indigo-600 dark:text-indigo-400' : ''"
                    >
                        <span class="w-3.5 h-3.5 inline-flex items-center justify-center rounded border transition-colors"
                            :class="type.merge_subtypes ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-slate-300 dark:border-slate-600'"
                        >
                            <svg v-if="type.merge_subtypes" class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 16 16"><path d="M13 8a.5.5 0 0 1-.5.5H3.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L3.707 7.5H12.5A.5.5 0 0 1 13 8"/></svg>
                        </span>
                        Merge subtypes
                    </button>
                </div>

                <!-- Subtypes list -->
                <ul v-if="type.subtypes.length" class="divide-y divide-slate-50 dark:divide-slate-700/60 px-5">
                    <li v-for="sub in type.subtypes" :key="sub.id" class="flex items-center gap-3 py-2">
                        <template v-if="editingSubtype?.id !== sub.id">
                            <span class="flex-1 text-sm text-slate-700 dark:text-slate-300 pl-2" :class="sub.is_hidden ? 'line-through opacity-50' : ''">{{ sub.name }}</span>
                            <!-- subtype hidden toggle -->
                            <button
                                @click="toggleSubtypeHidden(type, sub)"
                                class="text-xs transition-colors"
                                :class="sub.is_hidden ? 'text-amber-500' : 'text-slate-300 dark:text-slate-600 hover:text-amber-400'"
                                title="Toggle visibility"
                            >
                                <!-- eye-open -->
                                <svg v-if="!sub.is_hidden" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                </svg>
                                <!-- eye-slash -->
                                <svg v-else class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/>
                                    <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                                    <path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>
                                </svg>
                            </button>
                            <button @click="startEditSubtype(sub)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                            <button
                                @click.stop="confirmingDelete === `sub-${sub.id}` ? deleteSubtype(type, sub.id) : (confirmingDelete = `sub-${sub.id}`)"
                                class="text-xs font-medium transition-colors"
                                :class="confirmingDelete === `sub-${sub.id}` ? 'text-red-500 animate-pulse' : 'text-slate-400 hover:text-red-500'"
                            >{{ confirmingDelete === `sub-${sub.id}` ? 'Confirm?' : 'Delete' }}</button>
                        </template>
                        <template v-else>
                            <input
                                v-model="editingSubtype.name"
                                type="text"
                                class="flex-1 h-8 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 ml-2"
                                @keydown.enter="saveSubtype(type)"
                                @keydown.escape="editingSubtype = null"
                            />
                            <button @click="saveSubtype(type)" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Save</button>
                            <button @click="editingSubtype = null" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                        </template>
                    </li>
                </ul>

                <!-- Add subtype -->
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
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, watch } from 'vue';
import api from '../api';

const types          = ref([]);
const loading        = ref(true);

const newTypeName      = ref('');
const newTypeIsExpense = ref(true);
const typeError        = ref('');
const editingType      = ref(null);

const newSubtypeName  = reactive({}); // keyed by type.id
const editingSubtype  = ref(null);
const confirmingDelete = ref(null); // `type-{id}` or `sub-{id}`

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

// ── Types ─────────────────────────────────────────────────────────────────────

async function addType() {
    const name = newTypeName.value.trim();
    if (!name) return;
    typeError.value = '';
    try {
        const { data } = await api.post('/cashflow/settings/types', {
            name,
            is_expense: newTypeIsExpense.value,
        });
        types.value.push(data);
        newTypeName.value = '';
        newTypeIsExpense.value = true;
    } catch {
        typeError.value = 'Failed to add type.';
    }
}

function startEditType(type) {
    confirmingDelete.value = null;
    editingType.value = { id: type.id, name: type.name, is_expense: type.is_expense };
}

async function saveType() {
    if (!editingType.value) return;
    const { id, name, is_expense } = editingType.value;
    editingType.value = null;
    const { data } = await api.patch(`/cashflow/settings/types/${id}`, { name, is_expense });
    const idx = types.value.findIndex(t => t.id === id);
    if (idx !== -1) types.value[idx] = { ...types.value[idx], ...data };
}

async function deleteType(id) {
    confirmingDelete.value = null;
    await api.delete(`/cashflow/settings/types/${id}`);
    types.value = types.value.filter(t => t.id !== id);
}

async function toggleTypeHidden(type) {
    const is_hidden = !type.is_hidden;
    type.is_hidden = is_hidden; // optimistic
    await api.patch(`/cashflow/settings/types/${type.id}`, { is_hidden });
}

async function toggleTypeMerge(type) {
    const merge_subtypes = !type.merge_subtypes;
    type.merge_subtypes = merge_subtypes; // optimistic
    await api.patch(`/cashflow/settings/types/${type.id}`, { merge_subtypes });
}

// ── Subtypes ──────────────────────────────────────────────────────────────────

async function addSubtype(type) {
    const name = (newSubtypeName[type.id] ?? '').trim();
    if (!name) return;
    const { data } = await api.post(`/cashflow/settings/types/${type.id}/subtypes`, { name });
    type.subtypes.push(data);
    newSubtypeName[type.id] = '';
}

function startEditSubtype(sub) {
    confirmingDelete.value = null;
    editingSubtype.value = { id: sub.id, name: sub.name };
}

async function saveSubtype(type) {
    if (!editingSubtype.value) return;
    const { id, name } = editingSubtype.value;
    editingSubtype.value = null;
    const { data } = await api.patch(`/cashflow/settings/subtypes/${id}`, { name });
    const idx = type.subtypes.findIndex(s => s.id === id);
    if (idx !== -1) type.subtypes[idx] = data;
}

async function deleteSubtype(type, id) {
    confirmingDelete.value = null;
    await api.delete(`/cashflow/settings/subtypes/${id}`);
    type.subtypes = type.subtypes.filter(s => s.id !== id);
}

async function toggleSubtypeHidden(type, sub) {
    const is_hidden = !sub.is_hidden;
    sub.is_hidden = is_hidden; // optimistic
    const { data } = await api.patch(`/cashflow/settings/subtypes/${sub.id}`, { is_hidden });
    const idx = type.subtypes.findIndex(s => s.id === sub.id);
    if (idx !== -1) type.subtypes[idx] = { ...type.subtypes[idx], ...data };
}
</script>
