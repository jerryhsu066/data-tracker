<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Flight Log</h1>
            <div class="flex items-center gap-3">
                <select v-model="selectedYear" class="h-9 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Years</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <button @click="showForm = !showForm"
                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors">
                    {{ showForm ? 'Cancel' : 'Add Flight' }}
                </button>
            </div>
        </div>

        <!-- Add / Edit Form -->
        <div v-if="showForm" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ editing ? 'Edit Flight' : 'Add Flight' }}</h2>

            <!-- Lookup row -->
            <div class="flex items-end gap-3 flex-wrap">
                <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Flight Date *</label>
                    <input v-model="form.flight_date" type="date" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.flight_date }}</p>
                </div>
                <div class="flex-1 min-w-28">
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Flight Number *</label>
                    <input v-model="form.flight_number" type="text" placeholder="CI123" maxlength="20" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.flight_number }}</p>
                </div>
                <div class="pb-[1.1rem]">
                    <button @click="lookupFlight" :disabled="!canLookup || lookingUp"
                        class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                        <svg v-if="lookingUp" class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Lookup
                    </button>
                </div>
            </div>
            <p v-if="lookupMsg" class="text-xs" :class="lookupMsg.includes('found') ? 'text-red-500' : 'text-emerald-500'">{{ lookupMsg }}</p>

            <!-- Main fields -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Airline *</label>
                    <input v-model="form.airline" type="text" maxlength="100" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.airline }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">From *</label>
                    <AirportAutocomplete v-model="form.departure_airport" placeholder="TPE" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.departure_airport }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">To *</label>
                    <AirportAutocomplete v-model="form.arrival_airport" placeholder="NRT" />
                    <p class="h-[1.1rem] text-xs text-red-500">{{ errors.arrival_airport }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Aircraft</label>
                    <input v-model="form.aircraft_type" type="text" maxlength="50" placeholder="A330-300" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="h-[1.1rem]"></p>
                </div>
            </div>

            <!-- Optional fields -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Departure Time</label>
                    <input v-model="form.departure_time" type="datetime-local" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Arrival Time</label>
                    <input v-model="form.arrival_time" type="datetime-local" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Class</label>
                    <select v-model="form.seat_class" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">—</option>
                        <option value="economy">Economy</option>
                        <option value="economy+">Economy+</option>
                        <option value="business">Business</option>
                        <option value="first">First</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Seat</label>
                    <input v-model="form.seat_number" type="text" maxlength="10" placeholder="32A" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Booking Ref</label>
                    <input v-model="form.booking_reference" type="text" maxlength="20" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Price</label>
                    <input v-model="form.ticket_price" type="number" step="0.01" min="0" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tail Number</label>
                    <input v-model="form.tail_number" type="text" maxlength="20" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Notes</label>
                    <input v-model="form.notes" type="text" class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <button v-if="editing" @click="confirmDelete({ id: editing })"
                    class="h-9 px-4 text-sm font-medium rounded-md transition-colors mr-auto"
                    :class="deleteTarget?.id === editing ? 'bg-red-600 text-white animate-pulse' : 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20'">
                    {{ deleteTarget?.id === editing ? 'Confirm Delete?' : 'Delete Flight' }}
                </button>
                <button v-if="editing" @click="cancelEdit"
                    class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">
                    Cancel
                </button>
                <button @click="saveFlight" :disabled="saving"
                    class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50">
                    {{ saving ? 'Saving…' : (editing ? 'Update' : 'Save') }}
                </button>
            </div>
        </div>

        <!-- Flight table -->
        <div v-if="loading" class="text-center py-12 text-slate-400">Loading…</div>

        <div v-else-if="flights.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl shadow-sm">
            <p class="text-slate-400 text-lg">No flights recorded yet.</p>
            <p class="text-slate-400 text-sm mt-1">Click "Add Flight" to log your first flight.</p>
        </div>

        <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-x-auto">
            <table class="text-sm border-collapse w-full font-mono">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Date</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Flight</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">From</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">To</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Reg</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Aircraft</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Dep</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Arr</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Seat</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Class</th>
                        <th class="portrait:hidden px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Track</th>
                        <th class="portrait:hidden px-3 py-3 text-right font-medium text-slate-500 dark:text-slate-400"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="f in flights" :key="f.id"
                        class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                        <!-- Date -->
                        <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">{{ f.flight_date?.split('T')[0] }}</td>
                        <!-- Flight: airline logo + number -->
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <img :src="airlineLogo(f.flight_number)" :alt="f.airline"
                                    class="h-6 w-6 object-contain rounded shrink-0"
                                    @error="e => e.currentTarget.style.display = 'none'" />
                                <span class="text-slate-700 dark:text-slate-300">{{ f.flight_number }}</span>
                            </div>
                        </td>
                        <!-- From: flag + IATA + city (city hidden in portrait) -->
                        <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            <span class="mr-1">{{ countryFlag(f.departure_airport) }}</span>
                            <span>{{ f.departure_airport }}</span>
                            <span class="portrait:hidden text-xs text-slate-400 dark:text-slate-500 ml-1">{{ airportCity(f.departure_airport) }}</span>
                        </td>
                        <!-- To: flag + IATA + city (city hidden in portrait) -->
                        <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            <span class="mr-1">{{ countryFlag(f.arrival_airport) }}</span>
                            <span>{{ f.arrival_airport }}</span>
                            <span class="portrait:hidden text-xs text-slate-400 dark:text-slate-500 ml-1">{{ airportCity(f.arrival_airport) }}</span>
                        </td>
                        <!-- Reg (tail number) -->
                        <td class="portrait:hidden px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ f.tail_number || '—' }}</td>
                        <!-- Aircraft -->
                        <td class="portrait:hidden px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ formatAircraft(f.aircraft_type) }}</td>
                        <!-- Dep time: hh:mm (UTC+X) -->
                        <td class="portrait:hidden px-3 py-2.5 whitespace-nowrap">
                            <template v-if="formatTime(f.departure_time, f.departure_airport)">
                                <span class="text-slate-700 dark:text-slate-300">{{ formatTime(f.departure_time, f.departure_airport).time }}</span>
                                <span class="text-xs text-slate-400 dark:text-slate-500 ml-1">{{ formatTime(f.departure_time, f.departure_airport).offset }}</span>
                            </template>
                            <span v-else class="text-xs text-slate-400 dark:text-slate-500">—</span>
                        </td>
                        <!-- Arr time: hh:mm +N (UTC+X) -->
                        <td class="portrait:hidden px-3 py-2.5 whitespace-nowrap">
                            <template v-if="formatTime(f.arrival_time, f.arrival_airport)">
                                <span class="text-slate-700 dark:text-slate-300">
                                    {{ formatTime(f.arrival_time, f.arrival_airport).time }}<sup v-if="dayOffset(f) > 0" class="text-[0.6em] font-semibold ml-0.5">+{{ dayOffset(f) }}</sup>
                                </span>
                                <span class="text-xs text-slate-400 dark:text-slate-500 ml-1">{{ formatTime(f.arrival_time, f.arrival_airport).offset }}</span>
                            </template>
                            <span v-else class="text-xs text-slate-400 dark:text-slate-500">—</span>
                        </td>
                        <!-- Seat number -->
                        <td class="portrait:hidden px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ f.seat_number || '—' }}</td>
                        <!-- Class -->
                        <td class="portrait:hidden px-3 py-2.5 text-slate-700 dark:text-slate-300 capitalize">{{ f.seat_class || '—' }}</td>
                        <td class="portrait:hidden px-3 py-2.5 whitespace-nowrap">
                            <template v-if="f.track_points">
                                <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ f.track_points.length }} pts</span>
                                <button @click="removeTrack(f)" class="font-sans text-red-400 hover:text-red-600 text-xs ml-1">&times;</button>
                            </template>
                            <template v-else>
                                <label :for="'track-' + f.id" class="font-sans text-indigo-500 hover:text-indigo-700 text-xs font-medium cursor-pointer">Upload</label>
                                <input :id="'track-' + f.id" type="file" accept=".gpx,.kml" class="hidden" @change="e => uploadTrack(f, e)" />
                            </template>
                        </td>
                        <td class="portrait:hidden px-3 py-2.5 text-right">
                            <button @click="editFlight(f)" class="font-sans text-indigo-500 hover:text-indigo-700 text-xs font-medium">Edit</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api';
import { usePrivacy } from '../stores/privacy';
import { getAirport, ensureLoaded, reloadAirports } from '../data/airportLookup';
import AirportAutocomplete from '../components/AirportAutocomplete.vue';

const { hidden } = usePrivacy();

const flights = ref([]);
const loading = ref(true);
const showForm = ref(false);
const editing = ref(null);
const saving = ref(false);
const lookingUp = ref(false);
const lookupMsg = ref('');
const deleteTarget = ref(null);

const currentYear = new Date().getFullYear();
const years = computed(() => {
    const yrs = new Set(flights.value.map(f => new Date(f.flight_date).getFullYear()));
    for (let y = currentYear; y >= currentYear - 5; y--) yrs.add(y);
    return [...yrs].sort((a, b) => b - a);
});
const selectedYear = ref('');

const emptyForm = () => ({
    flight_date: '', airline: '', flight_number: '', departure_airport: '', arrival_airport: '',
    departure_time: '', arrival_time: '', aircraft_type: '', seat_class: '', seat_number: '',
    booking_reference: '', ticket_price: '', tail_number: '', notes: '',
});
const form = ref(emptyForm());
const errors = ref({});

const canLookup = computed(() => form.value.flight_date && form.value.flight_number);



async function fetchFlights() {
    loading.value = true;
    try {
        const params = {};
        if (selectedYear.value) params.year = selectedYear.value;
        const { data } = await api.get('/flights', { params });
        flights.value = data;
    } finally {
        loading.value = false;
    }
}

async function lookupFlight() {
    lookingUp.value = true;
    lookupMsg.value = '';
    try {
        const { data } = await api.post('/flights/lookup', {
            flight_number: form.value.flight_number,
            flight_date: form.value.flight_date,
        });
        if (data.source) {
            // Only fill empty fields for user-editable text fields
            if (!form.value.airline && data.airline) form.value.airline = data.airline;
            if (!form.value.departure_airport && data.departure_airport) form.value.departure_airport = data.departure_airport;
            if (!form.value.arrival_airport && data.arrival_airport) form.value.arrival_airport = data.arrival_airport;
            if (!form.value.aircraft_type && data.aircraft_type) form.value.aircraft_type = data.aircraft_type;
            // Always overwrite times and tail number — these come from the data source
            if (data.departure_time) form.value.departure_time = data.departure_time;
            if (data.arrival_time) form.value.arrival_time = data.arrival_time;
            if (data.tail_number) form.value.tail_number = data.tail_number;
            lookupMsg.value = `Auto-filled from ${data.source}`;
        } else {
            lookupMsg.value = 'No flight data found — please fill in manually';
        }
    } catch {
        lookupMsg.value = 'No flight data found — please fill in manually';
    } finally {
        lookingUp.value = false;
    }
}

async function saveFlight() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...form.value };
        // Clean empty strings to null
        for (const key of Object.keys(payload)) {
            if (payload[key] === '') payload[key] = null;
        }

        if (editing.value) {
            await api.patch(`/flights/${editing.value}`, payload);
            editing.value = null;
        } else {
            await api.post('/flights', payload);
        }
        // Reload airport cache then re-fetch list in correct server order
        await reloadAirports();
        await fetchFlights();
        form.value = emptyForm();
        showForm.value = false;
        lookupMsg.value = '';
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = {};
            for (const [k, v] of Object.entries(err.response.data.errors || {})) {
                errors.value[k] = v[0];
            }
        }
    } finally {
        saving.value = false;
    }
}

function editFlight(f) {
    editing.value = f.id;
    showForm.value = true;
    form.value = {
        flight_date: f.flight_date?.split('T')[0] || '',
        airline: f.airline || '',
        flight_number: f.flight_number || '',
        departure_airport: f.departure_airport || '',
        arrival_airport: f.arrival_airport || '',
        departure_time: f.departure_time ? f.departure_time.replace(' ', 'T').slice(0, 16) : '',
        arrival_time: f.arrival_time ? f.arrival_time.replace(' ', 'T').slice(0, 16) : '',
        aircraft_type: f.aircraft_type || '',
        seat_class: f.seat_class || '',
        seat_number: f.seat_number || '',
        booking_reference: f.booking_reference || '',
        ticket_price: f.ticket_price || '',
        tail_number: f.tail_number || '',
        notes: f.notes || '',
    };
    errors.value = {};
    lookupMsg.value = '';
}

function cancelEdit() {
    editing.value = null;
    form.value = emptyForm();
    errors.value = {};
    showForm.value = false;
}

let deleteTimeout = null;
function confirmDelete(f) {
    if (deleteTarget.value?.id === f.id) {
        clearTimeout(deleteTimeout);
        deleteFlight(f);
    } else {
        deleteTarget.value = f;
        deleteTimeout = setTimeout(() => { deleteTarget.value = null; }, 3000);
    }
}

async function deleteFlight(f) {
    await api.delete(`/flights/${f.id}`);
    flights.value = flights.value.filter(x => x.id !== f.id);
    deleteTarget.value = null;
    if (editing.value === f.id) {
        editing.value = null;
        form.value = emptyForm();
        showForm.value = false;
    }
}

async function uploadTrack(flight, event) {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('track', file);
    try {
        await api.post(`/flights/${flight.id}/track`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await fetchFlights();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to upload track');
    }
    event.target.value = '';
}

async function removeTrack(flight) {
    try {
        await api.delete(`/flights/${flight.id}/track`);
        await fetchFlights();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to remove track');
    }
}

function airportCity(iata) {
    return getAirport(iata)?.city || '';
}

// IATA aircraft type code → human-readable short label
// Codes not in this map are returned as-is (e.g. user already stored "A330-300")
const aircraftIataMap = {
    // Airbus narrow-body
    '318': 'A318', '319': 'A319', '320': 'A320', '321': 'A321',
    '32A': 'A320n', '32B': 'A321n', '32N': 'A320n', '32Q': 'A321n',
    // Airbus wide-body
    '332': 'A332', '333': 'A333',
    '342': 'A342', '343': 'A343', '345': 'A345', '346': 'A346',
    '350': 'A350', '351': 'A351', '359': 'A359', '35K': 'A35K',
    '388': 'A388',
    // Boeing narrow-body
    '732': 'B732', '733': 'B733', '734': 'B734', '735': 'B735',
    '736': 'B736', '737': 'B737', '738': 'B738', '739': 'B739',
    '73G': 'B73G', '73H': 'B73H', '73W': 'B73W',
    '7M7': 'B737M', '7M8': 'B737M', '7M9': 'B737M',
    // Boeing wide-body
    '741': 'B741', '742': 'B742', '743': 'B743', '744': 'B744', '748': 'B748',
    '752': 'B752', '753': 'B753',
    '762': 'B762', '763': 'B763', '764': 'B764', '76W': 'B763', '76X': 'B764',
    '772': 'B772', '773': 'B773', '77L': 'B77L', '77W': 'B77W',
    '788': 'B788', '789': 'B789', '78X': 'B78X',
    // Embraer
    'E70': 'E170', 'E75': 'E175', 'E90': 'E190', 'E95': 'E195', 'E7W': 'E175',
    // Bombardier CRJ
    'CR2': 'CRJ2', 'CR7': 'CRJ7', 'CR9': 'CRJ9',
    // ATR
    'AT4': 'ATR42', 'AT7': 'ATR72',
};

function formatAircraft(type) {
    if (!type) return '—';
    return aircraftIataMap[type.toUpperCase()] ?? type;
}

// Extract IATA airline code from flight number (e.g. "CI123" → "CI")
function airlineCode(flightNumber) {
    return flightNumber?.match(/^[A-Z]{2,3}/)?.[0] || '';
}

function airlineLogo(flightNumber) {
    const code = airlineCode(flightNumber);
    return code ? `https://www.gstatic.com/flights/airline_logos/70px/${code}.png` : '';
}

function countryFlag(iata) {
    const code = getAirport(iata)?.country_code;
    if (!code || code.length !== 2) return '';
    return String.fromCodePoint(...[...code].map(c => 0x1F1E6 + c.charCodeAt(0) - 65));
}

// Returns day difference between arr and dep based on the date portion of the stored local-time strings
function dayOffset(f) {
    if (!f.departure_time || !f.arrival_time) return 0;
    const depDate = f.departure_time.slice(0, 10);
    const arrDate = f.arrival_time.slice(0, 10);
    return Math.round((new Date(arrDate) - new Date(depDate)) / 86400000);
}

// Returns { time: "08:30", offset: "(UTC+8)" } or null
// dt is stored as a naive local-time string ("2026-03-15T08:30") — no TZ conversion needed.
function formatTime(dt, iata) {
    if (!dt) return null;
    const tz = getAirport(iata)?.tz;
    // Extract HH:mm directly — value is already in airport local time
    const time = dt.slice(11, 16);
    // Compute UTC offset label using the flight date as reference (append Z so Date parses as UTC,
    // giving a close-enough epoch for DST lookup on that date)
    const ref = new Date(dt.length === 16 ? dt + ':00Z' : dt);
    const offsetRaw = new Intl.DateTimeFormat('en', {
        timeZone: tz || Intl.DateTimeFormat().resolvedOptions().timeZone,
        timeZoneName: 'shortOffset',
    }).formatToParts(ref).find(p => p.type === 'timeZoneName')?.value || '';
    const offset = '(' + offsetRaw.replace('GMT', 'UTC') + ')';
    return { time, offset };
}

onMounted(async () => {
    await ensureLoaded();
    await fetchFlights();
});

// Re-fetch when year changes
import { watch } from 'vue';
watch(selectedYear, fetchFlights);
</script>
