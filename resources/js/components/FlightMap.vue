<template>
    <div ref="mapContainer"
         :style="{ height: height + 'px' }"
         class="isolate rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 portrait:aspect-[4/3] portrait:!h-auto">
    </div>
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
let resizeObserver = null;

const LIGHT_TILES = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
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

    resizeObserver = new ResizeObserver(() => { map?.invalidateSize(); drawFlights(); });
    resizeObserver.observe(mapContainer.value);

    drawFlights();
}

function drawFlights() {
    if (!map || !markersGroup || !routesGroup) return;

    markersGroup.clearLayers();
    routesGroup.clearLayers();

    const airportVisits = {};

    for (const f of props.flights) {
        airportVisits[f.departure_airport] = (airportVisits[f.departure_airport] || 0) + 1;
        airportVisits[f.arrival_airport]   = (airportVisits[f.arrival_airport]   || 0) + 1;
    }

    const bounds = [];
    const isDark = dark.value;
    const markerColor = isDark ? '#818cf8' : '#4f46e5';
    const routeColor = isDark ? '#818cf8' : '#6366f1';

    // Draw airport markers
    for (const [iata, count] of Object.entries(airportVisits)) {
        const airport = getAirport(iata);
        if (!airport) continue;

        const isMobile = (mapContainer.value?.offsetWidth ?? 768) < 768;
        const radius = isMobile ? 3 : 5;
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
    const drawnRoutes = new Set();

    for (const f of props.flights) {
        const key = [f.departure_airport, f.arrival_airport].sort().join('-');
        if (drawnRoutes.has(key)) continue;
        drawnRoutes.add(key);

        const points = getArcPoints(f.departure_airport, f.arrival_airport, 50);
        if (points.length < 2) continue;

        const opacity = 0.5;

        L.polyline(points, {
            color: routeColor,
            weight: 1.5,
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
    resizeObserver?.disconnect();
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
