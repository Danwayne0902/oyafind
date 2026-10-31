import { onMounted, onUnmounted, ref } from 'vue';
import { getLocationChoices } from './api/locationCatalogue.js';

export function useLocationPicker(emit) {
    const isOpen = ref(false);
    const query = ref('');
    const choices = ref([]);
    const state = ref('idle');
    const error = ref('');
    const loading = ref(false);
    // States are loaded first. A state's local government areas are requested
    // only when that state is expanded, so the picker never shows a flat list
    // of states with unrelated LGAs mixed in.
    const childrenByState = ref(Object.create(null));
    const childrenLoading = ref('');
    const childrenError = ref('');
    const activeQuery = ref('');
    let requestNumber = 0;
    let childrenRequestNumber = 0;

    function displayName(location) {
        if (!location.parent?.name || location.parent.location_type === 'country') {
            return location.name;
        }
        return `${location.name} — ${location.parent.name}`;
    }

    async function loadChoices(search = '') {
        const currentRequest = ++requestNumber;
        const normalizedSearch = typeof search === 'string' ? search.trim() : '';
        loading.value = true;
        error.value = '';

        try {
            // Searching spans the whole catalogue so someone can type an LGA
            // directly; the empty default lists states only.
            const result = await getLocationChoices(normalizedSearch, normalizedSearch ? {} : { type: 'state' });
            if (currentRequest !== requestNumber) return;
            choices.value = result.items;
            activeQuery.value = normalizedSearch;
            state.value = result.state;
        } catch (requestError) {
            if (currentRequest !== requestNumber) return;
            state.value = 'unavailable';
            error.value = requestError.message || 'The area list is unavailable right now.';
        } finally {
            if (currentRequest === requestNumber) loading.value = false;
        }
    }

    async function loadChildren(stateSlug) {
        if (!stateSlug || childrenByState.value[stateSlug]) return;
        const currentRequest = ++childrenRequestNumber;
        childrenLoading.value = stateSlug;
        childrenError.value = '';

        try {
            const result = await getLocationChoices('', { type: 'lga', parent: stateSlug });
            if (currentRequest !== childrenRequestNumber) return;
            childrenByState.value = { ...childrenByState.value, [stateSlug]: result.items };
        } catch (requestError) {
            if (currentRequest !== childrenRequestNumber) return;
            childrenError.value = requestError.message || 'The local areas could not be loaded.';
        } finally {
            if (currentRequest === childrenRequestNumber) childrenLoading.value = '';
        }
    }

    function openPicker() {
        isOpen.value = true;
        query.value = '';
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
        if (isOpen.value) void loadChoices(activeQuery.value);
    }

    onMounted(() => {
        if (typeof window !== 'undefined') window.addEventListener('online', refreshWhenOnline);
    });

    onUnmounted(() => {
        requestNumber += 1;
        childrenRequestNumber += 1;
        if (typeof window !== 'undefined') window.removeEventListener('online', refreshWhenOnline);
    });

    return {
        isOpen,
        query,
        choices,
        state,
        error,
        loading,
        activeQuery,
        childrenByState,
        childrenLoading,
        childrenError,
        displayName,
        loadChoices,
        loadChildren,
        openPicker,
        search,
        selectChoice,
        clearLocation,
    };
}
