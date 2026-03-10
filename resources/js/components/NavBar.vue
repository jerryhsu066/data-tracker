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
                    @click="privacy.toggle()"
                    class="p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors leading-none"
                    :title="privacy.hidden.value ? 'Show amounts' : 'Hide amounts'"
                >
                    <svg v-if="!privacy.hidden.value" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                        <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                        <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                    </svg>
                </button>
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
import { usePrivacy } from '../stores/privacy';
import { useRouter } from 'vue-router';

const auth = useAuth();
const theme = useTheme();
const privacy = usePrivacy();
const router = useRouter();

const modules = [
    {
        id: 'stocks',
        icon: '📈',
        label: 'Stocks',
        links: [
            { to: '/home', label: 'Home' },
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
