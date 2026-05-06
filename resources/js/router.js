import { createRouter, createWebHistory } from 'vue-router';

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

// On every navigation, reset the scroll position and force iOS WebKit to
// recalculate the layout viewport width. This corrects the "stuck wide"
// state that can occur after native overlays (Face ID, keyboard) temporarily
// change the viewport and iOS PWA does not fully restore the scroll area.
router.afterEach(() => {
    window.scrollTo(0, 0);
    // Toggling overflow forces a style recalculation which flushes iOS
    // WebKit's cached scrollWidth back to the actual content width.
    document.documentElement.style.overflowX = 'hidden';
    requestAnimationFrame(() => {
        document.documentElement.style.overflowX = '';
    });
});

export default router;
