import { createRouter, createWebHistory } from 'vue-router';
import { nextTick } from 'vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: () => import('./views/LoginView.vue'), meta: { guest: true } },
        { path: '/register', component: () => import('./views/RegisterView.vue'), meta: { guest: true } },
        { path: '/', redirect: '/stocks/home' },
        { path: '/user/settings', component: () => import('./views/UserSettingsView.vue'), meta: { auth: true } },

        // Stocks module
        {
            path: '/stocks',
            component: () => import('./layouts/ModuleLayout.vue'),
            meta: { auth: true, module: 'stocks' },
            children: [
                { path: 'home', component: () => import('./views/HomeView.vue') },
                { path: 'dashboard', component: () => import('./views/DashboardView.vue') },
                { path: 'list', component: () => import('./views/StocksView.vue') },
                { path: 'transactions', component: () => import('./views/StockTransactionsView.vue') },
                { path: 'exposure', component: () => import('./views/ExposureView.vue') },
                { path: 'settings', component: () => import('./views/SettingsView.vue') },
                { path: ':symbol', component: () => import('./views/StockDetailView.vue') },
            ],
        },

        // Cashflow module
        {
            path: '/cashflow',
            component: () => import('./layouts/ModuleLayout.vue'),
            meta: { auth: true, module: 'cashflow' },
            children: [
                { path: 'home', component: () => import('./views/CashflowHomeView.vue') },
                { path: 'log', component: () => import('./views/CashflowLogView.vue') },
                { path: 'settings', component: () => import('./views/CashflowSettingsView.vue') },
            ],
        },

        // Flights module
        {
            path: '/flights',
            component: () => import('./layouts/ModuleLayout.vue'),
            meta: { auth: true, module: 'flights' },
            children: [
                { path: 'home', component: () => import('./views/FlightsHomeView.vue') },
                { path: 'log', component: () => import('./views/FlightsLogView.vue') },
                { path: 'settings', component: () => import('./views/FlightsSettingsView.vue') },
            ],
        },
    ],
});

router.beforeEach((to) => {
    const token = localStorage.getItem('token');
    if (to.meta.auth && !token) return '/login';
    if (to.meta.guest && token) return '/stocks/home';
});

// After native overlays (Face ID, keyboard) iOS can report a wrong CSS
// viewport width, causing md:/sm: breakpoints to fire incorrectly.
// Manipulating the viewport meta tag is the only way to force iOS to
// recalculate the CSS viewport from the actual device dimensions.
// We do NOT use window.innerWidth because it may itself be returning
// the wrong value after the viewport drift.
function resetCssViewport() {
    const vp = document.querySelector('meta[name="viewport"]');
    if (!vp) return;
    const original = vp.getAttribute('content');
    vp.setAttribute('content', 'width=1');
    requestAnimationFrame(() => vp.setAttribute('content', original));
}

router.afterEach(async () => {
    await nextTick();
    resetCssViewport();
});

export { resetCssViewport };

export default router;
