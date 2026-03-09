<template>
    <nav class="fixed top-0 inset-x-0 z-50 bg-slate-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
            <!-- Logo -->
            <RouterLink to="/dashboard" class="font-bold text-lg tracking-tight text-white">
                📈 Stock Tracker
            </RouterLink>

            <!-- Nav links -->
            <div class="flex items-center gap-1">
                <RouterLink
                    v-for="link in links"
                    :key="link.to"
                    :to="link.to"
                    class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                    active-class="bg-slate-700 text-white"
                >
                    {{ link.label }}
                </RouterLink>
            </div>

            <!-- User menu -->
            <div class="flex items-center gap-3">
                <span class="text-slate-400 text-sm">{{ auth.state.user?.name }}</span>
                <button
                    @click="handleLogout"
                    class="px-3 py-1.5 text-sm bg-slate-700 hover:bg-slate-600 rounded-md transition-colors"
                >
                    Logout
                </button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { useAuth } from '../stores/auth';
import { useRouter } from 'vue-router';

const auth = useAuth();
const router = useRouter();

const links = [
    { to: '/dashboard', label: 'Portfolio' },
    { to: '/stocks', label: 'Stocks' },
    { to: '/transactions', label: 'Transactions' },
    { to: '/settings', label: 'Settings' },
];

async function handleLogout() {
    await auth.logout();
    router.push('/login');
}
</script>
