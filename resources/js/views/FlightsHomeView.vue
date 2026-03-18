<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Flight Overview</h1>

        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <template v-else>
            <!-- Empty state -->
            <div v-if="stats.total_flights === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
                <p class="text-slate-400 text-lg">No flights recorded yet.</p>
                <p class="text-slate-400 text-sm mt-1">
                    Add your first flight in
                    <RouterLink to="/flights/log" class="text-indigo-500 hover:underline">Flight Log</RouterLink>.
                </p>
            </div>

            <template v-else>
                <!-- Map -->
                <FlightMap :flights="flights" :height="420" />

                <!-- Stats cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <StatCard label="Total Flights" :value="stats.total_flights" />
                    <StatCard label="Total Distance" :value="totalDistanceFormatted" />
                    <StatCard label="Unique Airports" :value="stats.unique_airports" />
                    <StatCard label="Unique Airlines" :value="stats.unique_airlines" />
                    <StatCard label="Countries" :value="uniqueCountries" />
                    <StatCard label="Most Visited" :value="stats.most_visited_airport || '—'" />
                    <StatCard label="Longest Flight" :value="longestFlight" />
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
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import api from '../api';
import { ensureLoaded, getDistance, getAirport, getUniqueCountries } from '../data/airportLookup';
import FlightMap from '../components/FlightMap.vue';

const loading = ref(true);
const flights = ref([]);
const stats = ref({
    total_flights: 0, unique_airports: 0, unique_airlines: 0,
    most_visited_airport: null, flights_by_year: {}, flights_by_class: {},
});

// Simple stat card component
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
    for (const f of flights.value) {
        total += getDistance(f.departure_airport, f.arrival_airport);
    }
    return total;
});

const totalDistanceFormatted = computed(() => {
    const km = Math.round(totalDistance.value);
    if (km >= 1000) return `${(km / 1000).toFixed(1)}k km`;
    return `${km} km`;
});

const uniqueCountries = computed(() => {
    const codes = new Set();
    for (const f of flights.value) {
        codes.add(f.departure_airport);
        codes.add(f.arrival_airport);
    }
    return getUniqueCountries(codes).size;
});

const longestFlight = computed(() => {
    let max = 0;
    let route = '—';
    for (const f of flights.value) {
        const d = getDistance(f.departure_airport, f.arrival_airport);
        if (d > max) {
            max = d;
            route = `${f.departure_airport}→${f.arrival_airport}`;
        }
    }
    return max > 0 ? `${route} (${Math.round(max)} km)` : '—';
});

onMounted(async () => {
    await ensureLoaded();
    try {
        const [flightsRes, statsRes] = await Promise.all([
            api.get('/flights'),
            api.get('/flights/stats'),
        ]);
        flights.value = flightsRes.data;
        stats.value = statsRes.data;
    } finally {
        loading.value = false;
    }
});
</script>
