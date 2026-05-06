<template>
    <div class="max-w-4xl space-y-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Account Settings</h1>

        <!-- Install App -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-3">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Install App</h2>

            <!-- Already installed -->
            <div v-if="pwa.isStandalone" class="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                </svg>
                App is already installed on this device.
            </div>

            <!-- iOS instructions -->
            <template v-else-if="pwa.isIOS">
                <p class="text-sm text-slate-600 dark:text-slate-300">To install on iOS:</p>
                <ol class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li class="flex items-start gap-2">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 text-xs flex items-center justify-center font-semibold">1</span>
                        <span>Tap the <svg class="inline w-4 h-4 mx-0.5 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> <strong>Share</strong> button in Safari's toolbar</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 text-xs flex items-center justify-center font-semibold">2</span>
                        <span>Scroll down and tap <strong>Add to Home Screen</strong></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 text-xs flex items-center justify-center font-semibold">3</span>
                        <span>Tap <strong>Add</strong> to confirm</span>
                    </li>
                </ol>
                <p class="text-xs text-slate-400 dark:text-slate-500">Make sure you're using Safari — Chrome on iOS does not support PWA install.</p>
            </template>

            <!-- Android / desktop with native prompt -->
            <template v-else-if="pwa.canInstall">
                <p class="text-sm text-slate-500 dark:text-slate-400">Install this app on your device for quick access without opening a browser.</p>
                <button @click="doInstall" :disabled="installing"
                    class="h-9 px-5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ installing ? 'Installing…' : 'Install App' }}
                </button>
            </template>

            <!-- No prompt available -->
            <template v-else>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Open this page in <strong>Chrome</strong> or <strong>Edge</strong> and look for the install icon
                    <svg class="inline w-4 h-4 mx-0.5 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><path d="M12 8v4l3 3"/></svg>
                    in the address bar, or use the browser menu to add to home screen.
                </p>
            </template>
        </div>

        <!-- Profile -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Profile</h2>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Name</label>
                    <input
                        v-model="name"
                        type="text"
                        class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button
                    @click="saveProfile"
                    :disabled="savingProfile"
                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
                >{{ savingProfile ? 'Saving…' : 'Save' }}</button>
                <span v-if="profileSaved" class="text-sm font-medium text-emerald-500 dark:text-emerald-400">Saved ✓</span>
                <p v-if="profileError" class="text-sm text-red-500">{{ profileError }}</p>
            </div>
        </div>

        <!-- Privacy -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Privacy</h2>
            <label class="flex items-center justify-between gap-4 cursor-pointer">
                <div>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Require password to reveal amounts</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">When enabled, you must enter your password to turn off the "hide amounts" mode.</p>
                </div>
                <button
                    type="button"
                    @click="togglePrivacyLock"
                    :disabled="savingPrivacyLock"
                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    :class="privacyLock ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform"
                        :class="privacyLock ? 'translate-x-5' : 'translate-x-0'"
                    ></span>
                </button>
            </label>
        </div>

        <!-- Passkeys -->
        <div v-if="webauthnSupported" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4 overflow-x-hidden">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Passkeys (Face ID / Fingerprint)</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Sign in with biometrics instead of your password. Passkeys are stored on your device and work on iOS, Android, and desktop.</p>

            <!-- Existing passkeys -->
            <div v-if="passkeys.length > 0" class="space-y-2">
                <div v-for="pk in passkeys" :key="pk.id"
                    class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50 dark:bg-slate-700/50">
                    <div>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ pk.name }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            {{ pk.last_used_at ? 'Last used ' + new Date(pk.last_used_at).toLocaleDateString() : 'Never used' }}
                        </p>
                    </div>
                    <button @click="deletePasskey(pk.id)" class="text-slate-300 dark:text-slate-600 hover:text-red-400 transition-colors text-sm">✕</button>
                </div>
            </div>
            <p v-else class="text-sm text-slate-400 dark:text-slate-500">No passkeys registered yet.</p>

            <!-- Add passkey -->
            <div class="space-y-2 pt-1">
                <div class="flex items-center gap-3">
                    <input v-model="newPasskeyName" type="text" placeholder="Device name (e.g. iPhone 15)"
                        class="h-9 border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-md px-3 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button @click="addPasskey" :disabled="addingPasskey"
                        class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-md transition-colors">
                        {{ addingPasskey ? 'Registering…' : '+ Add Passkey' }}
                    </button>
                </div>
                <p v-if="passkeyError" class="text-sm text-red-500">{{ passkeyError }}</p>
                <p v-if="passkeySuccess" class="text-sm text-emerald-500">Passkey added ✓</p>
            </div>
        </div>

        <!-- App settings (admin only) -->
        <div v-if="auth.state.user?.is_admin" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">App Settings</h2>
            <label class="flex items-center justify-between gap-4 cursor-pointer">
                <div>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Allow new registrations</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">When disabled, only existing accounts can log in.</p>
                </div>
                <button
                    type="button"
                    @click="toggleRegistration"
                    :disabled="savingRegistration"
                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    :class="registrationEnabled ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform"
                        :class="registrationEnabled ? 'translate-x-5' : 'translate-x-0'"
                    ></span>
                </button>
            </label>
        </div>

        <!-- Change password -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Change Password</h2>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Current password</label>
                    <input
                        v-model="currentPassword"
                        type="password"
                        class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">New password</label>
                    <input
                        v-model="newPassword"
                        type="password"
                        class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Confirm new password</label>
                    <input
                        v-model="confirmPassword"
                        type="password"
                        class="h-9 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button
                    @click="savePassword"
                    :disabled="savingPassword"
                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-medium rounded-md transition-colors"
                >{{ savingPassword ? 'Saving…' : 'Update Password' }}</button>
                <span v-if="passwordSaved" class="text-sm font-medium text-emerald-500 dark:text-emerald-400">Updated ✓</span>
                <p v-if="passwordError" class="text-sm text-red-500">{{ passwordError }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';
import { isWebauthnSupported, registerPasskey } from '../utils/webauthn';
import { usePwa } from '../stores/pwa';
import { resetCssViewport } from '../router';

const auth = useAuth();
const pwa  = usePwa();
const installing = ref(false);

async function doInstall() {
    installing.value = true;
    await pwa.install();
    installing.value = false;
}

const name         = ref(auth.state.user?.name ?? '');
const email        = ref(auth.state.user?.email ?? '');
const privacyLock  = ref(auth.state.user?.privacy_lock ?? false);
const savingPrivacyLock = ref(false);

const registrationEnabled = ref(true);
const savingRegistration  = ref(false);

const webauthnSupported = isWebauthnSupported();
const passkeys          = ref([]);
const newPasskeyName    = ref('');
const addingPasskey     = ref(false);
const passkeyError      = ref('');
const passkeySuccess    = ref(false);

onMounted(async () => {
    if (auth.state.user?.is_admin) {
        try {
            const { data } = await api.get('/admin/settings');
            registrationEnabled.value = data.registration_enabled;
        } catch {}
    }
    if (webauthnSupported) {
        try {
            const { data } = await api.get('/auth/webauthn/credentials');
            passkeys.value = data;
        } catch {}
    }
});

const savingProfile = ref(false);
const profileSaved  = ref(false);
const profileError  = ref('');

const currentPassword = ref('');
const newPassword     = ref('');
const confirmPassword = ref('');

const savingPassword = ref(false);
const passwordSaved  = ref(false);
const passwordError  = ref('');

async function addPasskey() {
    passkeyError.value   = '';
    passkeySuccess.value = false;
    addingPasskey.value  = true;
    try {
        const { data: options } = await api.post('/auth/webauthn/register/options');
        const credentialJson    = await registerPasskey(options);
        const { data }          = await api.post('/auth/webauthn/register', {
            credential: credentialJson,
            name: newPasskeyName.value || 'Passkey',
        });
        passkeys.value.push(data);
        newPasskeyName.value = '';
        passkeySuccess.value = true;
        setTimeout(() => { passkeySuccess.value = false; }, 3000);
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            passkeyError.value = 'Cancelled or not allowed.';
        } else {
            passkeyError.value = 'Registration failed. Please try again.';
        }
    } finally {
        addingPasskey.value = false;
        resetCssViewport();
    }
}

async function deletePasskey(id) {
    if (!confirm('Remove this passkey?')) return;
    await api.delete(`/auth/webauthn/credentials/${id}`);
    passkeys.value = passkeys.value.filter(p => p.id !== id);
}

async function toggleRegistration() {
    savingRegistration.value = true;
    try {
        const { data } = await api.patch('/admin/settings', { registration_enabled: !registrationEnabled.value });
        registrationEnabled.value = data.registration_enabled;
    } finally {
        savingRegistration.value = false;
    }
}

async function togglePrivacyLock() {
    savingPrivacyLock.value = true;
    try {
        const { data } = await api.patch('/auth/me', { privacy_lock: !privacyLock.value });
        auth.updateUser(data);
        privacyLock.value = data.privacy_lock;
    } finally {
        savingPrivacyLock.value = false;
    }
}

async function saveProfile() {
    savingProfile.value = true;
    profileError.value  = '';
    try {
        const { data } = await api.patch('/auth/me', { name: name.value, email: email.value });
        auth.updateUser(data);
        profileSaved.value = true;
        setTimeout(() => { profileSaved.value = false; }, 2000);
    } catch (e) {
        profileError.value = e.response?.data?.message ?? 'Failed to save.';
    } finally {
        savingProfile.value = false;
    }
}

async function savePassword() {
    passwordError.value = '';
    if (newPassword.value !== confirmPassword.value) {
        passwordError.value = 'Passwords do not match.';
        return;
    }
    savingPassword.value = true;
    try {
        await api.patch('/auth/me', {
            current_password:      currentPassword.value,
            password:              newPassword.value,
            password_confirmation: confirmPassword.value,
        });
        currentPassword.value = '';
        newPassword.value     = '';
        confirmPassword.value = '';
        passwordSaved.value   = true;
        setTimeout(() => { passwordSaved.value = false; }, 2000);
    } catch (e) {
        passwordError.value = e.response?.data?.errors?.current_password?.[0]
            ?? e.response?.data?.message
            ?? 'Failed to update password.';
    } finally {
        savingPassword.value = false;
    }
}
</script>
