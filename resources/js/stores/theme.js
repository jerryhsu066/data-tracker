import { ref, watch } from 'vue';

const dark = ref(
    localStorage.getItem('theme') === 'dark' ||
    (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
);

function apply() {
    document.documentElement.classList.toggle('dark', dark.value);
}
apply();

watch(dark, () => {
    localStorage.setItem('theme', dark.value ? 'dark' : 'light');
    apply();
});

export function useTheme() {
    return { dark, toggle: () => { dark.value = !dark.value; } };
}
