<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors">
        <NavBar v-if="auth.state.user" />
        <main :class="auth.state.user ? 'pt-16' : ''">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import NavBar from './components/NavBar.vue';
import { useAuth } from './stores/auth';
import { useTheme } from './stores/theme';

const auth = useAuth();
useTheme();
</script>
