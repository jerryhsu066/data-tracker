<template>
    <nav class="fixed top-0 inset-x-0 z-50 bg-slate-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-13">
            <!-- App name -->
            <span class="font-bold text-base tracking-tight text-white select-none">MyTracker</span>

            <!-- Module selector -->
            <div class="flex items-center gap-1">
                <button
                    v-for="mod in modules"
                    :key="mod.id"
                    @click="activeModule = mod.id"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
                    :class="activeModule === mod.id
                        ? 'bg-indigo-600 text-white'
                        : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                >
                    <span>{{ mod.icon }}</span>
                    <span>{{ mod.label }}</span>
                </button>
            </div>

            <!-- User controls -->
            <div class="flex items-center gap-3">
                <span class="text-slate-400 text-sm">{{ auth.state.user?.name }}</span>
                <button
                    @click="theme.toggle()"
                    class="p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-base leading-none"
                    :title="theme.dark.value ? 'Switch to light mode' : 'Switch to dark mode'"
                >{{ theme.dark.value ? '☀' : '☾' }}</button>
                <button
                    @click="handleLogout"
                    class="px-3 py-1.5 text-sm bg-slate-700 hover:bg-slate-600 rounded-md transition-colors"
                >Logout</button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useAuth } from '../stores/auth';
import { useTheme } from '../stores/theme';
import { useRouter } from 'vue-router';

const auth = useAuth();
const theme = useTheme();
const router = useRouter();

const modules = [
    {
        id: 'stocks',
        icon: '📈',
        label: 'Stocks',
        links: [
            { to: '/dashboard', label: 'Portfolio' },
            { to: '/stocks', label: 'Stocks' },
            { to: '/transactions', label: 'Transactions' },
            { to: '/exposure', label: 'Exposure' },
            { to: '/settings', label: 'Settings' },
        ],
    },
];

const activeModule = ref('stocks');

const activeLinks = computed(() =>
    modules.find(m => m.id === activeModule.value)?.links ?? []
);

defineExpose({ activeLinks });

async function handleLogout() {
    await auth.logout();
    router.push('/login');
}
</script>
