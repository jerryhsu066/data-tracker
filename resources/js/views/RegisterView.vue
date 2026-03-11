<template>
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Create account</h1>
            <p class="text-slate-500 text-sm mb-6">Start tracking your Taiwan stocks</p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg py-2 text-sm font-medium transition-colors"
                >
                    {{ loading ? 'Creating account…' : 'Create account' }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-500">
                Already have an account?
                <RouterLink to="/login" class="text-indigo-600 hover:underline font-medium">Sign in</RouterLink>
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

const form = ref({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const loading = ref(false);

async function submit() {
    errors.value = {};
    loading.value = true;
    try {
        await auth.register(form.value.name, form.value.email, form.value.password, form.value.password_confirmation);
        router.push('/stocks/home');
    } catch (e) {
        if (e.response?.status === 422) errors.value = e.response.data.errors ?? {};
    } finally {
        loading.value = false;
    }
}
</script>
