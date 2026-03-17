<template>
    <aside
        :class="collapsed ? 'w-16' : 'w-55'"
        class="fixed top-12 left-0 bottom-0 bg-white dark:bg-gray-950 border-r border-slate-200 dark:border-slate-800 z-40 flex flex-col transition-[width] duration-200"
    >
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-1">
            <div v-for="mod in modules" :key="mod.id">
                <RouterLink
                    :to="mod.basePath + '/' + mod.defaultTab"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    :class="isActive(mod)
                        ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white'
                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'"
                    :title="collapsed ? mod.label : undefined"
                >
                    <component :is="moduleIcons[mod.id]" class="w-5 h-5 shrink-0" />
                    <span v-show="!collapsed" class="truncate">{{ mod.label }}</span>
                </RouterLink>

                <!-- Sub-links: only when expanded AND module is active -->
                <div v-if="!collapsed && isActive(mod)" class="mt-1 ml-5 space-y-0.5">
                    <RouterLink
                        v-for="tab in mod.tabs"
                        :key="tab.id"
                        :to="tab.path"
                        class="block px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
                        :class="isTabActive(tab)
                            ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-500/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/40'"
                    >{{ tab.label }}</RouterLink>
                </div>
            </div>
        </nav>

        <!-- Collapse toggle -->
        <button
            @click="toggle"
            class="flex items-center justify-center p-3 border-t border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors"
            :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"
                class="transition-transform duration-200"
                :class="collapsed ? 'rotate-180' : ''"
            >
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
        </button>
    </aside>
</template>

<script setup>
import { h } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { modules } from '../modules';
import { useSidebar } from '../stores/sidebar';

const route = useRoute();
const { collapsed, toggle } = useSidebar();

function isActive(mod) {
    return route.path.startsWith(mod.basePath);
}

function isTabActive(tab) {
    return route.path === tab.path;
}

// Inline SVG icon components per module
const StocksIcon = {
    render() {
        return h('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 20 20', fill: 'currentColor' }, [
            h('path', { d: 'M12 2a1 1 0 0 1 .894.553l2.991 5.982a.869.869 0 0 1 .02.037l.99 1.98a1 1 0 1 1-1.79.895L14.382 10h-4.764l-.723 1.447a1 1 0 1 1-1.79-.894l.99-1.98.02-.038 2.99-5.982A1 1 0 0 1 12 2Zm-1.382 6h2.764L12 5.236 10.618 8Z' }),
            h('path', { d: 'M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm0-2a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z' }),
            h('path', { d: 'M2 10a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Z' }),
        ]);
    },
};

const CashflowIcon = {
    render() {
        return h('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 20 20', fill: 'currentColor' }, [
            h('path', { 'fill-rule': 'evenodd', 'clip-rule': 'evenodd', d: 'M1 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4Zm2 1v10h14V5H3Z' }),
            h('path', { d: 'M10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-3 2a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z' }),
        ]);
    },
};

const moduleIcons = {
    stocks: StocksIcon,
    cashflow: CashflowIcon,
};
</script>
