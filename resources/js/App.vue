<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors">
        <template v-if="auth.state.user">
            <NavBar ref="navBar" />

            <!-- Left sidebar -->
            <aside class="fixed top-13 left-0 bottom-0 w-40 bg-slate-800 border-r border-slate-700/60 z-40 flex flex-col pt-4 px-2">
                <div class="flex flex-col gap-0.5">
                    <RouterLink
                        v-for="link in mainLinks"
                        :key="link.to"
                        :to="link.to"
                        class="px-3 py-2 rounded-md text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                        active-class="text-white bg-slate-700"
                    >
                        {{ link.label }}
                    </RouterLink>
                </div>

                <div class="mt-auto mb-4 flex flex-col gap-0.5">
                    <RouterLink
                        v-for="link in bottomLinks"
                        :key="link.to"
                        :to="link.to"
                        class="px-3 py-2 rounded-md text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                        active-class="text-white bg-slate-700"
                    >
                        {{ link.label }}
                    </RouterLink>
                </div>
            </aside>

            <main class="pt-13 pl-40">
                <RouterView />
            </main>
        </template>

        <template v-else>
            <main>
                <RouterView />
            </main>
        </template>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import NavBar from './components/NavBar.vue';
import { useAuth } from './stores/auth';
import { useTheme } from './stores/theme';

const auth = useAuth();
useTheme();

const navBar = ref(null);

const mainLinks = computed(() =>
    (navBar.value?.activeLinks ?? []).filter(l => l.label !== 'Settings')
);
const bottomLinks = computed(() =>
    (navBar.value?.activeLinks ?? []).filter(l => l.label === 'Settings')
);
</script>
