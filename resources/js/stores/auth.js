import { reactive, readonly } from 'vue';
import api from '../api';

const state = reactive({
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    token: localStorage.getItem('token') || null,
});

export function useAuth() {
    async function login(email, password) {
        const { data } = await api.post('/auth/login', { email, password });
        _persist(data);
    }

    async function register(name, email, password, password_confirmation) {
        const { data } = await api.post('/auth/register', { name, email, password, password_confirmation });
        _persist(data);
    }

    async function logout() {
        try { await api.post('/auth/logout'); } catch {}
        _clear();
    }

    function _persist({ token, user }) {
        state.token = token;
        state.user = user;
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(user));
    }

    function _clear() {
        state.token = null;
        state.user = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
    }

    return { state: readonly(state), login, register, logout };
}
