import './bootstrap';
import '../css/app.css';
import '../css/oyafind-v2.css';
import '../css/edition.css';
import '../css/studio.css';
import '@fontsource-variable/instrument-sans';
import { createApp } from 'vue';
import App from './App.vue';

/*
 * Mark the document before the first paint of the app shell. Motion styles key
 * off this class so a visitor without JavaScript never gets pre-hidden content.
 */
document.documentElement.classList.add('has-motion');

createApp(App).mount('#app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {
            // The app remains usable when service workers are unavailable.
        });
    });
}
