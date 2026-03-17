import { ref, watch } from 'vue';

const collapsed = ref(localStorage.getItem('sidebar-collapsed') === 'true');

watch(collapsed, (val) => {
    localStorage.setItem('sidebar-collapsed', val ? 'true' : 'false');
});

export function useSidebar() {
    function toggle() {
        collapsed.value = !collapsed.value;
    }

    return { collapsed, toggle };
}
