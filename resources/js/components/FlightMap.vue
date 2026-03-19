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
let infoTooltips = {};     // iata → { tips: L.tooltip[], latLng: [lat, lng] }
let routeInfoTooltips = {}; // routeKey → { els: HTMLElement[] } (one per world offset)
let routeTooltipContainer = null; // DOM container for route tooltip overlays
let flightsByKey = {};     // routeKey → [{flight_date, flight_number, ...}]

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

function countryCodeToFlag(cc) {
    if (!cc || cc.length !== 2) return '';
    return String.fromCodePoint(...[...cc.toUpperCase()].map(c => 0x1f1e6 + c.charCodeAt(0) - 65));
}

function airlineLogo(flightNumber) {
    if (!flightNumber) return '';
    const match = flightNumber.match(/^([A-Z]{2})/i);
    return match ? `https://www.gstatic.com/flights/airline_logos/70px/${match[1].toUpperCase()}.png` : '';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const yy = String(d.getFullYear()).slice(2);
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yy}/${mm}/${dd}`;
}

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

    // DOM overlay container for route tooltips (pixel-positioned in container space)
    routeTooltipContainer = document.createElement('div');
    routeTooltipContainer.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:650;overflow:hidden;';
    map.getContainer().appendChild(routeTooltipContainer);

    resizeObserver = new ResizeObserver(() => { map?.invalidateSize(); });
    resizeObserver.observe(mapEl.value);

    map.on('zoomend moveend', () => { resolveLabels(); resolveInfoTooltips(); });
    // Reposition route tooltips during pan and zoom animation
    map.on('move', () => { repositionRouteTooltips(); });
    map.on('zoomanim', (e) => {
        routeTooltipContainer.classList.add('route-tooltip-animating');
        repositionRouteTooltipsZoom(e);
    });
    map.on('zoomend', () => {
        routeTooltipContainer.classList.remove('route-tooltip-animating');
    });
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
    infoTooltips = {};
    // Clean up old route tooltip DOM elements
    for (const k in routeInfoTooltips) {
        for (const el of routeInfoTooltips[k].els) el.remove();
    }
    routeInfoTooltips = {};
    flightsByKey = {};
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
        const airports = [f.departure_airport, f.arrival_airport];

        let coordSets = [];
        let hitKey; // key used for hover/click highlighting

        if (f.track_points && f.track_points.length >= 2) {
            hitKey = `flight-${f.id}`;
            if (!routesByKey[hitKey]) routesByKey[hitKey] = [];
            if (!airportsByKey[hitKey]) airportsByKey[hitKey] = airports;

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
                routesByKey[hitKey].push(line);
                for (const iata of airports) {
                    if (!routesByAirport[iata]) routesByAirport[iata] = [];
                    routesByAirport[iata].push(line);
                }
                coordSets.push(coords);
            }
        } else {
            hitKey = [f.departure_airport, f.arrival_airport].sort().join('-');
            if (!routesByKey[hitKey]) routesByKey[hitKey] = [];
            if (!airportsByKey[hitKey]) airportsByKey[hitKey] = airports;
            if (!flightsByKey[hitKey]) flightsByKey[hitKey] = [];
            flightsByKey[hitKey].push(f);

            if (drawnRoutes.has(hitKey)) continue;
            drawnRoutes.add(hitKey);
            const points = unwrapLongitudes(getArcPoints(f.departure_airport, f.arrival_airport, 50));
            if (points.length < 2) continue;
            for (const off of WORLD_OFFSETS) {
                const coords = offsetPts(points, off);
                const line = L.polyline(coords, {
                    color: routeColor, weight: 1.5, opacity: 0.5, smoothFactor: 1,
                    interactive: false,
                }).addTo(routesGroup);
                line._origStyle = { color: routeColor, weight: 1.5, opacity: 0.5 };
                allRoutes.push(line);
                routesByKey[hitKey].push(line);
                for (const iata of airports) {
                    if (!routesByAirport[iata]) routesByAirport[iata] = [];
                    routesByAirport[iata].push(line);
                }
                coordSets.push(coords);
            }
        }

        // Store flight info for this key (tracked flights only — arc flights stored above)
        if (hitKey.startsWith('flight-')) {
            if (!flightsByKey[hitKey]) flightsByKey[hitKey] = [];
            flightsByKey[hitKey].push(f);
        }

        // Route hit targets (invisible, wide, interactive)
        for (const coords of coordSets) {
            const hit = L.polyline(coords, {
                weight: 16, opacity: 0, interactive: true,
            }).addTo(hitGroup);
            hit._hitType = 'route';
            hit._hitKey = hitKey;
        }
    }

    // ── Route info tooltips (pixel-positioned DOM overlays) ──
    for (const [key, flights] of Object.entries(flightsByKey)) {
        const lines = routesByKey[key];
        if (!lines || !lines.length) continue;
        const latlngs = lines[0].getLatLngs();
        if (latlngs.length < 2) continue;

        // Build tooltip content — list all flights for this route
        const rows = flights.map(f => {
            const logo = airlineLogo(f.flight_number);
            const logoImg = logo ? `<img src="${logo}" class="route-info-logo" onerror="this.style.display='none'">` : '';
            return `<div class="route-info-row"><div class="route-info-line1">${logoImg}<b>${f.flight_number || '—'}</b></div><div class="route-info-route"><span>${f.departure_airport}</span><span class="route-info-arrow">→</span><span>${f.arrival_airport}</span></div><div class="route-info-date">${formatDate(f.flight_date)}</div></div>`;
        }).join('');

        // Create one DOM element per world copy
        routeInfoTooltips[key] = { els: [] };
        for (let i = 0; i < 3; i++) {
            const el = document.createElement('div');
            el.className = 'route-info-tooltip' + (isDark ? ' route-info-tooltip-dark' : '');
            el.innerHTML = rows;
            el.style.cssText = 'position:absolute;display:none;pointer-events:none;';
            routeTooltipContainer.appendChild(el);
            routeInfoTooltips[key].els.push(el);
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

            // Info tooltip (shown on hover/click, managed by applyStyles)
            const flag = countryCodeToFlag(airport.country_code);
            const infoTip = L.tooltip({
                permanent: true,
                interactive: false,
                direction: 'top',
                offset: [0, 0],
                className: 'airport-info-tooltip' + (isDark ? ' airport-info-tooltip-dark' : ''),
            }).setContent(`<span class="airport-info-flag">${flag}</span> <b>${iata}</b><br><span class="airport-info-sub">${count} visit${count > 1 ? 's' : ''}</span>`)
              .setLatLng([airport.lat, airport.lng + off]);
            if (!infoTooltips[iata]) infoTooltips[iata] = { tips: [], latLng: [airport.lat, airport.lng] };
            infoTooltips[iata].tips.push(infoTip);
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
    // Hide all info tooltips
    for (const iata in infoTooltips) {
        for (const tip of infoTooltips[iata].tips) {
            if (map.hasLayer(tip)) map.removeLayer(tip);
        }
    }
    for (const k in routeInfoTooltips) {
        for (const el of routeInfoTooltips[k].els) el.style.display = 'none';
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

    // Show route info tooltips only when hovering/clicking a route
    if (type === 'route') {
        const entry = routeInfoTooltips[key];
        if (entry) entry.els.forEach(el => el.style.display = '');
    }

    // Highlight airport markers + show info tooltips
    const airportsToHighlight = new Set();
    if (type === 'airport') {
        airportsToHighlight.add(key);
    } else {
        const pair = airportsByKey[key];
        if (pair) pair.forEach(a => airportsToHighlight.add(a));
    }
    for (const iata of airportsToHighlight) {
        const markers = markersByAirport[iata];
        if (!markers) continue;
        for (const m of markers) {
            m.setStyle({ fillColor: AIRPORT_HIGHLIGHT_COLOR, color: AIRPORT_HIGHLIGHT_COLOR, weight: 2.5 });
        }
        const entry = infoTooltips[iata];
        if (entry) {
            for (const tip of entry.tips) map.addLayer(tip);
        }
    }

    // Resolve collisions — wait for Leaflet to position tooltip DOM elements
    nextTick(() => requestAnimationFrame(() => resolveInfoTooltips()));
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

function resolveInfoTooltips() {
    if (!map) return;
    const active = locked || hovered;
    if (!active) return;
    const [type, key] = active.split(':');

    const mapRect = map.getContainer().getBoundingClientRect();
    const ARROW = 10;
    const PAD = 6;
    const allArrowCls = ['arrow-up', 'arrow-left', 'arrow-right'];
    const allDirCls = ['leaflet-tooltip-top', 'leaflet-tooltip-bottom', 'leaflet-tooltip-left', 'leaflet-tooltip-right'];
    const allRouteDirCls = ['route-dir-top', 'route-dir-bottom', 'route-dir-left', 'route-dir-right'];

    function getBox(el) {
        const r = el.getBoundingClientRect();
        return { x: r.left - mapRect.left, y: r.top - mapRect.top, w: r.width, h: r.height };
    }

    function overlapArea(a, b) {
        const ox = Math.max(0, Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x));
        const oy = Math.max(0, Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y));
        return ox * oy;
    }

    // Position an element at (anchorX, anchorY) with given direction, returns box
    function placeEl(el, anchorX, anchorY, dir) {
        el.style.marginLeft = '0px';
        el.style.marginTop = '0px';
        const bw = el.offsetWidth;
        const bh = el.offsetHeight;
        let x, y;
        const gap = ARROW + PAD;
        switch (dir) {
            case 'top':    x = anchorX - bw / 2; y = anchorY - bh - gap; break;
            case 'bottom': x = anchorX - bw / 2; y = anchorY + gap; break;
            case 'left':   x = anchorX - bw - gap; y = anchorY - bh / 2; break;
            case 'right':  x = anchorX + gap; y = anchorY - bh / 2; break;
        }
        const cur = getBox(el);
        el.style.marginLeft = Math.round(x - cur.x) + 'px';
        el.style.marginTop = Math.round(y - cur.y) + 'px';
        return { x, y, w: bw, h: bh };
    }

    // Wrap longitude to be within ±180 of the map center (handles antimeridian routes)
    const centerLng = map.getCenter().lng;
    function visibleLng(lng) {
        let l = lng;
        while (l - centerLng > 180) l -= 360;
        while (l - centerLng < -180) l += 360;
        return l;
    }
    function visiblePx(lat, lng) {
        return map.latLngToContainerPoint([lat, visibleLng(lng)]);
    }

    const placed = [];

    if (type === 'route') {
        const pair = airportsByKey[key];
        if (!pair) return;
        const aptA = getAirport(pair[0]), aptB = getAirport(pair[1]);
        if (!aptA || !aptB) return;
        const pxA = visiblePx(aptA.lat, aptA.lng);
        const pxB = visiblePx(aptB.lat, aptB.lng);
        const dx = pxB.x - pxA.x;
        const dy = pxB.y - pxA.y;
        const isVertical = Math.abs(dy) > Math.abs(dx);

        const straightMidX = (pxA.x + pxB.x) / 2;
        const straightMidY = (pxA.y + pxB.y) / 2;

        // Get route half-weight for edge gap calculation
        const rLines = routesByKey[key];
        const routeHalfWeight = rLines && rLines.length
            ? ((rLines[0]._origStyle?.weight || 2) + 1) / 2 : 1.5;

        // Compute world pixel width for world copy offsets
        const worldPxWidth = map.latLngToContainerPoint([0, centerLng + 180]).x
                           - map.latLngToContainerPoint([0, centerLng - 180]).x;

        // Walk polyline for the visual midpoint — unwrap lngs sequentially
        let baseVisMidX = straightMidX, baseVisMidY = straightMidY;
        if (rLines && rLines.length) {
            const latlngs = rLines[0].getLatLngs();
            const unwrapped = [];
            for (let i = 0; i < latlngs.length; i++) {
                let lng = latlngs[i].lng;
                if (i > 0) {
                    while (lng - unwrapped[i - 1] > 180) lng -= 360;
                    while (lng - unwrapped[i - 1] < -180) lng += 360;
                }
                unwrapped.push(lng);
            }
            const pixels = latlngs.map((ll, i) => map.latLngToContainerPoint([ll.lat, unwrapped[i]]));
            let totalLen = 0;
            for (let i = 1; i < pixels.length; i++) {
                totalLen += Math.sqrt((pixels[i].x - pixels[i-1].x) ** 2 + (pixels[i].y - pixels[i-1].y) ** 2);
            }
            let half = totalLen / 2, walked = 0;
            for (let i = 1; i < pixels.length; i++) {
                const segLen = Math.sqrt((pixels[i].x - pixels[i-1].x) ** 2 + (pixels[i].y - pixels[i-1].y) ** 2);
                if (walked + segLen >= half) {
                    const t = segLen > 0 ? (half - walked) / segLen : 0;
                    baseVisMidX = pixels[i-1].x + t * (pixels[i].x - pixels[i-1].x);
                    baseVisMidY = pixels[i-1].y + t * (pixels[i].y - pixels[i-1].y);
                    break;
                }
                walked += segLen;
            }
        }

        // Route box direction: place on opposite side of arc curvature
        let routeDir;
        if (isVertical) {
            routeDir = baseVisMidX > straightMidX ? 'left' : 'right';
        } else {
            routeDir = baseVisMidY > straightMidY ? 'top' : 'bottom';
        }

        // Airport box directions based on line orientation
        let aptDirA, aptDirB;
        if (isVertical) {
            aptDirA = pxA.y <= pxB.y ? 'top' : 'bottom';
            aptDirB = pxA.y <= pxB.y ? 'bottom' : 'top';
        } else {
            aptDirA = pxA.x <= pxB.x ? 'left' : 'right';
            aptDirB = pxA.x <= pxB.x ? 'right' : 'left';
        }

        // 1. Place route tooltips (pixel-positioned DOM elements)
        const screenDist = Math.sqrt(dx * dx + dy * dy);
        const routeEntry = routeInfoTooltips[key];
        const routeEls = routeEntry ? routeEntry.els : [];

        for (let tipIdx = 0; tipIdx < routeEls.length; tipIdx++) {
            const el = routeEls[tipIdx];
            if (screenDist < 50) {
                el.style.display = 'none';
                continue;
            }
            el.style.display = '';

            // Shift from visible copy to this world copy
            const pxShift = (WORLD_OFFSETS[tipIdx] / 360) * worldPxWidth;
            const copyStraightMidX = straightMidX + pxShift;
            const copyVisMidX = baseVisMidX + pxShift;
            const copyVisMidY = baseVisMidY;

            // Set direction class for arrow (custom classes to avoid Leaflet CSS interference)
            allRouteDirCls.forEach(c => el.classList.remove(c));
            el.classList.add('route-dir-' + routeDir);

            const bw = el.offsetWidth;
            const bh = el.offsetHeight;
            // Arrow tip touches route line edge: offset = routeHalfWeight + arrowHeight
            // Arrow (6px) fills gap between box and route — no extra space
            const arrowH = 6;
            const offset = Math.ceil(routeHalfWeight) + arrowH;
            let x, y;
            switch (routeDir) {
                case 'top':    x = copyStraightMidX - bw / 2; y = copyVisMidY - bh - offset; break;
                case 'bottom': x = copyStraightMidX - bw / 2; y = copyVisMidY + offset; break;
                case 'left':   x = copyVisMidX - bw - offset; y = straightMidY - bh / 2; break;
                case 'right':  x = copyVisMidX + offset;      y = straightMidY - bh / 2; break;
            }
            el.style.left = Math.round(x) + 'px';
            el.style.top = Math.round(y) + 'px';
            // Cache lat/lng for smooth repositioning during zoom/pan animation
            el._cachedLatLng = map.containerPointToLatLng(L.point(x, y));
            placed.push({ x, y, w: bw, h: bh });
        }

        // 2. Place airport tooltips with collision sliding
        const aptPairs = [
            { iata: pair[0], dir: aptDirA },
            { iata: pair[1], dir: aptDirB },
        ];
        for (const { iata, dir } of aptPairs) {
            const entry = infoTooltips[iata];
            if (!entry) continue;
            for (const tip of entry.tips) {
                if (!map.hasLayer(tip)) continue;
                const el = tip.getElement?.() ?? tip._container;
                if (!el) continue;
                el.style.display = '';
                allArrowCls.forEach(c => el.classList.remove(c));

                // Arrow class: points back toward the anchor point
                const arrowMap = { top: '', bottom: 'arrow-up', left: 'arrow-right', right: 'arrow-left' };
                if (arrowMap[dir]) el.classList.add(arrowMap[dir]);

                // Use anchor from tooltip's own latLng (handles world copy offsets)
                const anchor = map.latLngToContainerPoint(tip.getLatLng());
                const box = placeEl(el, anchor.x, anchor.y, dir);

                // Check collision with placed boxes and slide if needed
                const hasCollision = placed.some(p => overlapArea(box, p) > 0);
                if (hasCollision) {
                    let slideX = box.x, slideY = box.y;
                    for (const p of placed) {
                        if (overlapArea({ x: slideX, y: slideY, w: box.w, h: box.h }, p) <= 0) continue;
                        // Slide away from anchor (same direction as box placement)
                        switch (dir) {
                            case 'top':    slideY = p.y - box.h - PAD; break;
                            case 'bottom': slideY = p.y + p.h + PAD; break;
                            case 'left':   slideX = p.x - box.w - PAD; break;
                            case 'right':  slideX = p.x + p.w + PAD; break;
                        }
                    }
                    const curML = parseInt(el.style.marginLeft) || 0;
                    const curMT = parseInt(el.style.marginTop) || 0;
                    el.style.marginLeft = (curML + Math.round(slideX - box.x)) + 'px';
                    el.style.marginTop = (curMT + Math.round(slideY - box.y)) + 'px';
                    placed.push({ x: slideX, y: slideY, w: box.w, h: box.h });
                } else {
                    placed.push(box);
                }
            }
        }
    } else {
        // Airport hover — place above marker (default position)
        const entry = infoTooltips[key];
        if (!entry) return;
        for (const tip of entry.tips) {
            if (!map.hasLayer(tip)) continue;
            const el = tip.getElement?.() ?? tip._container;
            if (!el) continue;
            allArrowCls.forEach(c => el.classList.remove(c));
            const anchor = map.latLngToContainerPoint(tip.getLatLng());
            placeEl(el, anchor.x, anchor.y, 'top');
        }
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

// ── Route tooltip live repositioning (pan & zoom animation) ─────────────

function repositionRouteTooltips() {
    if (!map) return;
    const active = locked || hovered;
    if (!active) return;
    const [type, key] = active.split(':');
    if (type !== 'route') return;
    const entry = routeInfoTooltips[key];
    if (!entry) return;
    for (const el of entry.els) {
        if (el.style.display === 'none' || !el._cachedLatLng) continue;
        const pt = map.latLngToContainerPoint(el._cachedLatLng);
        el.style.left = Math.round(pt.x) + 'px';
        el.style.top = Math.round(pt.y) + 'px';
    }
}

function repositionRouteTooltipsZoom(e) {
    if (!map) return;
    const active = locked || hovered;
    if (!active) return;
    const [type, key] = active.split(':');
    if (type !== 'route') return;
    const entry = routeInfoTooltips[key];
    if (!entry) return;
    // Project cached lat/lngs at the target zoom/center to container space
    const half = map.getSize().divideBy(2);
    for (const el of entry.els) {
        if (el.style.display === 'none' || !el._cachedLatLng) continue;
        // project() gives global pixel coords at target zoom; subtract target center pixel + add half container
        const px = map.project(el._cachedLatLng, e.zoom);
        const centerPx = map.project(e.center, e.zoom);
        const x = px.x - centerPx.x + half.x;
        const y = px.y - centerPx.y + half.y;
        el.style.left = Math.round(x) + 'px';
        el.style.top = Math.round(y) + 'px';
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
    routeTooltipContainer?.remove();
    routeTooltipContainer = null;
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
.airport-info-tooltip,
.route-info-tooltip,
.iata-label {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
}
.airport-info-tooltip {
    background: #fff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12) !important;
    padding: 8px 12px !important;
    font-size: 15px !important;
    line-height: 1.4 !important;
    color: #1e293b !important;
    white-space: nowrap !important;
    pointer-events: none !important;
}
.airport-info-tooltip::before {
    border-top-color: #cbd5e1 !important;
}
.airport-info-tooltip-dark {
    background: #475569 !important;
    border-color: #64748b !important;
    color: #f1f5f9 !important;
}
.airport-info-tooltip-dark::before {
    border-top-color: #64748b !important;
}
.airport-info-flag {
    font-size: 17px !important;
    vertical-align: middle !important;
}
.airport-info-sub {
    font-size: 12px !important;
    color: #94a3b8 !important;
}
/* Arrow repositioning when tooltip shifts to avoid collision */
.airport-info-tooltip.arrow-up::before {
    bottom: auto !important;
    top: 0 !important;
    margin-bottom: 0 !important;
    margin-top: -12px !important;
    border-top-color: transparent !important;
    border-bottom: 6px solid #cbd5e1 !important;
}
.airport-info-tooltip-dark.arrow-up::before {
    border-bottom-color: #64748b !important;
}
.airport-info-tooltip.arrow-left::before {
    bottom: auto !important;
    left: 0 !important;
    right: auto !important;
    top: 50% !important;
    margin: -6px 0 0 -12px !important;
    border-top-color: transparent !important;
    border-right: 6px solid #cbd5e1 !important;
    border-top: 6px solid transparent !important;
    border-bottom: 6px solid transparent !important;
    border-left: 6px solid transparent !important;
}
.airport-info-tooltip-dark.arrow-left::before {
    border-right-color: #64748b !important;
}
.airport-info-tooltip.arrow-right::before {
    bottom: auto !important;
    left: auto !important;
    right: 0 !important;
    top: 50% !important;
    margin: -6px -12px 0 0 !important;
    border-top-color: transparent !important;
    border-left: 6px solid #cbd5e1 !important;
    border-top: 6px solid transparent !important;
    border-bottom: 6px solid transparent !important;
    border-right: 6px solid transparent !important;
}
.airport-info-tooltip-dark.arrow-right::before {
    border-left-color: #64748b !important;
}
.route-tooltip-animating .route-info-tooltip {
    transition: left 0.25s linear, top 0.25s linear;
}
.route-info-tooltip {
    background: #fff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12) !important;
    padding: 6px 10px !important;
    font-size: 14px !important;
    line-height: 1.5 !important;
    color: #1e293b !important;
    white-space: nowrap !important;
    pointer-events: none !important;
}
.route-info-tooltip::before {
    content: '';
    position: absolute;
    border: 6px solid transparent;
}
/* Arrow: top direction (box above route, arrow points down) */
.route-dir-top.route-info-tooltip::before {
    left: 50%;
    margin-left: -6px;
    bottom: -12px;
    border-top-color: #cbd5e1;
}
/* Arrow: bottom direction (box below route, arrow points up) */
.route-dir-bottom.route-info-tooltip::before {
    left: 50%;
    margin-left: -6px;
    top: -12px;
    border-bottom-color: #cbd5e1;
}
/* Arrow: left direction (box left of route, arrow points right) */
.route-dir-left.route-info-tooltip::before {
    top: 50%;
    margin-top: -6px;
    right: -12px;
    border-left-color: #cbd5e1;
}
/* Arrow: right direction (box right of route, arrow points left) */
.route-dir-right.route-info-tooltip::before {
    top: 50%;
    margin-top: -6px;
    left: -12px;
    border-right-color: #cbd5e1;
}
.route-info-tooltip-dark {
    background: #475569 !important;
    border-color: #64748b !important;
    color: #f1f5f9 !important;
}
.route-dir-top.route-info-tooltip-dark::before {
    border-top-color: #64748b;
}
.route-dir-bottom.route-info-tooltip-dark::before {
    border-bottom-color: #64748b;
}
.route-dir-left.route-info-tooltip-dark::before {
    border-left-color: #64748b;
}
.route-dir-right.route-info-tooltip-dark::before {
    border-right-color: #64748b;
}
.route-info-row {
    display: flex !important;
    flex-direction: column !important;
}
.route-info-row + .route-info-row {
    margin-top: 4px !important;
    padding-top: 4px !important;
    border-top: 1px solid rgba(148,163,184,0.2) !important;
}
.route-info-line1 {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
}
.route-info-route {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    margin-top: 2px !important;
}
.route-info-arrow {
    margin: 0 4px !important;
}
.route-info-date {
    font-size: 11px !important;
    color: #94a3b8 !important;
    margin-top: 2px !important;
    text-align: center !important;
}
.route-info-logo {
    width: 20px !important;
    height: 20px !important;
    object-fit: contain !important;
    flex-shrink: 0 !important;
}
</style>
