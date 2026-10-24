import './bootstrap';
import '../css/app.css';
import '@fontsource-variable/instrument-sans';
import { createApp } from 'vue';
import App from './App.vue';

createApp(App).mount('#app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {
            // The app remains usable when service workers are unavailable.
        });
    });
}
