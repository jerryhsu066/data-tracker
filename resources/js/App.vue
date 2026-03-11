<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors">
        <template v-if="auth.state.user">
            <NavBar />

            <!-- Left sidebar (desktop only) -->
            <aside class="hidden md:flex fixed top-13 left-0 bottom-0 w-44 bg-slate-800 border-r border-slate-700/60 z-40 flex-col pt-3 px-2 overflow-y-auto">
                <div v-for="mod in modules" :key="mod.id" class="mb-4">
                    <!-- Module header — clickable, links to module home -->
                    <RouterLink
                        :to="mod.home"
                        class="flex items-center gap-1.5 px-3 py-1.5 mb-0.5 rounded-md text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-700 transition-colors"
                    >
                        <span class="text-base leading-none">{{ mod.icon }}</span>
                        <span>{{ mod.label }}</span>
                    </RouterLink>
                    <!-- Module links (indented) -->
                    <div class="flex flex-col gap-0.5 pl-3">
                        <RouterLink
                            v-for="link in mod.links"
                            :key="link.to"
                            :to="link.to"
                            class="px-3 py-1.5 rounded-md text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                            active-class="text-white bg-slate-700"
                        >
                            {{ link.label }}
                        </RouterLink>
                    </div>
                </div>
            </aside>

            <main class="pt-13 md:pl-44">
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
import NavBar from './components/NavBar.vue';
import { useAuth } from './stores/auth';
import { useTheme } from './stores/theme';
import { modules } from './navigation';

const auth = useAuth();
useTheme();
</script>
