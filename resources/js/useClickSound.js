import { onMounted, onUnmounted, ref } from 'vue';

const STORAGE_KEY = 'oyafind:sound';
const INTERACTIVE_SELECTOR = [
    'button',
    '.button',
    '.save-btn',
    '.category-chip',
    '.area-option',
    '.area-trigger',
    '.bottom-nav-link',
].join(',');

function readPreference() {
    try {
        return globalThis.localStorage?.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

function writePreference(enabled) {
    try {
        globalThis.localStorage?.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
    } catch {
        // The preference is cosmetic; the interface works without storage.
    }
}

/**
 * A tiny, synthesised click for interactive controls.
 *
 * The sound is created with the Web Audio API, so there is no extra network
 * request or audio asset to download. Browsers only allow audio after a user
 * gesture, which is exactly when this runs.
 */
export function useClickSound() {
    const soundEnabled = ref(readPreference());
    let context = null;
    let lastPlayedAt = 0;

    function ensureContext() {
        if (typeof window === 'undefined') return null;
        const AudioContext = window.AudioContext ?? window.webkitAudioContext;
        if (!AudioContext) return null;
        if (!context) context = new AudioContext();
        if (context.state === 'suspended') void context.resume().catch(() => {});
        return context;
    }

    function playTick(strength = 1) {
        if (!soundEnabled.value) return;

        const now = performance.now();
        if (now - lastPlayedAt < 45) return;
        lastPlayedAt = now;

        const audio = ensureContext();
        if (!audio) return;

        const start = audio.currentTime;
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();

        oscillator.type = 'triangle';
        oscillator.frequency.setValueAtTime(760 * strength, start);
        oscillator.frequency.exponentialRampToValueAtTime(420 * strength, start + 0.045);

        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.035 * strength, start + 0.005);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.07);

        oscillator.connect(gain);
        gain.connect(audio.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.08);
    }

    function handlePointerDown(event) {
        if (event.button !== undefined && event.button !== 0) return;
        if (!(event.target instanceof Element)) return;

        const control = event.target.closest(INTERACTIVE_SELECTOR);
        if (!control) return;
        if (control.matches(':disabled, [aria-disabled="true"]')) return;

        playTick();
    }

    function toggleSound() {
        soundEnabled.value = !soundEnabled.value;
        writePreference(soundEnabled.value);
        if (soundEnabled.value) playTick(1.15);
    }

    onMounted(() => {
        document.addEventListener('pointerdown', handlePointerDown, { capture: true, passive: true });
    });

    onUnmounted(() => {
        document.removeEventListener('pointerdown', handlePointerDown, { capture: true });
    });

    return { soundEnabled, toggleSound };
}
