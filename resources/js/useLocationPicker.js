import { onMounted, onUnmounted, ref } from 'vue';
import { getLocationChoices } from './api/locationCatalogue.js';

export function useLocationPicker(emit) {
    const isOpen = ref(false);
    const query = ref('');
    const choices = ref([]);
    const state = ref('idle');
    const error = ref('');
    const loading = ref(false);
    let requestNumber = 0;

    function displayName(location) {
        return location.parent?.name ? `${location.name} — ${location.parent.name}` : location.name;
    }

    async function loadChoices(search = '') {
        const currentRequest = ++requestNumber;
        loading.value = true;
        error.value = '';

        try {
            const result = await getLocationChoices(search);
            if (currentRequest !== requestNumber) return;
            choices.value = result.items;
            state.value = result.state;
        } catch (requestError) {
            if (currentRequest !== requestNumber) return;
            state.value = 'unavailable';
            error.value = requestError.message || 'The area list is unavailable right now.';
        } finally {
            if (currentRequest === requestNumber) loading.value = false;
        }
    }

    function openPicker() {
        isOpen.value = true;
        void loadChoices();
    }

    function search() {
        void loadChoices(query.value);
    }

    function selectChoice(event) {
        emit('select', event.target.value);
    }

    function clearLocation() {
        emit('select', '');
    }

    function refreshWhenOnline() {
        if (isOpen.value) void loadChoices(query.value);
    }

    onMounted(() => {
        if (typeof window !== 'undefined') window.addEventListener('online', refreshWhenOnline);
    });

    onUnmounted(() => {
        requestNumber += 1;
        if (typeof window !== 'undefined') window.removeEventListener('online', refreshWhenOnline);
    });

    return {
        isOpen,
        query,
        choices,
        state,
        error,
        loading,
        displayName,
        loadChoices,
        openPicker,
        search,
        selectChoice,
        clearLocation,
    };
}
