<template>
    <NavBar v-if="auth.state.user" />
    <main :class="auth.state.user ? 'pt-16' : ''">
        <RouterView />
    </main>
</template>

<script setup>
import NavBar from './components/NavBar.vue';
import { useAuth } from './stores/auth';

const auth = useAuth();
</script>
