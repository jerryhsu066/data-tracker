<template>
    <div class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-gray-950/80 backdrop-blur-sm sticky top-12 z-30">
        <nav class="flex overflow-x-auto scrollbar-hide -mb-px" role="tablist">
            <RouterLink
                v-for="tab in tabs"
                :key="tab.id"
                :to="tab.path"
                class="shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                :class="isActive(tab)
                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600'"
            >{{ tab.label }}</RouterLink>
        </nav>
    </div>
</template>

<script setup>
import { useRoute, RouterLink } from 'vue-router';

const props = defineProps({
    tabs: { type: Array, required: true },
    basePath: { type: String, default: '' },
});

const route = useRoute();

function isActive(tab) {
    if (route.path === tab.path) return true;
    // For dynamic routes like /stocks/:symbol, highlight the "Stocks" tab
    if (tab.id === 'list' && props.basePath === '/stocks') {
        return route.path.startsWith('/stocks/') && !props.tabs.some(t => t.path === route.path);
    }
    return false;
}
</script>
