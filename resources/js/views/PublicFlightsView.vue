<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
        <!-- Header -->
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

        <!-- Content -->
        <main class="max-w-5xl mx-auto px-4 py-6 space-y-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h1 class="text-2xl font-bold">Flight Overview</h1>
                <select v-model="selectedYear" class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Years</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

            <template v-else>
                <!-- Map -->
                <div ref="inlineContainer"
                     class="rounded-xl overflow-hidden isolate border border-slate-200 dark:border-slate-700 portrait:aspect-[4/3] portrait:!h-auto"
                     style="height: 420px">
                    <FlightMap ref="flightMapRef" :flights="flights">
                        <button @click="toggleFullscreen"
                                class="absolute top-3 right-3 z-[1001] h-8 w-8 flex items-center justify-center rounded-md bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-slate-600 dark:text-slate-300"
                                :title="mapFullscreen ? 'Exit fullscreen' : 'Fullscreen'">
                            <svg v-if="!mapFullscreen" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M3 3h4V1H1v6h2V3zm10-2v2h4v4h2V1h-6zM3 13H1v6h6v-2H3v-4zm14 4h-4v2h6v-6h-2v4z"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7 1v2H3v4H1V1h6zm6 0h6v6h-2V3h-4V1zM1 13h2v4h4v2H1v-6zm12 4h4v-4h2v6h-6v-2z"/>
                            </svg>
                        </button>
                    </FlightMap>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <StatCard label="Total Flights" :value="stats.total_flights" />
                    <StatCard label="Total Distance" :value="totalDistanceFormatted" />
                    <StatCard label="Unique Airports" :value="stats.unique_airports" />
                    <StatCard label="Unique Airlines" :value="stats.unique_airlines" />
                    <StatCard label="Countries" :value="uniqueCountries" />
                    <StatCard label="Most Visited" :value="stats.most_visited_airport || '—'" />
                </div>

                <div v-if="Object.keys(stats.flights_by_year).length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Flights by Year</h2>
                    <div class="flex flex-wrap gap-4">
                        <div v-for="(count, year) in stats.flights_by_year" :key="year" class="text-center">
                            <div class="text-2xl font-bold">{{ count }}</div>
                            <div class="text-xs text-slate-400">{{ year }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(stats.flights_by_class).length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Flights by Class</h2>
                    <div class="flex flex-wrap gap-4">
                        <div v-for="(count, cls) in stats.flights_by_class" :key="cls" class="text-center">
                            <div class="text-2xl font-bold">{{ count }}</div>
                            <div class="text-xs text-slate-400 capitalize">{{ cls }}</div>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <!-- Fullscreen overlay -->
        <Teleport to="body">
            <div v-show="mapFullscreen"
                 ref="fullscreenContainer"
                 class="fixed z-[35]"
                 :style="{ top: '48px', bottom: '0', left: '0', right: '0', opacity: fsOpacity, transform: fsTransform, transition: 'opacity 0.25s ease, transform 0.25s ease' }">
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, h, nextTick } from 'vue';
import api from '../api';
import { ensureLoadedPublic, getDistance, getUniqueCountries } from '../data/airportLookup';
import { useTheme } from '../stores/theme';
import FlightMap from '../components/FlightMap.vue';

const { dark: isDark, toggle: toggleTheme } = useTheme();

// ── Data ─────────────────────────────────────────────────────────────────

const loading = ref(true);
const flights = ref([]);
const allFlights = ref([]);

const currentYear = new Date().getFullYear();
const selectedYear = ref('');

const years = computed(() => {
    const yrs = new Set(allFlights.value.map(f => new Date(f.flight_date).getFullYear()));
    for (let y = currentYear; y >= currentYear - 5; y--) yrs.add(y);
    return [...yrs].sort((a, b) => b - a);
});

const stats = computed(() => {
    const fs = flights.value;
    const airportCounts = {};
    const airlineSet = new Set();
    const byYear = {};
    const byClass = {};

    for (const f of fs) {
        airportCounts[f.departure_airport] = (airportCounts[f.departure_airport] || 0) + 1;
        airportCounts[f.arrival_airport]   = (airportCounts[f.arrival_airport]   || 0) + 1;
        airlineSet.add(f.airline);
        const yr = String(f.flight_date).slice(0, 4);
        byYear[yr] = (byYear[yr] || 0) + 1;
        if (f.seat_class) byClass[f.seat_class] = (byClass[f.seat_class] || 0) + 1;
    }

    const mostVisited = Object.entries(airportCounts).sort(([, a], [, b]) => b - a)[0]?.[0] ?? null;

    return {
        total_flights:         fs.length,
        unique_airports:       Object.keys(airportCounts).length,
        unique_airlines:       airlineSet.size,
        most_visited_airport:  mostVisited,
        flights_by_year:       byYear,
        flights_by_class:      byClass,
    };
});

const StatCard = {
    props: { label: String, value: [String, Number] },
    setup(props) {
        return () => h('div', { class: 'bg-white dark:bg-slate-800 rounded-xl shadow-sm p-4' }, [
            h('div', { class: 'text-2xl font-bold text-slate-900 dark:text-slate-100' }, String(props.value)),
            h('div', { class: 'text-xs text-slate-400 mt-1' }, props.label),
        ]);
    },
};

const totalDistance = computed(() => {
    let total = 0;
    for (const f of flights.value) total += getDistance(f.departure_airport, f.arrival_airport);
    return total;
});

const totalDistanceFormatted = computed(() => `${Math.round(totalDistance.value).toLocaleString()} km`);

const uniqueCountries = computed(() => {
    const codes = new Set();
    for (const f of flights.value) { codes.add(f.departure_airport); codes.add(f.arrival_airport); }
    return getUniqueCountries(codes).size;
});

async function fetchData() {
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

watch(selectedYear, fetchData);

// ── Fullscreen ────────────────────────────────────────────────────────────

const mapFullscreen = ref(false);
const inlineContainer = ref(null);
const fullscreenContainer = ref(null);
const flightMapRef = ref(null);
const fsOpacity = ref('0');
const fsTransform = ref('scale(0.97)');

function toggleFullscreen() {
    mapFullscreen.value ? exitFullscreen() : enterFullscreen();
}

function enterFullscreen() {
    fsOpacity.value = '0';
    fsTransform.value = 'scale(0.97)';
    mapFullscreen.value = true;
    nextTick(() => {
        if (flightMapRef.value?.$el && fullscreenContainer.value) {
            fullscreenContainer.value.insertBefore(flightMapRef.value.$el, fullscreenContainer.value.firstChild);
            nextTick(() => {
                flightMapRef.value?.invalidate?.();
                requestAnimationFrame(() => {
                    fsOpacity.value = '1';
                    fsTransform.value = 'scale(1)';
                });
            });
        }
    });
}

function exitFullscreen() {
    fsOpacity.value = '0';
    fsTransform.value = 'scale(0.97)';
    setTimeout(() => {
        if (flightMapRef.value?.$el && inlineContainer.value) {
            inlineContainer.value.appendChild(flightMapRef.value.$el);
            nextTick(() => { flightMapRef.value?.invalidate?.(); });
        }
        mapFullscreen.value = false;
    }, 250);
}

function onKeydown(e) {
    if (e.key === 'Escape' && mapFullscreen.value) exitFullscreen();
}

onMounted(() => { document.addEventListener('keydown', onKeydown); });
onUnmounted(() => { document.removeEventListener('keydown', onKeydown); });
</script>
