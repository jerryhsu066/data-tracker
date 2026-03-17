<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div v-if="open" class="fixed inset-0 z-50 flex">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="$emit('update:open', false)"></div>

                <!-- Drawer panel -->
                <div class="relative w-72 max-w-[80vw] bg-white dark:bg-gray-950 border-r border-slate-200 dark:border-slate-800 flex flex-col h-full">
                    <!-- Header -->
                    <div class="flex items-center gap-2 px-4 h-12 border-b border-slate-200 dark:border-slate-800 shrink-0">
                        <span class="font-bold text-lg text-slate-900 dark:text-white select-none">Tracker</span>
                    </div>

                    <!-- Module navigation -->
                    <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-3">
                        <div v-for="mod in modules" :key="mod.id">
                            <p class="px-2 mb-1 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">{{ mod.label }}</p>
                            <RouterLink
                                v-for="tab in mod.tabs"
                                :key="tab.id"
                                :to="tab.path"
                                class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                                :class="route.path === tab.path
                                    ? 'bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400'
                                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'"
                                @click="$emit('update:open', false)"
                            >{{ tab.label }}</RouterLink>
                        </div>
                    </nav>

                    <!-- Footer: account actions -->
                    <div class="border-t border-slate-200 dark:border-slate-800 px-3 py-3 space-y-1 shrink-0">
                        <RouterLink
                            to="/user/settings"
                            class="flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 transition-colors"
                            @click="$emit('update:open', false)"
                        >Account Settings</RouterLink>
                        <button
                            @click="handleLogout"
                            class="w-full text-left px-3 py-2.5 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 transition-colors"
                        >Logout</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { useRoute, RouterLink, useRouter } from 'vue-router';
import { modules } from '../modules';
import { useAuth } from '../stores/auth';

defineProps({ open: Boolean });
const emit = defineEmits(['update:open']);

const route  = useRoute();
const router = useRouter();
const auth   = useAuth();

async function handleLogout() {
    emit('update:open', false);
    await auth.logout();
    router.push('/login');
}
</script>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.2s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
    transition: transform 0.2s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
    transform: translateX(-100%);
}
</style>
