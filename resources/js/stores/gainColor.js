import { computed } from 'vue';
import { useAuth } from './auth';

export function useGainColor() {
    const { state } = useAuth();

    const gainIsRed = computed(() => !!state.user?.gain_is_red);

    // Returns Tailwind text-color classes for a numeric gain/loss value.
    function gainClass(value) {
        const positive = Number(value) >= 0;
        if (gainIsRed.value) {
            return positive
                ? 'text-red-500 dark:text-red-400'
                : 'text-emerald-600 dark:text-emerald-400';
        }
        return positive
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-red-500 dark:text-red-400';
    }

    // Returns a hex color string for Chart.js datasets.
    function gainHex(value) {
        const positive = Number(value) >= 0;
        if (gainIsRed.value) {
            return positive ? '#ef4444' : '#10b981';
        }
        return positive ? '#10b981' : '#ef4444';
    }

    return { gainClass, gainHex, gainIsRed };
}
