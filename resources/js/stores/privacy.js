import { ref, watch } from 'vue';

const hidden        = ref(localStorage.getItem('privacy-mode') === 'true');
const pendingUnlock = ref(false);

watch(hidden, () => {
    localStorage.setItem('privacy-mode', hidden.value ? 'true' : 'false');
});

export function usePrivacy() {
    function toggle(privacyLock = false) {
        if (hidden.value && privacyLock) {
            // Revealing while lock is on — caller must handle the prompt
            pendingUnlock.value = true;
            return;
        }
        pendingUnlock.value = false;
        hidden.value = !hidden.value;
    }

    function unlock() {
        pendingUnlock.value = false;
        hidden.value = false;
    }

    function cancelUnlock() {
        pendingUnlock.value = false;
    }

    return { hidden, pendingUnlock, toggle, unlock, cancelUnlock };
}
