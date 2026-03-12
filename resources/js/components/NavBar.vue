<template>
    <nav class="fixed top-0 inset-x-0 z-50 bg-slate-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-13">
            <!-- App name -->
            <span class="flex items-center gap-2 font-bold text-xl tracking-tight text-white select-none">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-7 h-7 shrink-0">
                    <rect width="32" height="32" rx="7" fill="#1e293b"/>
                    <polygon points="16,3.5 26.8,9.75 26.8,22.25 16,28.5 5.2,22.25 5.2,9.75" fill="none" stroke="#6366f1" stroke-width="1.5" stroke-linejoin="round"/>
                    <line x1="9.5" y1="12" x2="22.5" y2="12" stroke="#f1f5f9" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="9.5" y1="16" x2="20"   y2="16" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="9.5" y1="20" x2="16.5" y2="20" stroke="#f1f5f9" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Tracker
            </span>

            <!-- User controls -->
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="hidden sm:block text-slate-400 text-sm">{{ auth.state.user?.name }}</span>
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
                    class="hidden sm:block px-3 py-1.5 text-sm bg-slate-700 hover:bg-slate-600 rounded-md transition-colors"
                >Logout</button>
                <!-- Hamburger (mobile only) -->
                <button
                    class="md:hidden p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    aria-label="Toggle menu"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path v-if="!mobileMenuOpen" fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                        <path v-else d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile menu dropdown -->
        <div v-if="mobileMenuOpen" class="md:hidden bg-slate-800 border-t border-slate-700">
            <div class="px-3 py-2">
                <div v-for="mod in modules" :key="mod.id" class="mb-3">
                    <RouterLink
                        :to="mod.home"
                        class="flex items-center gap-1.5 px-3 py-1.5 mb-0.5 rounded-md text-sm font-semibold text-slate-200 hover:text-white hover:bg-slate-700 transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        <span class="leading-none">{{ mod.icon }}</span>
                        <span>{{ mod.label }}</span>
                    </RouterLink>
                    <div class="flex flex-col gap-0.5 pl-3">
                        <RouterLink
                            v-for="link in mod.links"
                            :key="link.to"
                            :to="link.to"
                            class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                            active-class="text-white bg-slate-700"
                            @click="mobileMenuOpen = false"
                        >
                            {{ link.label }}
                        </RouterLink>
                    </div>
                </div>
                <button
                    @click="handleLogout"
                    class="sm:hidden w-full text-left px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-700 transition-colors mt-1 border-t border-slate-700 pt-3"
                >Logout</button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref } from 'vue';
import { useAuth } from '../stores/auth';
import { useTheme } from '../stores/theme';
import { usePrivacy } from '../stores/privacy';
import { useRouter } from 'vue-router';
import { modules } from '../navigation';

const auth = useAuth();
const theme = useTheme();
const privacy = usePrivacy();
const router = useRouter();

const mobileMenuOpen = ref(false);

async function handleLogout() {
    mobileMenuOpen.value = false;
    await auth.logout();
    router.push('/login');
}
</script>
