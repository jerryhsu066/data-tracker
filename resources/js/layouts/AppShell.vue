<template>
    <div class="min-h-screen overflow-x-hidden bg-slate-50 dark:bg-gray-950 text-slate-900 dark:text-slate-100">
        <TopBar @toggle-drawer="drawerOpen = !drawerOpen" />
        <AppSidebar class="hidden md:flex" />
        <MobileDrawer v-model:open="drawerOpen" class="md:hidden" />
        <main
            class="pt-12 transition-[padding] duration-200"
            :class="mainPadding"
        >
            <slot />
        </main>
        <PwaInstallPrompt />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import TopBar from './TopBar.vue';
import AppSidebar from './AppSidebar.vue';
import MobileDrawer from './MobileDrawer.vue';
import PwaInstallPrompt from '../components/PwaInstallPrompt.vue';
import { useSidebar } from '../stores/sidebar';

const { collapsed } = useSidebar();
const drawerOpen = ref(false);

const mainPadding = computed(() => {
    // md+ gets sidebar padding, mobile gets none
    return collapsed.value ? 'md:pl-16' : 'md:pl-55';
});
</script>
