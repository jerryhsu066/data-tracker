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
</template>

<script setup>
import { ref, computed, onMounted, watch, h } from 'vue';
import api from '../api';
import { ensureLoaded, getDistance, getUniqueCountries } from '../data/airportLookup';
import FlightMap from '../components/FlightMap.vue';

const loading = ref(true);
const flights = ref([]);
const allFlights = ref([]); // unfiltered, used for building year list

const currentYear = new Date().getFullYear();
const selectedYear = ref('');

const years = computed(() => {
    const yrs = new Set(allFlights.value.map(f => new Date(f.flight_date).getFullYear()));
    for (let y = currentYear; y >= currentYear - 5; y--) yrs.add(y);
    return [...yrs].sort((a, b) => b - a);
});

// All stats derived client-side from filtered flights so year selection is instantly reactive
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
    return `${km.toLocaleString()} km`;
});

const uniqueCountries = computed(() => {
    const codes = new Set();
    for (const f of flights.value) {
        codes.add(f.departure_airport);
        codes.add(f.arrival_airport);
    }
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
    // Load all flights once to populate year dropdown, then filter
    const { data } = await api.get('/flights');
    allFlights.value = data;
    flights.value = data;
    loading.value = false;
});

watch(selectedYear, fetchData);
</script>
