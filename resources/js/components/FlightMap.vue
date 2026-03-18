<template>
    <div ref="mapContainer" :style="{ height: height + 'px' }" class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700"></div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useTheme } from '../stores/theme';
import { getAirport, getArcPoints } from '../data/airportLookup';

const props = defineProps({
    flights: { type: Array, default: () => [] },
    height: { type: Number, default: 400 },
});

const { dark } = useTheme();
const mapContainer = ref(null);
let map = null;
let tileLayer = null;
let markersGroup = null;
let routesGroup = null;

const LIGHT_TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const DARK_TILES = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>';

function getTileUrl() {
    return dark.value ? DARK_TILES : LIGHT_TILES;
}

function initMap() {
    if (!mapContainer.value || map) return;

    map = L.map(mapContainer.value, {
        center: [25, 121],
        zoom: 3,
        zoomControl: true,
        attributionControl: true,
    });

    tileLayer = L.tileLayer(getTileUrl(), { attribution: ATTRIBUTION }).addTo(map);
    markersGroup = L.layerGroup().addTo(map);
    routesGroup = L.layerGroup().addTo(map);

    drawFlights();
}

function drawFlights() {
    if (!map || !markersGroup || !routesGroup) return;

    markersGroup.clearLayers();
    routesGroup.clearLayers();

    const airportVisits = {};
    const routeFreqs = {};

    for (const f of props.flights) {
        const dep = f.departure_airport;
        const arr = f.arrival_airport;
        airportVisits[dep] = (airportVisits[dep] || 0) + 1;
        airportVisits[arr] = (airportVisits[arr] || 0) + 1;

        const key = [dep, arr].sort().join('-');
        routeFreqs[key] = (routeFreqs[key] || 0) + 1;
    }

    const bounds = [];
    const isDark = dark.value;
    const markerColor = isDark ? '#818cf8' : '#4f46e5';
    const routeColor = isDark ? '#818cf8' : '#6366f1';

    // Draw airport markers
    for (const [iata, count] of Object.entries(airportVisits)) {
        const airport = getAirport(iata);
        if (!airport) continue;

        const radius = Math.min(4 + count * 1.5, 12);
        const marker = L.circleMarker([airport.lat, airport.lng], {
            radius,
            fillColor: markerColor,
            fillOpacity: 0.85,
            color: isDark ? '#c7d2fe' : '#312e81',
            weight: 1.5,
        });

        marker.bindTooltip(`<b>${iata}</b> ${airport.city}<br>${count} visit${count > 1 ? 's' : ''}`, {
            className: isDark ? 'dark-tooltip' : '',
        });

        markersGroup.addLayer(marker);
        bounds.push([airport.lat, airport.lng]);
    }

    // Draw route arcs
    const maxFreq = Math.max(1, ...Object.values(routeFreqs));
    const drawnRoutes = new Set();

    for (const f of props.flights) {
        const key = [f.departure_airport, f.arrival_airport].sort().join('-');
        if (drawnRoutes.has(key)) continue;
        drawnRoutes.add(key);

        const points = getArcPoints(f.departure_airport, f.arrival_airport, 50);
        if (points.length < 2) continue;

        const freq = routeFreqs[key];
        const weight = Math.max(1.5, Math.min(4, (freq / maxFreq) * 4));
        const opacity = Math.max(0.3, Math.min(0.8, 0.3 + (freq / maxFreq) * 0.5));

        L.polyline(points, {
            color: routeColor,
            weight,
            opacity,
            smoothFactor: 1,
        }).addTo(routesGroup);
    }

    // Fit bounds
    if (bounds.length > 0) {
        nextTick(() => {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 6 });
        });
    }
}

watch(dark, () => {
    if (tileLayer && map) {
        tileLayer.setUrl(getTileUrl());
        drawFlights();
    }
});

watch(() => props.flights, drawFlights, { deep: true });

onMounted(() => {
    nextTick(initMap);
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<style>
.dark-tooltip {
    background: #1e293b !important;
    color: #e2e8f0 !important;
    border-color: #475569 !important;
}
.dark-tooltip::before {
    border-top-color: #475569 !important;
}
</style>
