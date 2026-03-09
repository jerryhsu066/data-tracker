<template>
    <div class="max-w-lg mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-slate-900 mb-6">Settings</h1>

        <div class="bg-white rounded-xl shadow-sm p-6 space-y-6">
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Brokerage Fees</h2>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Handling Fee Discount</label>
                <p class="text-xs text-slate-400 mb-3">
                    Standard rate is 0.1425%. Enter your broker's discount (e.g. 40 for 40% off).
                    Effective rate: <span class="font-medium text-slate-600">{{ effectiveRate }}%</span>
                </p>
                <div class="flex items-center gap-3">
                    <input
                        v-model.number="discountPercent"
                        type="number" min="0" max="100" step="1"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="0"
                    />
                    <span class="text-sm text-slate-500">% off</span>
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
import { ref, computed } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';

const { state, updateUser } = useAuth();

const STANDARD_RATE = 0.1425;

// Convert stored decimal (0.40) → display percent (40)
const discountPercent = ref(Math.round(Number(state.user?.handling_fee_discount ?? 0) * 100));
const saving = ref(false);
const saved = ref(false);
const error = ref('');

const effectiveRate = computed(() =>
    (STANDARD_RATE * (1 - discountPercent.value / 100)).toFixed(4)
);

async function save() {
    saving.value = true;
    saved.value = false;
    error.value = '';
    try {
        const { data } = await api.patch('/settings', {
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
