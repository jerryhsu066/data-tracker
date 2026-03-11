<template>
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Cashflow Settings</h1>

        <!-- Companies -->
        <section class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-semibold text-slate-700 dark:text-slate-200 mb-4">Companies</h2>

            <ul v-if="companies.length" class="divide-y divide-slate-100 dark:divide-slate-700 mb-4">
                <li v-for="c in companies" :key="c.id" class="flex items-center py-2 gap-3">
                    <span v-if="editingCompany?.id !== c.id" class="flex-1 text-sm text-slate-800 dark:text-slate-200">{{ c.name }}</span>
                    <input
                        v-else
                        v-model="editingCompany.name"
                        type="text"
                        class="flex-1 h-8 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @keydown.enter="saveCompany"
                        @keydown.escape="editingCompany = null"
                    />
                    <div class="flex gap-2 shrink-0">
                        <template v-if="editingCompany?.id !== c.id">
                            <button @click="startEditCompany(c)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                            <button @click="deleteCompany(c.id)" class="text-xs text-slate-400 hover:text-red-500 transition-colors">Delete</button>
                        </template>
                        <template v-else>
                            <button @click="saveCompany" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Save</button>
                            <button @click="editingCompany = null" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                        </template>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-400 mb-4">No companies yet.</p>

            <form @submit.prevent="addCompany" class="flex gap-2">
                <input
                    v-model="newCompanyName"
                    type="text" placeholder="Company name"
                    class="h-9 flex-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <button type="submit" :disabled="!newCompanyName.trim()" class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors">Add</button>
            </form>
            <p class="h-[1.1rem] text-xs text-red-500 mt-1">{{ companyError }}</p>
        </section>

        <!-- Banks -->
        <section class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-semibold text-slate-700 dark:text-slate-200 mb-4">Banks</h2>

            <ul v-if="banks.length" class="divide-y divide-slate-100 dark:divide-slate-700 mb-4">
                <li v-for="b in banks" :key="b.id" class="flex items-center py-2 gap-3">
                    <span v-if="editingBank?.id !== b.id" class="flex-1 text-sm text-slate-800 dark:text-slate-200">{{ b.name }}</span>
                    <input
                        v-else
                        v-model="editingBank.name"
                        type="text"
                        class="flex-1 h-8 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @keydown.enter="saveBank"
                        @keydown.escape="editingBank = null"
                    />
                    <div class="flex gap-2 shrink-0">
                        <template v-if="editingBank?.id !== b.id">
                            <button @click="startEditBank(b)" class="text-xs text-slate-400 hover:text-indigo-500 transition-colors">Edit</button>
                            <button @click="deleteBank(b.id)" class="text-xs text-slate-400 hover:text-red-500 transition-colors">Delete</button>
                        </template>
                        <template v-else>
                            <button @click="saveBank" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Save</button>
                            <button @click="editingBank = null" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                        </template>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-400 mb-4">No banks yet.</p>

            <form @submit.prevent="addBank" class="flex gap-2">
                <input
                    v-model="newBankName"
                    type="text" placeholder="Bank name"
                    class="h-9 flex-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <button type="submit" :disabled="!newBankName.trim()" class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors">Add</button>
            </form>
            <p class="h-[1.1rem] text-xs text-red-500 mt-1">{{ bankError }}</p>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';

const companies     = ref([]);
const newCompanyName = ref('');
const companyError  = ref('');
const editingCompany = ref(null);

const banks      = ref([]);
const newBankName = ref('');
const bankError  = ref('');
const editingBank = ref(null);

onMounted(async () => {
    const [cRes, bRes] = await Promise.all([
        api.get('/cashflow/settings/companies'),
        api.get('/cashflow/settings/banks'),
    ]);
    companies.value = cRes.data;
    banks.value = bRes.data;
});

// ── Companies ─────────────────────────────────────────────────────────────────

async function addCompany() {
    const name = newCompanyName.value.trim();
    if (!name) return;
    companyError.value = '';
    try {
        const { data } = await api.post('/cashflow/settings/companies', { name });
        companies.value.push(data);
        newCompanyName.value = '';
    } catch {
        companyError.value = 'Failed to add company.';
    }
}

function startEditCompany(c) {
    editingCompany.value = { id: c.id, name: c.name };
}

async function saveCompany() {
    if (!editingCompany.value) return;
    const { id, name } = editingCompany.value;
    editingCompany.value = null;
    const { data } = await api.patch(`/cashflow/settings/companies/${id}`, { name });
    const idx = companies.value.findIndex(c => c.id === id);
    if (idx !== -1) companies.value[idx] = data;
}

async function deleteCompany(id) {
    await api.delete(`/cashflow/settings/companies/${id}`);
    companies.value = companies.value.filter(c => c.id !== id);
}

// ── Banks ─────────────────────────────────────────────────────────────────────

async function addBank() {
    const name = newBankName.value.trim();
    if (!name) return;
    bankError.value = '';
    try {
        const { data } = await api.post('/cashflow/settings/banks', { name });
        banks.value.push(data);
        newBankName.value = '';
    } catch {
        bankError.value = 'Failed to add bank.';
    }
}

function startEditBank(b) {
    editingBank.value = { id: b.id, name: b.name };
}

async function saveBank() {
    if (!editingBank.value) return;
    const { id, name } = editingBank.value;
    editingBank.value = null;
    const { data } = await api.patch(`/cashflow/settings/banks/${id}`, { name });
    const idx = banks.value.findIndex(b => b.id === id);
    if (idx !== -1) banks.value[idx] = data;
}

async function deleteBank(id) {
    await api.delete(`/cashflow/settings/banks/${id}`);
    banks.value = banks.value.filter(b => b.id !== id);
}
</script>
