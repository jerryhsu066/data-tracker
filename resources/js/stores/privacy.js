import { ref, watch } from 'vue';

const hidden = ref(localStorage.getItem('privacy-mode') === 'true');

watch(hidden, () => {
    localStorage.setItem('privacy-mode', hidden.value ? 'true' : 'false');
});

export function usePrivacy() {
    return { hidden, toggle: () => { hidden.value = !hidden.value; } };
}
