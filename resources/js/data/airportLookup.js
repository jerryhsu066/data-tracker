import api from '../api';

let airportMap = null;
let airportsPromise = null;

function loadAirports() {
    if (!airportsPromise) {
        airportsPromise = api.get('/flights/airports').then(({ data }) => {
            airportMap = new Map(data.map(a => [a.iata, a]));
            return airportMap;
        }).catch(() => {
            // If API fails (e.g. not authenticated yet), reset so we can retry later
            airportsPromise = null;
            airportMap = new Map();
            return airportMap;
        });
    }
    return airportsPromise;
}

export async function ensureLoaded() {
    await loadAirports();
}

export async function ensureLoadedPublic() {
    if (!airportsPromise) {
        airportsPromise = api.get('/public/flights/airports').then(({ data }) => {
            airportMap = new Map(data.map(a => [a.iata, a]));
            return airportMap;
        }).catch(() => {
            airportsPromise = null;
            airportMap = new Map();
            return airportMap;
        });
    }
    return airportsPromise;
}

/**
 * Reload airports from the API (e.g. after a new airport was fetched on-demand).
 */
export async function reloadAirports() {
    airportsPromise = null;
    airportMap = null;
    await loadAirports();
}

export function getAirport(iata) {
    return airportMap?.get(iata) ?? null;
}

export function getAllAirports() {
    if (!airportMap) return [];
    return [...airportMap.values()];
}

export function searchAirports(query, limit = 10) {
    if (!airportMap || !query) return [];
    const q = query.toLowerCase();
    const results = [];
    for (const a of airportMap.values()) {
        if (
            a.iata.toLowerCase().includes(q) ||
            a.city.toLowerCase().includes(q) ||
            a.name.toLowerCase().includes(q)
        ) {
            results.push(a);
            if (results.length >= limit) break;
        }
    }
    // Prioritize exact IATA match
    results.sort((a, b) => {
        const aExact = a.iata.toLowerCase() === q ? -1 : 0;
        const bExact = b.iata.toLowerCase() === q ? -1 : 0;
        return aExact - bExact;
    });
    return results;
}

const R = 6371; // Earth radius in km

export function getDistance(fromIata, toIata) {
    const from = getAirport(fromIata);
    const to = getAirport(toIata);
    if (!from || !to) return 0;

    const lat1 = (from.lat * Math.PI) / 180;
    const lat2 = (to.lat * Math.PI) / 180;
    const dLat = lat2 - lat1;
    const dLng = ((to.lng - from.lng) * Math.PI) / 180;

    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function getArcPoints(fromIata, toIata, n = 50) {
    const from = getAirport(fromIata);
    const to = getAirport(toIata);
    if (!from || !to) return [];

    const lat1 = (from.lat * Math.PI) / 180;
    const lng1 = (from.lng * Math.PI) / 180;
    const lat2 = (to.lat * Math.PI) / 180;
    const lng2 = (to.lng * Math.PI) / 180;

    const d = 2 * Math.asin(
        Math.sqrt(
            Math.sin((lat1 - lat2) / 2) ** 2 +
            Math.cos(lat1) * Math.cos(lat2) * Math.sin((lng1 - lng2) / 2) ** 2
        )
    );

    if (d < 1e-10) return [[from.lat, from.lng]];

    const points = [];
    for (let i = 0; i <= n; i++) {
        const f = i / n;
        const A = Math.sin((1 - f) * d) / Math.sin(d);
        const B = Math.sin(f * d) / Math.sin(d);
        const x = A * Math.cos(lat1) * Math.cos(lng1) + B * Math.cos(lat2) * Math.cos(lng2);
        const y = A * Math.cos(lat1) * Math.sin(lng1) + B * Math.cos(lat2) * Math.sin(lng2);
        const z = A * Math.sin(lat1) + B * Math.sin(lat2);
        const lat = Math.atan2(z, Math.sqrt(x * x + y * y));
        const lng = Math.atan2(y, x);
        points.push([(lat * 180) / Math.PI, (lng * 180) / Math.PI]);
    }
    return points;
}

export function getUniqueCountries(codes) {
    const countries = new Set();
    for (const code of codes) {
        const a = getAirport(code);
        if (a) countries.add(a.country);
    }
    return countries;
}
