<template>
    <nav class="fixed top-0 inset-x-0 z-50 bg-slate-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-13">

            <!-- Left: logo -->
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

            <!-- Right: controls -->
            <div class="flex items-center gap-2">
                <!-- Privacy toggle + unlock popover -->
                <div class="relative" ref="privacyRef">
                    <button
                        @click="handlePrivacyToggle"
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

                    <!-- Unlock popover -->
                    <div
                        v-if="privacy.pendingUnlock.value"
                        class="absolute right-0 top-full mt-1 w-56 bg-slate-800 border border-slate-700 rounded-lg shadow-lg p-3 z-50"
                    >
                        <p class="text-xs text-slate-400 mb-2">Enter your password to show amounts</p>
                        <input
                            v-model="unlockPassword"
                            type="password"
                            placeholder="Password"
                            class="h-8 w-full rounded-md border border-slate-600 bg-slate-900 text-slate-100 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2"
                            @keydown.enter="submitUnlock"
                            @keydown.escape="privacy.cancelUnlock()"
                            ref="unlockInputRef"
                        />
                        <p v-if="unlockError" class="text-xs text-red-400 mb-2">{{ unlockError }}</p>
                        <div class="flex gap-2">
                            <button
                                @click="submitUnlock"
                                :disabled="unlocking"
                                class="flex-1 h-7 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-xs font-medium rounded-md transition-colors"
                            >{{ unlocking ? '…' : 'Confirm' }}</button>
                            <button
                                @click="privacy.cancelUnlock()"
                                class="flex-1 h-7 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium rounded-md transition-colors"
                            >Cancel</button>
                        </div>
                    </div>
                </div>

                <button
                    @click="theme.toggle()"
                    class="p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-base leading-none"
                    :title="theme.dark.value ? 'Switch to light mode' : 'Switch to dark mode'"
                >{{ theme.dark.value ? '☀' : '☾' }}</button>

                <!-- User dropdown (desktop) -->
                <div class="relative hidden sm:block" ref="userMenuRef">
                    <button
                        @click="userMenuOpen = !userMenuOpen"
                        class="flex items-center gap-1.5 px-2 py-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-sm"
                    >
                        <span>{{ auth.state.user?.name }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                        </svg>
                    </button>
                    <div
                        v-if="userMenuOpen"
                        class="absolute right-0 top-full mt-1 w-44 bg-slate-800 border border-slate-700 rounded-lg shadow-lg py-1 z-50"
                    >
                        <RouterLink
                            to="/user/settings"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                            @click="userMenuOpen = false"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                                <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.892 3.433-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.892-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                            </svg>
                            Settings
                        </RouterLink>
                        <div class="border-t border-slate-700 my-1"></div>
                        <button
                            @click="handleLogout"
                            class="w-full flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                                <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                            </svg>
                            Logout
                        </button>
                    </div>
                </div>

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
                <div class="border-t border-slate-700 mt-2 pt-2 flex flex-col gap-0.5">
                    <RouterLink
                        to="/user/settings"
                        class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                        @click="mobileMenuOpen = false"
                    >Account Settings</RouterLink>
                    <button
                        @click="handleLogout"
                        class="text-left px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                    >Logout</button>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useAuth } from '../stores/auth';
import { useTheme } from '../stores/theme';
import { usePrivacy } from '../stores/privacy';
import { useRouter, RouterLink } from 'vue-router';
import { modules } from '../navigation';
import api from '../api';

const auth    = useAuth();
const theme   = useTheme();
const privacy = usePrivacy();
const router  = useRouter();

const mobileMenuOpen = ref(false);
const userMenuOpen   = ref(false);
const userMenuRef    = ref(null);
const privacyRef     = ref(null);

// Unlock state
const unlockPassword = ref('');
const unlockError    = ref('');
const unlocking      = ref(false);
const unlockInputRef = ref(null);

function handlePrivacyToggle() {
    privacy.toggle(auth.state.user?.privacy_lock);
}

watch(() => privacy.pendingUnlock.value, async (val) => {
    if (val) {
        unlockPassword.value = '';
        unlockError.value    = '';
        await nextTick();
        unlockInputRef.value?.focus();
    }
});

async function submitUnlock() {
    if (!unlockPassword.value) return;
    unlocking.value = true;
    unlockError.value = '';
    try {
        await api.post('/auth/verify-password', { password: unlockPassword.value });
        privacy.unlock();
        unlockPassword.value = '';
    } catch {
        unlockError.value = 'Incorrect password.';
    } finally {
        unlocking.value = false;
    }
}

function onClickOutside(e) {
    if (userMenuRef.value && !userMenuRef.value.contains(e.target)) {
        userMenuOpen.value = false;
    }
    if (privacyRef.value && !privacyRef.value.contains(e.target)) {
        privacy.cancelUnlock();
    }
}
onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));

async function handleLogout() {
    mobileMenuOpen.value = false;
    await auth.logout();
    router.push('/login');
}
</script>
