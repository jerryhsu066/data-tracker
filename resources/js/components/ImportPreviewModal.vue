<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md space-y-4 p-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ importableCount === 0 ? 'Cannot Import' : 'Ready to Import' }}
            </h3>

            <!-- Summary -->
            <div class="space-y-1 text-sm">
                <p v-if="preview.total === 0" class="text-slate-500 dark:text-slate-400">No rows found in file.</p>
                <template v-else>
                    <p v-if="importableCount > 0" class="text-slate-700 dark:text-slate-300">
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ importableCount }}</span>
                        row(s) will be imported
                        <span v-if="preview.invalid.length" class="text-slate-500 dark:text-slate-400">
                            ({{ preview.invalid.length }} invalid row(s) skipped)
                        </span>
                    </p>
                </template>
            </div>

            <!-- Invalid rows — always shown when present -->
            <div v-if="preview.invalid.length" class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-3">
                <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-2">
                    {{ preview.invalid.length }} invalid row(s) — will be skipped
                </p>
                <div class="max-h-36 overflow-y-auto space-y-1">
                    <p v-for="item in preview.invalid" :key="item.row" class="text-xs text-red-700 dark:text-red-300">
                        <span class="font-medium">Row {{ item.row }}:</span> {{ item.reason }}
                    </p>
                </div>
            </div>

            <!-- Duplicate rows -->
            <div v-if="preview.duplicates.length" class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3">
                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-2">
                    {{ preview.duplicates.length }} duplicate row(s) found
                </p>
                <div class="max-h-36 overflow-y-auto space-y-1 mb-3">
                    <p v-for="item in preview.duplicates" :key="item.row" class="text-xs text-amber-700 dark:text-amber-300">
                        <span class="font-medium">Row {{ item.row }}:</span> {{ item.label }}
                    </p>
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="radio" v-model="skipDuplicates" :value="true" class="accent-indigo-600" />
                        <span class="text-slate-700 dark:text-slate-300">Skip duplicates</span>
                    </label>
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="radio" v-model="skipDuplicates" :value="false" class="accent-indigo-600" />
                        <span class="text-slate-700 dark:text-slate-300">Import anyway</span>
                    </label>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex gap-2 justify-end pt-1">
                <!-- Nothing to import: show only Close -->
                <template v-if="importableCount === 0">
                    <button @click="$emit('cancel')"
                        class="px-5 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-lg transition-colors">
                        Close
                    </button>
                </template>
                <!-- Has rows to import -->
                <template v-else>
                    <button @click="$emit('cancel')"
                        class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                        Cancel
                    </button>
                    <button @click="$emit('confirm', skipDuplicates)"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                        Import {{ importableCount }} row{{ importableCount !== 1 ? 's' : '' }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    preview: { type: Object, required: true },
});

defineEmits(['confirm', 'cancel']);

const skipDuplicates = ref(true);

const importableCount = computed(() =>
    skipDuplicates.value
        ? props.preview.valid
        : props.preview.valid + props.preview.duplicates.length
);
</script>
