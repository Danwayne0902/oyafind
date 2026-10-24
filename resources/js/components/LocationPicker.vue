<script setup>
import { computed, watch } from 'vue';
import { useLocationPicker } from '../useLocationPicker.js';
import UiIcon from './UiIcon.vue';

const props = defineProps({
    selectedSlug: { type: String, default: '' },
});

const emit = defineEmits(['select']);
const {
    isOpen,
    query,
    choices,
    state,
    error,
    loading,
    displayName,
    openPicker,
    search,
    selectChoice,
    clearLocation,
    loadChoices,
} = useLocationPicker(emit);

// Resolve a human-readable label for the currently selected slug.
// Searches the already-loaded choices list; falls back to the slug
// itself only when choices have not been fetched yet.
const selectedLabel = computed(() => {
    if (!props.selectedSlug) return '';
    const match = choices.value.find((c) => c.slug === props.selectedSlug);
    return match ? displayName(match) : props.selectedSlug;
});

// Pre-load the catalogue silently when a selection is already active so
// selectedLabel can resolve to a friendly name without the user having
// to open the picker first.
watch(
    () => props.selectedSlug,
    (slug) => { if (slug && !choices.value.length) void loadChoices(); },
    { immediate: true },
);
</script>

<template>
    <section class="location-picker" aria-labelledby="location-picker-heading">
        <div class="location-picker-heading">
            <div>
                <p class="eyebrow">Optional filter</p>
                <h2 id="location-picker-heading">Browse by area</h2>
            </div>
            <button v-if="selectedSlug" class="text-link" type="button" @click="clearLocation">
                Browse all areas
            </button>
        </div>

        <p class="location-picker-copy">
            Location is optional. Browse all opportunities or choose a reviewed area.
            <span v-if="selectedSlug">Current area: <strong>{{ selectedLabel }}</strong>.</span>
        </p>

        <button
            v-if="!isOpen"
            class="button button-secondary"
            type="button"
            aria-controls="location-picker-controls"
            @click="openPicker"
        >{{ selectedSlug ? 'Change area' : 'Choose an area' }}</button>

        <div v-if="isOpen" id="location-picker-controls" class="location-picker-controls">
            <form class="location-search-form" role="search" @submit.prevent="search">
                <label for="location-search">Search reviewed areas</label>
                <div class="location-search-controls">
                    <input
                        id="location-search"
                        v-model="query"
                        type="search"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Search areas"
                    >
                    <button class="button button-primary" type="submit" :disabled="loading" :aria-busy="loading">
                        <UiIcon v-if="loading" class="loading-spinner" name="loader" :size="17" />
                        {{ loading ? 'Searching…' : 'Search areas' }}
                    </button>
                </div>
            </form>

            <p v-if="loading" class="muted-copy state-loading" role="status" aria-live="polite">
                <UiIcon class="loading-spinner" name="loader" :size="17" />
                Loading reviewed areas…
            </p>
            <p v-else-if="state === 'stale'" class="cache-state" role="status" aria-live="polite">
                Showing saved areas while offline. This list may be out of date.
            </p>
            <p v-else-if="state === 'fresh'" class="cache-state" role="status">
                Area list checked just now.
            </p>
            <p v-if="error" class="location-error" role="alert">
                {{ error }} All-location browsing remains available.
            </p>

            <template v-if="choices.length">
                <label class="location-results-label" for="location-results">Available reviewed areas</label>
                <select id="location-results" :value="selectedSlug" @change="selectChoice">
                    <option value="" disabled>Select an area</option>
                    <option v-for="choice in choices" :key="choice.id" :value="choice.slug">
                        {{ displayName(choice) }}
                    </option>
                </select>
            </template>
            <p v-else-if="!loading && !error" class="muted-copy">
                No reviewed areas are available in this list. You can browse all areas.
            </p>
            <button class="text-link location-close" type="button" @click="isOpen = false">Close area picker</button>
        </div>
    </section>
</template>
