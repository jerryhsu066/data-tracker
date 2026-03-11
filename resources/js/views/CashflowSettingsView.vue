<template>
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Cashflow Settings</h1>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Existing types -->
            <div v-for="type in types" :key="type.id" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden">

                <!-- Type header row -->
                <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-100 dark:border-slate-700">
                    <template v-if="editingType?.id !== type.id">
                        <span class="flex-1 font-semibold text-slate-800 dark:text-slate-100">{{ type.name }}</span>
                        <span
                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                            :class="type.is_expense ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'"
                        >{{ type.is_expense ? 'Expense' : 'Income' }}</span>
                        <button @click="startEditType(type)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                        <button @click="deleteType(type.id)" class="text-xs text-slate-400 hover:text-red-500 transition-colors">Delete</button>
                    </template>
                    <template v-else>
                        <input
                            v-model="editingType.name"
                            type="text"
                            class="flex-1 h-8 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @keydown.enter="saveType"
                            @keydown.escape="editingType = null"
                        />
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300 select-none cursor-pointer">
                            <input type="checkbox" v-model="editingType.is_expense" class="rounded" />
                            Expense
                        </label>
                        <button @click="saveType" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Save</button>
                        <button @click="editingType = null" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                    </template>
                </div>

                <!-- Subtypes list -->
                <ul v-if="type.subtypes.length" class="divide-y divide-slate-50 dark:divide-slate-700/60 px-5">
                    <li v-for="sub in type.subtypes" :key="sub.id" class="flex items-center gap-3 py-2">
                        <template v-if="editingSubtype?.id !== sub.id">
                            <span class="flex-1 text-sm text-slate-700 dark:text-slate-300 pl-2">{{ sub.name }}</span>
                            <button @click="startEditSubtype(sub)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                            <button @click="deleteSubtype(type, sub.id)" class="text-xs text-slate-400 hover:text-red-500 transition-colors">Delete</button>
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

            <!-- Add new type -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-3">Add New Type</h2>
                <form @submit.prevent="addType" class="flex gap-2 items-center flex-wrap">
                    <input
                        v-model="newTypeName"
                        type="text" placeholder="Type name (e.g. Subscription)"
                        class="h-9 flex-1 min-w-40 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <label class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300 select-none cursor-pointer px-1">
                        <input type="checkbox" v-model="newTypeIsExpense" class="rounded" />
                        Expense
                    </label>
                    <button
                        type="submit" :disabled="!newTypeName.trim()"
                        class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
                    >Add Type</button>
                </form>
                <p class="h-[1.1rem] text-xs text-red-500 mt-1">{{ typeError }}</p>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../api';

const types          = ref([]);
const loading        = ref(true);

const newTypeName      = ref('');
const newTypeIsExpense = ref(true);
const typeError        = ref('');
const editingType      = ref(null);

const newSubtypeName = reactive({}); // keyed by type.id
const editingSubtype = ref(null);

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
    editingType.value = { id: type.id, name: type.name, is_expense: type.is_expense };
}

async function saveType() {
    if (!editingType.value) return;
    const { id, name, is_expense } = editingType.value;
    editingType.value = null;
    const { data } = await api.patch(`/cashflow/settings/types/${id}`, { name, is_expense });
    const idx = types.value.findIndex(t => t.id === id);
    if (idx !== -1) {
        types.value[idx] = { ...types.value[idx], ...data };
    }
}

async function deleteType(id) {
    await api.delete(`/cashflow/settings/types/${id}`);
    types.value = types.value.filter(t => t.id !== id);
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
    await api.delete(`/cashflow/settings/subtypes/${id}`);
    type.subtypes = type.subtypes.filter(s => s.id !== id);
}
</script>
