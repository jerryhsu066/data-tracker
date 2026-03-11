import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: () => import('./views/LoginView.vue'), meta: { guest: true } },
        { path: '/register', component: () => import('./views/RegisterView.vue'), meta: { guest: true } },
        { path: '/', redirect: '/stocks/home' },
        { path: '/stocks/home', component: () => import('./views/HomeView.vue'), meta: { auth: true } },
        { path: '/stocks/dashboard', component: () => import('./views/DashboardView.vue'), meta: { auth: true } },
        { path: '/stocks/list', component: () => import('./views/StocksView.vue'), meta: { auth: true } },
        { path: '/stocks/transactions', component: () => import('./views/TransactionsView.vue'), meta: { auth: true } },
        { path: '/stocks/exposure', component: () => import('./views/ExposureView.vue'), meta: { auth: true } },
        { path: '/stocks/settings', component: () => import('./views/SettingsView.vue'), meta: { auth: true } },
        { path: '/stocks/:symbol', component: () => import('./views/StockDetailView.vue'), meta: { auth: true } },
        { path: '/cashflow/home', component: () => import('./views/CashflowHomeView.vue'), meta: { auth: true } },
        { path: '/cashflow/overview', component: () => import('./views/CashflowGridView.vue'), meta: { auth: true } },
        { path: '/cashflow/settings', component: () => import('./views/CashflowSettingsView.vue'), meta: { auth: true } },
    ],
});

router.beforeEach((to) => {
    const token = localStorage.getItem('token');
    if (to.meta.auth && !token) return '/login';
    if (to.meta.guest && token) return '/stocks/home';
});

export default router;
