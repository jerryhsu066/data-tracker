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

            <div class="flex gap-2">
                <button @click="saveFlight" :disabled="saving"
                    class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50">
                    {{ saving ? 'Saving…' : (editing ? 'Update' : 'Save') }}
                </button>
                <button v-if="editing" @click="cancelEdit"
                    class="h-9 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-md transition-colors">
                    Cancel
                </button>
                <button v-if="editing" @click="confirmDelete({ id: editing })"
                    class="h-9 px-4 text-sm font-medium rounded-md transition-colors ml-auto"
                    :class="deleteTarget?.id === editing ? 'bg-red-600 text-white animate-pulse' : 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20'">
                    {{ deleteTarget?.id === editing ? 'Confirm Delete?' : 'Delete Flight' }}
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
            <table class="text-sm border-collapse w-full">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Date</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Airline</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Flight</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">From</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">To</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Aircraft</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Class</th>
                        <th class="px-3 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Price</th>
                        <th class="px-3 py-3 text-right font-medium text-slate-500 dark:text-slate-400"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="f in flights" :key="f.id"
                        class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-4 py-2.5 text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ f.flight_date?.split('T')[0] }}</td>
                        <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ f.airline }}</td>
                        <td class="px-3 py-2.5 font-mono text-slate-700 dark:text-slate-300">{{ f.flight_number }}</td>
                        <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                            <span class="font-mono font-semibold">{{ f.departure_airport }}</span>
                            <span class="text-xs text-slate-400 ml-1">{{ airportCity(f.departure_airport) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                            <span class="font-mono font-semibold">{{ f.arrival_airport }}</span>
                            <span class="text-xs text-slate-400 ml-1">{{ airportCity(f.arrival_airport) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400">{{ f.aircraft_type || '—' }}</td>
                        <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400 capitalize">{{ f.seat_class || '—' }}</td>
                        <td class="px-3 py-2.5 text-right text-slate-700 dark:text-slate-300">
                            {{ f.ticket_price ? (hidden ? '••••' : '$' + Number(f.ticket_price).toLocaleString()) : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <button @click="editFlight(f)" class="text-indigo-500 hover:text-indigo-700 text-xs font-medium">Edit</button>
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
            const { data } = await api.patch(`/flights/${editing.value}`, payload);
            const idx = flights.value.findIndex(f => f.id === editing.value);
            if (idx >= 0) flights.value[idx] = data;
            editing.value = null;
        } else {
            const { data } = await api.post('/flights', payload);
            flights.value.unshift(data);
        }
        // Reload airport cache in case new airports were fetched on-demand
        await reloadAirports();
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

function airportCity(iata) {
    return getAirport(iata)?.city || '';
}

onMounted(async () => {
    await ensureLoaded();
    await fetchFlights();
});

// Re-fetch when year changes
import { watch } from 'vue';
watch(selectedYear, fetchFlights);
</script>
