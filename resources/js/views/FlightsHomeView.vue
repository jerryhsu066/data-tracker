<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Flight Overview</h1>
            <select v-model="selectedYear" class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Years</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Inline map container — map lives here when not fullscreen -->
            <div ref="inlineContainer"
                 class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 portrait:aspect-[4/3] portrait:!h-auto"
                 style="height: 420px">
                <FlightMap ref="flightMapRef" :flights="flights">
                    <button @click="toggleFullscreen"
                            class="absolute top-3 right-3 z-[1001] h-8 w-8 flex items-center justify-center rounded-md bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-slate-600 dark:text-slate-300"
                            :title="mapFullscreen ? 'Exit fullscreen' : 'Fullscreen'">
                        <!-- Expand icon when inline, shrink icon when fullscreen -->
                        <svg v-if="!mapFullscreen" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 3h4V1H1v6h2V3zm10-2v2h4v4h2V1h-6zM3 13H1v6h6v-2H3v-4zm14 4h-4v2h6v-6h-2v4z"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M7 1v2H3v4H1V1h6zm6 0h6v6h-2V3h-4V1zM1 13h2v4h4v2H1v-6zm12 4h4v-4h2v6h-6v-2z"/>
                        </svg>
                    </button>
                </FlightMap>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <StatCard label="Total Flights" :value="stats.total_flights" />
                <StatCard label="Total Distance" :value="totalDistanceFormatted" />
                <StatCard label="Unique Airports" :value="stats.unique_airports" />
                <StatCard label="Unique Airlines" :value="stats.unique_airlines" />
                <StatCard label="Countries" :value="uniqueCountries" />
                <StatCard label="Most Visited" :value="stats.most_visited_airport || '—'" />
            </div>

            <!-- Flights by Year -->
            <div v-if="Object.keys(stats.flights_by_year).length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Flights by Year</h2>
                <div class="flex flex-wrap gap-4">
                    <div v-for="(count, year) in stats.flights_by_year" :key="year" class="text-center">
                        <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ count }}</div>
                        <div class="text-xs text-slate-400">{{ year }}</div>
                    </div>
                </div>
            </div>

            <!-- Flights by Class -->
            <div v-if="Object.keys(stats.flights_by_class).length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Flights by Class</h2>
                <div class="flex flex-wrap gap-4">
                    <div v-for="(count, cls) in stats.flights_by_class" :key="cls" class="text-center">
                        <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ count }}</div>
                        <div class="text-xs text-slate-400 capitalize">{{ cls }}</div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Fullscreen overlay — teleported to body for z-index independence -->
    <Teleport to="body">
        <div v-show="mapFullscreen"
             ref="fullscreenContainer"
             class="fixed z-[35]"
             :style="{ ...fullscreenStyle, opacity: fsOpacity, transform: fsTransform, transition: 'left 0.2s ease, opacity 0.25s ease, transform 0.25s ease' }">
            <!-- Map gets reparented here via DOM manipulation -->
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, h, nextTick } from 'vue';
import api from '../api';
import { ensureLoaded, getDistance, getUniqueCountries } from '../data/airportLookup';
import { useSidebar } from '../stores/sidebar';
import FlightMap from '../components/FlightMap.vue';

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
        total_flights:        fs.length,
        unique_airports:      Object.keys(airportCounts).length,
        unique_airlines:      airlineSet.size,
        most_visited_airport: mostVisited,
        flights_by_year:      byYear,
        flights_by_class:     byClass,
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
        const { data } = await api.get('/flights', { params });
        flights.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await ensureLoaded();
    const { data } = await api.get('/flights');
    allFlights.value = data;
    flights.value = data;
    loading.value = false;
});

watch(selectedYear, fetchData);

// ── Fullscreen (single map instance, DOM reparenting) ────────────────────

const { collapsed } = useSidebar();
const mapFullscreen = ref(false);
const inlineContainer = ref(null);
const fullscreenContainer = ref(null);
const flightMapRef = ref(null);
const fsOpacity = ref('0');
const fsTransform = ref('scale(0.97)');

const fullscreenStyle = computed(() => {
    const left = window.innerWidth >= 768 ? (collapsed.value ? '64px' : '220px') : '0';
    return { top: '48px', bottom: '0', right: '0', left };
});

function toggleFullscreen() {
    if (mapFullscreen.value) {
        exitFullscreen();
    } else {
        enterFullscreen();
    }
}

function enterFullscreen() {
    fsOpacity.value = '0';
    fsTransform.value = 'scale(0.97)';
    mapFullscreen.value = true;
    nextTick(() => {
        // Move the map component's root element into the fullscreen container
        if (flightMapRef.value?.$el && fullscreenContainer.value) {
            fullscreenContainer.value.insertBefore(flightMapRef.value.$el, fullscreenContainer.value.firstChild);
            nextTick(() => {
                flightMapRef.value?.invalidate?.();
                // Animate in on next frame
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
    // Wait for transition to finish, then move map back
    setTimeout(() => {
        if (flightMapRef.value?.$el && inlineContainer.value) {
            inlineContainer.value.appendChild(flightMapRef.value.$el);
            nextTick(() => {
                flightMapRef.value?.invalidate?.();
            });
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

