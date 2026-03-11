<template>
    <div class="min-h-screen flex items-center justify-center px-4">
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

const router = useRouter();
const auth = useAuth();

const form = ref({ email: '', password: '' });
const errors = ref({});
const generalError = ref('');
const loading = ref(false);

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
</script>
