<template>
    <div class="relative">
        <input
            ref="inputRef"
            :value="modelValue"
            @input="onInput"
            @focus="open = true"
            @blur="onBlur"
            @keydown.down.prevent="moveDown"
            @keydown.up.prevent="moveUp"
            @keydown.enter.prevent="selectHighlighted"
            @keydown.escape="open = false"
            type="text"
            :placeholder="placeholder"
            maxlength="3"
            class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase"
        />
        <div
            v-if="open && results.length > 0"
            class="absolute z-50 mt-1 w-72 max-h-52 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-lg"
        >
            <button
                v-for="(a, i) in results" :key="a.iata"
                @mousedown.prevent="select(a)"
                class="w-full text-left px-3 py-2 text-sm transition-colors flex items-baseline gap-2"
                :class="i === highlighted
                    ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
            >
                <span class="font-mono font-semibold shrink-0">{{ a.iata }}</span>
                <span class="truncate text-slate-500 dark:text-slate-400">{{ a.city }} — {{ a.name }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { searchAirports } from '../data/airportLookup';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'IATA code' },
});

const emit = defineEmits(['update:modelValue']);

const inputRef = ref(null);
const open = ref(false);
const results = ref([]);
const highlighted = ref(0);

function onInput(e) {
    const val = e.target.value.toUpperCase();
    emit('update:modelValue', val);
    results.value = searchAirports(val, 8);
    highlighted.value = 0;
    open.value = true;
}

function select(airport) {
    emit('update:modelValue', airport.iata);
    open.value = false;
    results.value = [];
}

function onBlur() {
    setTimeout(() => { open.value = false; }, 150);
}

function moveDown() {
    if (highlighted.value < results.value.length - 1) highlighted.value++;
}

function moveUp() {
    if (highlighted.value > 0) highlighted.value--;
}

function selectHighlighted() {
    if (results.value[highlighted.value]) {
        select(results.value[highlighted.value]);
    }
}

watch(() => props.modelValue, (val) => {
    if (val && val.length >= 1) {
        results.value = searchAirports(val, 8);
    } else {
        results.value = [];
    }
});
</script>
