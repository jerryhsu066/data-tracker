<template>
    <div class="min-h-screen flex items-center justify-center px-4 bg-white dark:bg-slate-900">
        <div class="w-full max-w-sm bg-white dark:bg-slate-800 rounded-2xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-1">Welcome back</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mb-6">Sign in to your stock tracker</p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password[0] }}</p>
                </div>

                <p v-if="generalError" class="text-sm text-red-500 bg-red-50 dark:bg-red-900/30 rounded-lg px-3 py-2">{{ generalError }}</p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg py-2 text-sm font-medium transition-colors"
                >
                    {{ loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>

            <!-- Passkey sign-in -->
            <div v-if="webauthnSupported" class="mt-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                    <span class="text-xs text-slate-400">or</span>
                    <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                </div>
                <button @click="signInWithPasskey" :disabled="passkeyLoading"
                    class="w-full border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-50 text-slate-700 dark:text-slate-300 rounded-lg py-2 text-sm font-medium transition-colors flex items-center justify-center gap-2">
                    <span>🔑</span>
                    {{ passkeyLoading ? 'Authenticating…' : 'Sign in with Passkey' }}
                </button>
                <p v-if="passkeyError" class="mt-2 text-sm text-red-500 text-center">{{ passkeyError }}</p>
            </div>

            <p class="mt-4 text-center text-sm text-slate-500 dark:text-slate-400">
                No account?
                <RouterLink to="/register" class="text-indigo-600 hover:underline font-medium">Register</RouterLink>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../stores/auth';
import { isWebauthnSupported, authenticateWithPasskey } from '../utils/webauthn';
import api from '../api';

const router = useRouter();
const auth = useAuth();

const form = ref({ email: '', password: '' });
const errors = ref({});
const generalError = ref('');
const loading = ref(false);

const webauthnSupported = isWebauthnSupported();
const passkeyLoading    = ref(false);
const passkeyError      = ref('');

async function submit() {
    errors.value = {};
    generalError.value = '';
    loading.value = true;
    try {
        await auth.login(form.value.email, form.value.password);
        router.push('/stocks/home');
    } catch (e) {
        if (e.response?.status === 422) errors.value = e.response.data.errors ?? {};
        else generalError.value = e.response?.data?.message ?? 'Login failed.';
    } finally {
        loading.value = false;
    }
}

async function signInWithPasskey() {
    passkeyError.value  = '';
    passkeyLoading.value = true;
    try {
        const { data: options }  = await api.post('/auth/webauthn/authenticate/options');
        const credentialJson     = await authenticateWithPasskey(options);
        const { data }           = await api.post('/auth/webauthn/authenticate', {
            credential: credentialJson,
            session_id: options.session_id,
        });
        auth.setSession(data.token, data.user);
        router.push('/stocks/home');
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            passkeyError.value = 'Cancelled or no passkey found for this site.';
        } else {
            passkeyError.value = e.response?.data?.message ?? 'Passkey sign-in failed.';
        }
    } finally {
        passkeyLoading.value = false;
    }
}
</script>
