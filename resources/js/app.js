import { createApp } from 'vue';
import App from './App.vue';
import router from './router';

createApp(App).use(router).mount('#app');

// Register service worker for PWA
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/build/sw.js', { scope: '/' });
}
