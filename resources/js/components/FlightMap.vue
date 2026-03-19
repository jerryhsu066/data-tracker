<template>
    <div class="relative h-full w-full">
        <div ref="mapEl" class="h-full w-full"></div>
        <slot />
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
});

const { dark } = useTheme();
const mapEl = ref(null);

let map = null;
let tileLayer = null;
let routesGroup = null;   // visible routes (non-interactive)
let markersGroup = null;  // visible markers + labels (non-interactive)
let hitGroup = null;       // invisible hit targets (interactive)
let resizeObserver = null;

// Index: key → visible polyline[]
let routesByAirport = {};  // iata → polyline[]
let routesByKey = {};      // "AAA-BBB" → polyline[]
let airportsByKey = {};    // "AAA-BBB" → [iata, iata]
let allRoutes = [];
let markersByAirport = {}; // iata → circleMarker[] (visible markers)

// Labels for collision detection
let iataLabels = [];  // { labels: [{label, iata}], lat, lng, count }

// Interaction state
let hovered = null;  // "airport:TPE" or "route:HND-TPE"
let locked = null;   // same format, persists on click

const LIGHT_TILES = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
const DARK_TILES = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>';
const HIGHLIGHT_COLOR = '#f59e0b';       // amber for routes
const AIRPORT_HIGHLIGHT_COLOR = '#38bdf8'; // sky blue for airports

function getTileUrl() { return dark.value ? DARK_TILES : LIGHT_TILES; }

// ── Track smoothing ──────────────────────────────────────────────────────

function unwrapLongitudes(points) {
    if (points.length < 2) return points;
    const result = [[points[0][0], points[0][1]]];
    let offset = 0;
    for (let i = 1; i < points.length; i++) {
        const diff = points[i][1] + offset - (points[i - 1][1] + offset);
        if (diff > 180) offset -= 360;
        else if (diff < -180) offset += 360;
        result.push([points[i][0], points[i][1] + offset]);
    }
    return result;
}

function catmullRomSmooth(points, segs = 8) {
    if (points.length < 3) return points;
    function dist(a, b) { return Math.sqrt((a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2); }
    const result = [];
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[Math.max(i - 1, 0)];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[Math.min(i + 2, points.length - 1)];
        const d01 = Math.sqrt(dist(p0, p1)) || 1e-6;
        const d12 = Math.sqrt(dist(p1, p2)) || 1e-6;
        const d23 = Math.sqrt(dist(p2, p3)) || 1e-6;
        const t0 = 0, t1 = d01, t2 = t1 + d12, t3 = t2 + d23;
        for (let s = 0; s < segs; s++) {
            const t = t1 + (s / segs) * (t2 - t1);
            const A1 = [((t1-t)/(t1-t0))*p0[0]+((t-t0)/(t1-t0))*p1[0], ((t1-t)/(t1-t0))*p0[1]+((t-t0)/(t1-t0))*p1[1]];
            const A2 = [((t2-t)/(t2-t1))*p1[0]+((t-t1)/(t2-t1))*p2[0], ((t2-t)/(t2-t1))*p1[1]+((t-t1)/(t2-t1))*p2[1]];
            const A3 = [((t3-t)/(t3-t2))*p2[0]+((t-t2)/(t3-t2))*p3[0], ((t3-t)/(t3-t2))*p2[1]+((t-t2)/(t3-t2))*p3[1]];
            const B1 = [((t2-t)/(t2-t0))*A1[0]+((t-t0)/(t2-t0))*A2[0], ((t2-t)/(t2-t0))*A1[1]+((t-t0)/(t2-t0))*A2[1]];
            const B2 = [((t3-t)/(t3-t1))*A2[0]+((t-t1)/(t3-t1))*A3[0], ((t3-t)/(t3-t1))*A2[1]+((t-t1)/(t3-t1))*A3[1]];
            result.push([
                ((t2-t)/(t2-t1))*B1[0]+((t-t1)/(t2-t1))*B2[0],
                ((t2-t)/(t2-t1))*B1[1]+((t-t1)/(t2-t1))*B2[1],
            ]);
        }
    }
    result.push(points[points.length - 1]);
    return result;
}

// ── World-copy wrapping ──────────────────────────────────────────────────

const WORLD_OFFSETS = [0, -360, 360];

function offsetPts(pts, off) {
    if (off === 0) return pts;
    return pts.map(p => [p[0], p[1] + off]);
}

// ── Map init ─────────────────────────────────────────────────────────────

function updateMapBg() {
    if (!map) return;
    map.getContainer().style.background = dark.value ? '#1a1a2e' : '#f2efe9';
}

function initMap() {
    if (!mapEl.value || map) return;
    map = L.map(mapEl.value, {
        center: [25, 121], zoom: 3,
        zoomControl: true, attributionControl: true, worldCopyJump: true,
    });
    updateMapBg();
    tileLayer = L.tileLayer(getTileUrl(), { attribution: ATTRIBUTION }).addTo(map);

    routesGroup = L.layerGroup().addTo(map);
    markersGroup = L.layerGroup().addTo(map);
    hitGroup = L.layerGroup().addTo(map);

    resizeObserver = new ResizeObserver(() => { map?.invalidateSize(); });
    resizeObserver.observe(mapEl.value);

    map.on('zoomend moveend', resolveLabels);
    map.on('click', () => { locked = null; hovered = null; applyStyles(); });

    drawFlights();
}

// ── Draw ─────────────────────────────────────────────────────────────────

function drawFlights() {
    if (!map || !routesGroup || !markersGroup || !hitGroup) return;
    routesGroup.clearLayers();
    markersGroup.clearLayers();
    hitGroup.clearLayers();

    iataLabels = [];
    routesByAirport = {};
    routesByKey = {};
    airportsByKey = {};
    allRoutes = [];
    markersByAirport = {};
    hovered = null;
    locked = null;

    const airportVisits = {};
    for (const f of props.flights) {
        airportVisits[f.departure_airport] = (airportVisits[f.departure_airport] || 0) + 1;
        airportVisits[f.arrival_airport]   = (airportVisits[f.arrival_airport]   || 0) + 1;
    }

    const bounds = [];
    const isDark = dark.value;
    const markerColor = isDark ? '#818cf8' : '#4f46e5';
    const routeColor  = isDark ? '#818cf8' : '#6366f1';
    const isMobile = (mapEl.value?.offsetWidth ?? 768) < 768;
    const markerRadius = isMobile ? 3 : 5;

    // ── 1. Draw visible routes (non-interactive) ──
    const drawnRoutes = new Set();
    for (const f of props.flights) {
        const key = [f.departure_airport, f.arrival_airport].sort().join('-');
        const airports = [f.departure_airport, f.arrival_airport];
        if (!routesByKey[key]) routesByKey[key] = [];
        if (!airportsByKey[key]) airportsByKey[key] = airports;

        let coordSets = [];

        if (f.track_points && f.track_points.length >= 2) {
            const dep = getAirport(f.departure_airport);
            const arr = getAirport(f.arrival_airport);
            const pts = [...f.track_points];
            if (dep) pts.unshift([dep.lat, dep.lng]);
            if (arr) pts.push([arr.lat, arr.lng]);
            const smoothed = catmullRomSmooth(unwrapLongitudes(pts));
            for (const off of WORLD_OFFSETS) {
                const coords = offsetPts(smoothed, off);
                const line = L.polyline(coords, {
                    color: routeColor, weight: 2, opacity: 0.7, smoothFactor: 1,
                    interactive: false,
                }).addTo(routesGroup);
                line._origStyle = { color: routeColor, weight: 2, opacity: 0.7 };
                allRoutes.push(line);
                routesByKey[key].push(line);
                for (const iata of airports) {
                    if (!routesByAirport[iata]) routesByAirport[iata] = [];
                    routesByAirport[iata].push(line);
                }
                coordSets.push(coords);
            }
        } else {
            if (drawnRoutes.has(key)) continue;
            drawnRoutes.add(key);
            const points = getArcPoints(f.departure_airport, f.arrival_airport, 50);
            if (points.length < 2) continue;
            for (const off of WORLD_OFFSETS) {
                const coords = offsetPts(points, off);
                const line = L.polyline(coords, {
                    color: routeColor, weight: 1.5, opacity: 0.5, smoothFactor: 1,
                    interactive: false,
                }).addTo(routesGroup);
                line._origStyle = { color: routeColor, weight: 1.5, opacity: 0.5 };
                allRoutes.push(line);
                routesByKey[key].push(line);
                for (const iata of airports) {
                    if (!routesByAirport[iata]) routesByAirport[iata] = [];
                    routesByAirport[iata].push(line);
                }
                coordSets.push(coords);
            }
        }

        // Route hit targets (invisible, wide, interactive)
        for (const coords of coordSets) {
            const hit = L.polyline(coords, {
                weight: 16, opacity: 0, interactive: true,
            }).addTo(hitGroup);
            hit._hitType = 'route';
            hit._hitKey = key;
        }
    }

    // ── 2. Draw visible markers + labels (non-interactive) ──
    const sortedAirports = Object.entries(airportVisits).sort(([, a], [, b]) => b - a);

    for (const [iata, count] of sortedAirports) {
        const airport = getAirport(iata);
        if (!airport) continue;
        if (!routesByAirport[iata]) routesByAirport[iata] = [];
        if (!markersByAirport[iata]) markersByAirport[iata] = [];
        const labels = [];

        for (const off of WORLD_OFFSETS) {
            // Visible marker (non-interactive)
            const marker = L.circleMarker([airport.lat, airport.lng + off], {
                radius: markerRadius, fillColor: markerColor, fillOpacity: 0.85,
                color: isDark ? '#c7d2fe' : '#312e81', weight: 1.5,
                interactive: false,
            }).addTo(markersGroup);
            marker._origStyle = { fillColor: markerColor, color: isDark ? '#c7d2fe' : '#312e81', weight: 1.5 };
            markersByAirport[iata].push(marker);

            // IATA label (non-interactive via Leaflet, interactive via DOM events below)
            const label = L.tooltip({
                permanent: true,
                direction: 'right',
                offset: [8, 0],
                className: 'iata-label iata-label-interactive' + (isDark ? ' iata-label-dark' : ''),
            }).setContent(iata).setLatLng([airport.lat, airport.lng + off]);
            markersGroup.addLayer(label);
            labels.push({ label, iata });

            // Airport hit target (invisible circle, interactive)
            const hit = L.circleMarker([airport.lat, airport.lng + off], {
                radius: markerRadius + 10, fillOpacity: 0, opacity: 0, interactive: true,
            }).addTo(hitGroup);
            hit._hitType = 'airport';
            hit._hitKey = iata;
            hit.bindTooltip(`<b>${iata}</b> ${airport.city}<br>${count} visit${count > 1 ? 's' : ''}`, {
                className: isDark ? 'dark-tooltip' : '',
                offset: [-(markerRadius + 5), 0], // shift tooltip closer to the visible marker
            });
        }
        iataLabels.push({ labels, lat: airport.lat, lng: airport.lng, count });
        bounds.push([airport.lat, airport.lng]);
    }

    // ── 3. Attach events to hit group ──
    hitGroup.eachLayer(layer => {
        const type = layer._hitType;
        const key = layer._hitKey;
        if (!type || !key) return;
        const id = type + ':' + key;

        layer.on('mouseover', () => onHover(id));
        layer.on('mouseout',  () => onUnhover(id));
        layer.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            onToggle(id);
        });
    });

    resolveLabels();
    bindLabelEvents();

    if (bounds.length > 0) {
        nextTick(() => { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 6 }); });
    }
}

// ── Interaction state machine ────────────────────────────────────────────

function onHover(id) {
    hovered = id;
    if (locked && locked !== id) return; // something else is locked, don't change
    applyStyles();
}

function onUnhover(id) {
    if (hovered === id) hovered = null;
    if (locked) return; // locked, keep highlight
    applyStyles();
}

function onToggle(id) {
    if (locked === id) {
        locked = null; // deselect
    } else {
        locked = id;
    }
    applyStyles();
}

function applyStyles() {
    const active = locked || hovered; // locked takes priority

    // Restore all routes to original style
    for (const line of allRoutes) {
        line.setStyle(line._origStyle);
    }
    // Restore all markers to original style
    for (const iata in markersByAirport) {
        for (const m of markersByAirport[iata]) {
            m.setStyle(m._origStyle);
        }
    }

    if (!active) return;

    const [type, key] = active.split(':');

    // Highlight routes
    const lines = type === 'airport' ? routesByAirport[key] : routesByKey[key];
    if (lines) {
        for (const line of lines) {
            line.setStyle({ color: HIGHLIGHT_COLOR, weight: line._origStyle.weight + 1, opacity: 1 });
        }
    }

    // Highlight airport markers
    const airportsToHighlight = new Set();
    if (type === 'airport') {
        airportsToHighlight.add(key);
    } else {
        // Route selected — highlight both endpoints
        const pair = airportsByKey[key];
        if (pair) pair.forEach(a => airportsToHighlight.add(a));
    }
    for (const iata of airportsToHighlight) {
        const markers = markersByAirport[iata];
        if (!markers) continue;
        for (const m of markers) {
            m.setStyle({ fillColor: AIRPORT_HIGHLIGHT_COLOR, color: AIRPORT_HIGHLIGHT_COLOR, weight: 2.5 });
        }
    }
}

// ── Label collision detection ────────────────────────────────────────────

const LABEL_W = 32;
const LABEL_H = 14;

function resolveLabels() {
    if (!map || iataLabels.length === 0) return;
    const placed = [];
    for (const entry of iataLabels) {
        const pt = map.latLngToContainerPoint([entry.lat, entry.lng]);
        const overlaps = placed.some(p =>
            Math.abs(pt.x - p.x) < LABEL_W && Math.abs(pt.y - p.y) < LABEL_H
        );
        for (const { label } of entry.labels) {
            const el = label.getElement?.() ?? label._container;
            if (el) el.style.display = overlaps ? 'none' : '';
        }
        if (!overlaps) placed.push(pt);
    }
}

function bindLabelEvents() {
    for (const entry of iataLabels) {
        for (const { label, iata } of entry.labels) {
            const el = label.getElement?.() ?? label._container;
            if (!el) continue;
            el.style.cursor = 'pointer';
            const id = 'airport:' + iata;
            el.addEventListener('mouseenter', () => onHover(id));
            el.addEventListener('mouseleave', () => onUnhover(id));
            el.addEventListener('click', (e) => { e.stopPropagation(); onToggle(id); });
        }
    }
}

// ── Exposed API ──────────────────────────────────────────────────────────

function invalidate() {
    nextTick(() => { map?.invalidateSize(); });
}

defineExpose({ invalidate });

watch(dark, () => { if (tileLayer && map) { updateMapBg(); tileLayer.setUrl(getTileUrl()); drawFlights(); } });
watch(() => props.flights, drawFlights, { deep: true });

onMounted(() => { nextTick(initMap); });
onUnmounted(() => {
    resizeObserver?.disconnect();
    if (map) { map.remove(); map = null; }
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
.iata-label {
    background: none !important;
    border: none !important;
    box-shadow: none !important;
    font-size: 10px !important;
    font-weight: 600 !important;
    color: #334155 !important;
    text-shadow: 0 0 3px #fff, 0 0 3px #fff !important;
    padding: 0 !important;
}
.iata-label::before {
    display: none !important;
}
.iata-label-interactive {
    pointer-events: auto !important;
    cursor: pointer !important;
}
.iata-label-dark {
    color: #cbd5e1 !important;
    text-shadow: 0 0 3px #0f172a, 0 0 3px #0f172a !important;
}
.leaflet-interactive:focus {
    outline: none !important;
}
</style>
