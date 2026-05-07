import { createRouter, createWebHistory } from 'vue-router';
import { nextTick } from 'vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: () => import('./views/LoginView.vue'), meta: { guest: true } },
        { path: '/register', component: () => import('./views/RegisterView.vue'), meta: { guest: true } },
        { path: '/user/settings', component: () => import('./views/UserSettingsView.vue'), meta: { auth: true } },

        // Public subdomain routes — / and /log serve without auth; browser URL stays unchanged
        { path: '/', component: () => import('./views/PublicFlightsView.vue') },
        { path: '/log', component: () => import('./views/PublicFlightLogView.vue') },

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
    if (window.location.hostname === 'flight.jerry.tw') {
        if (to.path !== '/' && to.path !== '/log') return '/';
        return;
    }
    if (to.path === '/') return '/stocks/home';
    const token = localStorage.getItem('token');
    if (to.meta.auth && !token) return '/login';
    if (to.meta.guest && token) return '/stocks/home';
});

// Resets the iOS CSS viewport after native overlays (Face ID, keyboard) can
// leave it reporting a wrong width, causing md:/sm: breakpoints to misfire.
//
// Why screen.width instead of width=1:
//   width=1 forces a 440x zoom that iOS does not properly undo when restoring
//   initial-scale=1.0 in a drifted-viewport state — the page stays zoomed.
//   screen.width gives the device's actual CSS pixel width (e.g. 440 on
//   iPhone 16 Pro Max). Switching from the 'device-width' keyword to this
//   concrete number forces iOS to flush its cached viewport value without
//   changing the effective width or zoom at all. No opacity trick needed.
export function resetCssViewport() {
    const vp = document.querySelector('meta[name="viewport"]');
    if (!vp) return;
    const original = vp.getAttribute('content');
    vp.setAttribute('content', `width=${screen.width}, initial-scale=1`);
    requestAnimationFrame(() => vp.setAttribute('content', original));
}

router.afterEach(async () => {
    await nextTick();
    resetCssViewport();
});

export default router;
