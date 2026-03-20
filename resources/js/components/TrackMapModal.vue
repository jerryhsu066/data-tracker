<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50" @mousedown.self="$emit('close')">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-[95vw] max-w-5xl h-[85vh] flex flex-col overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ mode === 'preview' ? 'Track Preview' : 'Flight Track' }}
                        </h3>
                        <span class="text-xs text-slate-400">{{ points.length }} data points after compress</span>
                    </div>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg leading-none">&times;</button>
                </div>

                <!-- Warning -->
                <div v-if="mode === 'preview' && warnings.length" class="px-5 py-2 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800">
                    <p v-for="w in warnings" :key="w" class="text-xs text-amber-700 dark:text-amber-300">{{ w }}</p>
                </div>

                <!-- Map -->
                <div ref="mapEl" class="flex-1 min-h-[400px]"></div>

                <!-- Footer -->
                <div v-if="mode === 'preview'" class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 dark:border-slate-700">
                    <button @click="$emit('close')"
                        class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">
                        Cancel
                    </button>
                    <button @click="$emit('confirm')" :disabled="confirming"
                        class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50">
                        {{ confirming ? 'Uploading...' : (warnings.length ? 'Upload Anyway' : 'Confirm Upload') }}
                    </button>
                </div>
                <div v-else-if="mode === 'view'" class="flex items-center justify-end px-5 py-3 border-t border-slate-200 dark:border-slate-700">
                    <button @click="onDelete"
                        class="h-9 px-4 text-sm font-medium rounded-md transition-colors"
                        :class="confirmingDelete ? 'bg-red-600 text-white animate-pulse' : 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20'">
                        {{ confirmingDelete ? 'Confirm Delete?' : 'Delete Track' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useTheme } from '../stores/theme';
import { getAirport } from '../data/airportLookup';

const props = defineProps({
    points: { type: Array, required: true },
    departureAirport: { type: String, default: '' },
    arrivalAirport: { type: String, default: '' },
    mode: { type: String, default: 'view' }, // 'view' or 'preview'
    confirming: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm', 'delete']);

const { dark } = useTheme();
const mapEl = ref(null);
let map = null;

const confirmingDelete = ref(false);
let deleteTimeout = null;

function onDelete() {
    if (confirmingDelete.value) {
        clearTimeout(deleteTimeout);
        emit('delete');
    } else {
        confirmingDelete.value = true;
        deleteTimeout = setTimeout(() => { confirmingDelete.value = false; }, 3000);
    }
}

function unwrapLongitudes(points) {
    if (points.length < 2) return points;
    const result = [[points[0][0], points[0][1]]];
    let offset = 0;
    for (let i = 1; i < points.length; i++) {
        const diff = points[i][1] + offset - result[i - 1][1];
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

const LIGHT_TILES = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
const DARK_TILES = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>';
const WARNING_DISTANCE_KM = 50;

function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

const warnings = computed(() => {
    if (props.mode !== 'preview' || props.points.length < 2) return [];
    const msgs = [];
    const start = props.points[0];
    const end = props.points[props.points.length - 1];

    const dep = getAirport(props.departureAirport);
    if (dep) {
        const d = haversine(start[0], start[1], dep.lat, dep.lng);
        if (d > WARNING_DISTANCE_KM) {
            msgs.push(`Track start is ${Math.round(d)} km from ${props.departureAirport} (${dep.city})`);
        }
    }

    const arr = getAirport(props.arrivalAirport);
    if (arr) {
        const d = haversine(end[0], end[1], arr.lat, arr.lng);
        if (d > WARNING_DISTANCE_KM) {
            msgs.push(`Track end is ${Math.round(d)} km from ${props.arrivalAirport} (${arr.city})`);
        }
    }

    return msgs;
});

onMounted(() => {
    if (!mapEl.value) return;

    const tileUrl = dark.value ? DARK_TILES : LIGHT_TILES;
    const bg = dark.value ? '#1a1a2e' : '#f2efe9';

    map = L.map(mapEl.value, {
        zoomControl: true,
        attributionControl: true,
    });
    map.getContainer().style.background = bg;
    L.tileLayer(tileUrl, { attribution: ATTRIBUTION }).addTo(map);

    // Prepend departure + append arrival airports, then unwrap + smooth (same as FlightMap)
    const dep = getAirport(props.departureAirport);
    const arr = getAirport(props.arrivalAirport);
    const pts = [...props.points];
    if (dep) pts.unshift([dep.lat, dep.lng]);
    if (arr) pts.push([arr.lat, arr.lng]);
    const unwrapped = unwrapLongitudes(pts);
    const smoothed = catmullRomSmooth(unwrapped);

    // Draw track polyline with animated dashes to show direction
    const trackColor = '#6366f1'; // indigo
    const polyline = L.polyline(smoothed, {
        color: trackColor, weight: 2.5, opacity: 0.85,
        dashArray: '8 6',
    }).addTo(map);
    // Apply dash flow animation after polyline is rendered
    requestAnimationFrame(() => {
        const pathEl = polyline.getElement?.();
        if (pathEl) pathEl.classList.add('track-dash-anim');
    });

    // Snap an airport longitude to be nearest to a reference longitude (handles antimeridian)
    const trackStart = unwrapped[0];
    const trackEnd = unwrapped[unwrapped.length - 1];

    function snapLng(lng, refLng) {
        while (lng - refLng > 180) lng -= 360;
        while (refLng - lng > 180) lng += 360;
        return lng;
    }

    // Airport markers — place at longitudes consistent with the unwrapped track
    if (dep) {
        const lng = snapLng(dep.lng, trackStart[1]);
        L.circleMarker([dep.lat, lng], {
            radius: 6, fillColor: '#22c55e', fillOpacity: 1, color: '#fff', weight: 2,
        }).addTo(map).bindTooltip(props.departureAirport, { permanent: true, direction: 'top', offset: [0, -8], className: 'track-label' });
    }

    if (arr) {
        const lng = snapLng(arr.lng, trackEnd[1]);
        L.circleMarker([arr.lat, lng], {
            radius: 6, fillColor: '#ef4444', fillOpacity: 1, color: '#fff', weight: 2,
        }).addTo(map).bindTooltip(props.arrivalAirport, { permanent: true, direction: 'top', offset: [0, -8], className: 'track-label' });
    }

    // Track start/end markers (use unwrapped coordinates)
    if (unwrapped.length >= 2) {
        L.circleMarker(trackStart, {
            radius: 4, fillColor: '#22c55e', fillOpacity: 0.6, color: '#22c55e', weight: 1,
        }).addTo(map);
        L.circleMarker(trackEnd, {
            radius: 4, fillColor: '#ef4444', fillOpacity: 0.6, color: '#ef4444', weight: 1,
        }).addTo(map);
    }

    // Fit bounds — use polyline bounds (already unwrapped), extend with snapped airports
    const bounds = polyline.getBounds();
    if (dep) bounds.extend([dep.lat, snapLng(dep.lng, trackStart[1])]);
    if (arr) bounds.extend([arr.lat, snapLng(arr.lng, trackEnd[1])]);
    map.fitBounds(bounds, { padding: [30, 30] });
});

onUnmounted(() => {
    clearTimeout(deleteTimeout);
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<style>
.track-label {
    background: rgba(0, 0, 0, 0.7) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 2px 6px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    font-family: ui-monospace, monospace !important;
    box-shadow: none !important;
}
.track-label::before {
    border-top-color: rgba(0, 0, 0, 0.7) !important;
}
.track-dash-anim {
    animation: track-dash-flow 0.6s linear infinite;
}
@keyframes track-dash-flow {
    to { stroke-dashoffset: -14; }
}
</style>
