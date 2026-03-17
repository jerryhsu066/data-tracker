<template>
    <Transition name="toast">
        <div
            v-if="show"
            class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-80 z-50 bg-white dark:bg-gray-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg p-4 flex items-center gap-3"
        >
            <div class="flex-1">
                <p class="text-sm font-medium text-slate-900 dark:text-white">Install Tracker</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Add to your home screen for quick access</p>
            </div>
            <button
                @click="install"
                class="shrink-0 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded-md transition-colors"
            >Install</button>
            <button
                @click="dismiss"
                class="shrink-0 p-1 text-slate-500 hover:text-white transition-colors"
                aria-label="Dismiss"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const show = ref(false);
let deferredPrompt = null;

function onBeforeInstall(e) {
    e.preventDefault();
    deferredPrompt = e;
    // Don't show if user already dismissed recently
    const dismissed = localStorage.getItem('pwa-install-dismissed');
    if (dismissed && Date.now() - Number(dismissed) < 7 * 86400 * 1000) return;
    show.value = true;
}

async function install() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    show.value = false;
}

function dismiss() {
    show.value = false;
    localStorage.setItem('pwa-install-dismissed', String(Date.now()));
}

onMounted(() => window.addEventListener('beforeinstallprompt', onBeforeInstall));
onUnmounted(() => window.removeEventListener('beforeinstallprompt', onBeforeInstall));
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(1rem);
}
</style>
