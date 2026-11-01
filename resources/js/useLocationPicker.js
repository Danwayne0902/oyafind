import { onMounted, onUnmounted, ref } from 'vue';
import { getLocationChoices } from './api/locationCatalogue.js';

export function useLocationPicker(emit) {
    const isOpen = ref(false);
    const query = ref('');
    const choices = ref([]);
    const state = ref('idle');
    const error = ref('');
    const loading = ref(false);
    // The default view lists Nigerian states and the beyond-Nigeria regions.
    // A parent's children (LGAs for a state, countries for a region) are
    // requested only when it is expanded, so the picker never shows a flat
    // mixed list of every level at once.
    const childrenByState = ref(Object.create(null));
    const childrenLoading = ref('');
    const childrenError = ref('');
    const activeQuery = ref('');
    let requestNumber = 0;
    let childrenRequestNumber = 0;

    function displayName(location) {
        // Only LGAs need their state for context. Countries and regions are
        // already unambiguous on their own.
        if (!location.parent?.name || location.parent.location_type !== 'state') {
            return location.name;
        }
        return `${location.name} — ${location.parent.name}`;
    }

    async function loadDefaultChoices() {
        const [states, regions] = await Promise.all([
            getLocationChoices('', { type: 'state' }),
            getLocationChoices('', { type: 'region' }),
        ]);

        return {
            items: [...states.items, ...regions.items],
            state: states.state === 'stale' || regions.state === 'stale' ? 'stale' : states.state,
            catalogueVersion: states.catalogueVersion ?? regions.catalogueVersion,
        };
    }

    async function loadChoices(search = '') {
        const currentRequest = ++requestNumber;
        const normalizedSearch = typeof search === 'string' ? search.trim() : '';
        loading.value = true;
        error.value = '';

        try {
            // Searching spans the whole catalogue so someone can type an LGA
            // or country directly; the empty default lists states plus the
            // beyond-Nigeria regions.
            const result = normalizedSearch
                ? await getLocationChoices(normalizedSearch)
                : await loadDefaultChoices();
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
            // No type filter: a state returns its LGAs and a region returns
            // its countries, so the hierarchy stays intact.
            const result = await getLocationChoices('', { parent: stateSlug });
            if (currentRequest !== childrenRequestNumber) return;
            childrenByState.value = { ...childrenByState.value, [stateSlug]: result.items };
        } catch (requestError) {
            if (currentRequest !== childrenRequestNumber) return;
            childrenError.value = requestError.message || 'The smaller areas could not be loaded.';
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
