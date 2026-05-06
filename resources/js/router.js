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

// Resets the iOS CSS viewport after native overlays (Face ID, keyboard) can
// leave it reporting a wrong width, causing md:/sm: breakpoints to misfire.
//
// Why width=1: it forces iOS to fully discard the current viewport calculation
// and rebuild it from device dimensions when the original value is restored.
//
// Why opacity=0 first: iOS forces a paint immediately when the viewport meta
// changes, before requestAnimationFrame runs. Hiding the page ensures the
// zoomed-in intermediate frame (width=1 with default scale) is never visible.
// Opacity is restored in the same rAF that also restores the correct viewport,
// so the user sees one clean transition with no zoom flash.
export function resetCssViewport() {
    const vp = document.querySelector('meta[name="viewport"]');
    if (!vp) return;
    const original = vp.getAttribute('content');
    document.documentElement.style.opacity = '0';
    vp.setAttribute('content', 'width=1');
    requestAnimationFrame(() => {
        vp.setAttribute('content', original);
        document.documentElement.style.opacity = '';
    });
}

router.afterEach(async () => {
    await nextTick();
    resetCssViewport();
});

export default router;
