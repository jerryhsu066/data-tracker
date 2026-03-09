import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: () => import('./views/LoginView.vue'), meta: { guest: true } },
        { path: '/register', component: () => import('./views/RegisterView.vue'), meta: { guest: true } },
        { path: '/', redirect: '/dashboard' },
        { path: '/dashboard', component: () => import('./views/DashboardView.vue'), meta: { auth: true } },
        { path: '/stocks', component: () => import('./views/StocksView.vue'), meta: { auth: true } },
        { path: '/stocks/:symbol', component: () => import('./views/StockDetailView.vue'), meta: { auth: true } },
        { path: '/transactions', component: () => import('./views/TransactionsView.vue'), meta: { auth: true } },
    ],
});

router.beforeEach((to) => {
    const token = localStorage.getItem('token');
    if (to.meta.auth && !token) return '/login';
    if (to.meta.guest && token) return '/dashboard';
});

export default router;
