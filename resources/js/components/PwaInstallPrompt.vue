<template>
    <Transition name="toast">
        <div
            v-if="show"
            class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-80 z-50 bg-white dark:bg-gray-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg p-4"
        >
            <p class="text-sm font-medium text-slate-900 dark:text-white mb-0.5">Install Tracker</p>

            <!-- Android / desktop: native prompt -->
            <template v-if="!pwa.isIOS">
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Add to your home screen for quick access.</p>
                <div class="flex items-center gap-2">
                    <button @click="doInstall"
                        class="flex-1 h-8 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded-md transition-colors">
                        Install
                    </button>
                    <button @click="doDismiss"
                        class="h-8 px-3 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                        Not now
                    </button>
                </div>
            </template>

            <!-- iOS: manual instructions -->
            <template v-else>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">
                    Tap
                    <svg class="inline w-3.5 h-3.5 mx-0.5 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                    <span class="font-semibold">Share</span> then <span class="font-semibold">Add to Home Screen</span>.
                </p>
                <button @click="doDismiss"
                    class="mt-2 text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                    Dismiss
                </button>
            </template>

            <button @click="doDismiss"
                class="absolute top-2 right-2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                aria-label="Dismiss">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePwa } from '../stores/pwa';

const pwa  = usePwa();
const show = ref(false);

onMounted(() => {
    if (pwa.isStandalone || pwa.wasRecentlyDismissed()) return;
    if (pwa.isIOS) {
        show.value = true;
    } else if (pwa.canInstall) {
        show.value = true;
    } else {
        // Poll briefly for the deferred prompt in case it fires after mount
        const check = setInterval(() => {
            if (pwa.state.deferredPrompt) {
                show.value = true;
                clearInterval(check);
            }
        }, 500);
        setTimeout(() => clearInterval(check), 10000);
    }
});

async function doInstall() {
    await pwa.install();
    show.value = false;
}

function doDismiss() {
    pwa.dismiss();
    show.value = false;
}
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(1rem); }
</style>
