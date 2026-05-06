import { reactive, readonly } from 'vue';

const state = reactive({
    deferredPrompt: null,
    dismissed: false,
});

// Listen once globally — the event fires before Vue mounts
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    state.deferredPrompt = e;
});

window.addEventListener('appinstalled', () => {
    state.deferredPrompt = null;
});

export function usePwa() {
    const isIOS = /ipad|iphone|ipod/i.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    const canInstall = !isStandalone && !!state.deferredPrompt;

    async function install() {
        if (!state.deferredPrompt) return false;
        state.deferredPrompt.prompt();
        const { outcome } = await state.deferredPrompt.userChoice;
        state.deferredPrompt = null;
        return outcome === 'accepted';
    }

    function dismiss() {
        state.dismissed = true;
        localStorage.setItem('pwa-install-dismissed', String(Date.now()));
    }

    function wasRecentlyDismissed() {
        const ts = localStorage.getItem('pwa-install-dismissed');
        return ts && Date.now() - Number(ts) < 7 * 86400 * 1000;
    }

    return {
        state: readonly(state),
        isIOS,
        isStandalone,
        canInstall: !isStandalone && !!state.deferredPrompt,
        install,
        dismiss,
        wasRecentlyDismissed,
    };
}
