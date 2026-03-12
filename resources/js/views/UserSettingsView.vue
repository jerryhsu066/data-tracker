<template>
    <div class="max-w-lg mx-auto px-4 py-8 space-y-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Account Settings</h1>

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
import { ref } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';

const auth = useAuth();

const name         = ref(auth.state.user?.name ?? '');
const email        = ref(auth.state.user?.email ?? '');
const privacyLock  = ref(auth.state.user?.privacy_lock ?? false);
const savingPrivacyLock = ref(false);

const savingProfile = ref(false);
const profileSaved  = ref(false);
const profileError  = ref('');

const currentPassword = ref('');
const newPassword     = ref('');
const confirmPassword = ref('');

const savingPassword = ref(false);
const passwordSaved  = ref(false);
const passwordError  = ref('');

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
