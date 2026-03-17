<template>
    <template v-if="auth.state.user">
        <AppShell>
            <RouterView />
        </AppShell>
    </template>

    <template v-else>
        <div class="min-h-screen bg-slate-50 dark:bg-gray-950">
            <RouterView />
        </div>
    </template>
</template>

<script setup>
import { useAuth } from './stores/auth';
import { useTheme } from './stores/theme';
import AppShell from './layouts/AppShell.vue';

const auth = useAuth();
useTheme();
</script>
