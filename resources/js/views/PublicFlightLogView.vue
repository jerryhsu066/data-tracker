<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
        <header class="h-12 flex items-center justify-between px-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
            <nav class="flex items-center gap-1">
                <RouterLink to="/"
                    class="px-3 h-8 flex items-center text-sm font-medium rounded-md transition-colors"
                    :class="$route.path === '/' ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'">
                    Map
                </RouterLink>
                <RouterLink to="/log"
                    class="px-3 h-8 flex items-center text-sm font-medium rounded-md transition-colors"
                    :class="$route.path === '/log' ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'">
                    Log
                </RouterLink>
            </nav>
            <button @click="toggleTheme"
                    class="h-8 w-8 flex items-center justify-center rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors"
                    :title="isDark ? 'Light mode' : 'Dark mode'">
                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                </svg>
            </button>
        </header>

        <main class="max-w-5xl mx-auto px-4 py-6 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h1 class="text-2xl font-bold">Flight Log</h1>
                <select v-model="selectedYear" class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Years</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

            <div v-else-if="flights.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
                <p class="text-slate-400 text-lg">No flights yet.</p>
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-x-auto">
                <table class="text-sm border-collapse w-full font-mono">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                            <th class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Date</th>
                            <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Flight</th>
                            <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">From</th>
                            <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">To</th>
                            <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Aircraft</th>
                            <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Dep</th>
                            <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Arr</th>
                            <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(f, i) in flights" :key="f.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors"
                            :class="todayBorderClass(f, i)">
                            <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">{{ f.flight_date?.split('T')[0] }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <img :src="airlineLogo(f.flight_number)" :alt="f.airline"
                                        class="h-6 w-6 object-contain rounded shrink-0"
                                        @error="e => e.currentTarget.style.display = 'none'" />
                                    <span class="text-slate-700 dark:text-slate-300">{{ f.flight_number }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                <span class="mr-1">{{ countryFlag(f.departure_airport) }}</span>
                                <span>{{ f.departure_airport }}</span>
                                <span class="portrait:hidden text-xs text-slate-400 dark:text-slate-500 ml-1">{{ airportCity(f.departure_airport) }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                <span class="mr-1">{{ countryFlag(f.arrival_airport) }}</span>
                                <span>{{ f.arrival_airport }}</span>
                                <span class="portrait:hidden text-xs text-slate-400 dark:text-slate-500 ml-1">{{ airportCity(f.arrival_airport) }}</span>
                            </td>
                            <td class="portrait:hidden px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ formatAircraft(f.aircraft_type) }}</td>
                            <td class="portrait:hidden px-3 py-2.5 whitespace-nowrap">
                                <template v-if="formatTime(f.departure_time, f.departure_airport)">
                                    <span class="text-slate-700 dark:text-slate-300">{{ formatTime(f.departure_time, f.departure_airport).time }}</span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500 ml-1">{{ formatTime(f.departure_time, f.departure_airport).offset }}</span>
                                </template>
                                <span v-else class="text-xs text-slate-400 dark:text-slate-500">—</span>
                            </td>
                            <td class="portrait:hidden px-3 py-2.5 whitespace-nowrap">
                                <template v-if="formatTime(f.arrival_time, f.arrival_airport)">
                                    <span class="text-slate-700 dark:text-slate-300">
                                        {{ formatTime(f.arrival_time, f.arrival_airport).time }}<sup v-if="dayOffset(f) > 0" class="text-[0.6em] font-semibold ml-0.5">+{{ dayOffset(f) }}</sup>
                                    </span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500 ml-1">{{ formatTime(f.arrival_time, f.arrival_airport).offset }}</span>
                                </template>
                                <span v-else class="text-xs text-slate-400 dark:text-slate-500">—</span>
                            </td>
                            <td class="portrait:hidden px-3 py-2.5 text-slate-700 dark:text-slate-300 capitalize">{{ f.seat_class || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../api';
import { useTheme } from '../stores/theme';
import { getAirport, ensureLoadedPublic } from '../data/airportLookup';

const { dark: isDark, toggle: toggleTheme } = useTheme();

const flights = ref([]);
const allFlights = ref([]);
const loading = ref(true);
const selectedYear = ref('');

const currentYear = new Date().getFullYear();
const years = computed(() => {
    const yrs = new Set(allFlights.value.map(f => new Date(f.flight_date).getFullYear()));
    for (let y = currentYear; y >= currentYear - 5; y--) yrs.add(y);
    return [...yrs].sort((a, b) => b - a);
});

async function fetchFlights() {
    loading.value = true;
    try {
        const params = selectedYear.value ? { year: selectedYear.value } : {};
        const { data } = await api.get('/public/flights', { params });
        flights.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await ensureLoadedPublic();
    const { data } = await api.get('/public/flights');
    allFlights.value = data;
    flights.value = data;
    loading.value = false;
});

watch(selectedYear, fetchFlights);

const today = new Date().toISOString().slice(0, 10);

function todayBorderClass(f, i) {
    const thisDate = f.flight_date?.split('T')[0];
    const nextFlight = flights.value[i + 1];
    const nextDate = nextFlight?.flight_date?.split('T')[0];
    if (thisDate > today && nextDate && nextDate <= today) {
        return 'border-b-2 border-slate-300 dark:border-slate-500';
    }
    return 'border-b border-slate-100 dark:border-slate-700/50';
}

function airportCity(iata) {
    return getAirport(iata)?.city || '';
}

function countryFlag(iata) {
    const code = getAirport(iata)?.country_code;
    if (!code || code.length !== 2) return '';
    return String.fromCodePoint(...[...code].map(c => 0x1F1E6 + c.charCodeAt(0) - 65));
}

function airlineLogo(flightNumber) {
    const code = flightNumber?.match(/^[A-Z]{2,3}/)?.[0] || '';
    return code ? `https://www.gstatic.com/flights/airline_logos/70px/${code}.png` : '';
}

const aircraftIataMap = {
    '318': 'A318', '319': 'A319', '320': 'A320', '321': 'A321',
    '32A': 'A320n', '32B': 'A321n', '32N': 'A320n', '32Q': 'A321n',
    '332': 'A332', '333': 'A333',
    '342': 'A342', '343': 'A343', '345': 'A345', '346': 'A346',
    '350': 'A350', '351': 'A351', '359': 'A359', '35K': 'A35K',
    '388': 'A388',
    '732': 'B732', '733': 'B733', '734': 'B734', '735': 'B735',
    '736': 'B736', '737': 'B737', '738': 'B738', '739': 'B739',
    '73G': 'B73G', '73H': 'B73H', '73W': 'B73W',
    '7M7': 'B737M', '7M8': 'B737M', '7M9': 'B737M',
    '741': 'B741', '742': 'B742', '743': 'B743', '744': 'B744', '748': 'B748',
    '752': 'B752', '753': 'B753',
    '762': 'B762', '763': 'B763', '764': 'B764', '76W': 'B763', '76X': 'B764',
    '772': 'B772', '773': 'B773', '77L': 'B77L', '77W': 'B77W',
    '788': 'B788', '789': 'B789', '78X': 'B78X',
    'E70': 'E170', 'E75': 'E175', 'E90': 'E190', 'E95': 'E195', 'E7W': 'E175',
    'CR2': 'CRJ2', 'CR7': 'CRJ7', 'CR9': 'CRJ9',
    'AT4': 'ATR42', 'AT7': 'ATR72',
};

function formatAircraft(type) {
    if (!type) return '—';
    return aircraftIataMap[type.toUpperCase()] ?? type;
}

function dayOffset(f) {
    if (!f.departure_time || !f.arrival_time) return 0;
    const depDate = f.departure_time.slice(0, 10);
    const arrDate = f.arrival_time.slice(0, 10);
    return Math.round((new Date(arrDate) - new Date(depDate)) / 86400000);
}

function formatTime(dt, iata) {
    if (!dt) return null;
    const tz = getAirport(iata)?.tz;
    const time = dt.slice(11, 16);
    const ref = new Date(dt.length === 16 ? dt + ':00Z' : dt);
    const offsetRaw = new Intl.DateTimeFormat('en', {
        timeZone: tz || Intl.DateTimeFormat().resolvedOptions().timeZone,
        timeZoneName: 'shortOffset',
    }).formatToParts(ref).find(p => p.type === 'timeZoneName')?.value || '';
    const offset = '(' + offsetRaw.replace('GMT', 'UTC') + ')';
    return { time, offset };
}
</script>
