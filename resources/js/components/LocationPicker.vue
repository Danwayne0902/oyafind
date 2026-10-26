<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useLocationPicker } from '../useLocationPicker.js';
import UiIcon from './UiIcon.vue';

const props = defineProps({
    selectedSlug: { type: String, default: '' },
});

const emit = defineEmits(['select']);
const root = ref(null);
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
    loadChoices,
} = useLocationPicker(emit);

const selectedLabel = computed(() => {
    if (!props.selectedSlug) return '';
    const match = choices.value.find((choice) => choice.slug === props.selectedSlug);
    if (match) return displayName(match);
    return props.selectedSlug
        .split('-')
        .filter(Boolean)
        .map((part) => part[0].toUpperCase() + part.slice(1))
        .join(' ');
});

function choose(slug) {
    emit('select', slug);
    isOpen.value = false;
}

function close() {
    isOpen.value = false;
}

function onDocumentPointerDown(event) {
    if (!isOpen.value || !root.value) return;
    if (!root.value.contains(event.target)) close();
}

function onKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) close();
}

watch(
    () => props.selectedSlug,
    (slug) => {
        if (slug && !choices.value.length) void loadChoices();
    },
    { immediate: true },
);

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="area-picker">
        <button
            class="area-trigger"
            type="button"
            :aria-expanded="isOpen"
            aria-controls="area-picker-panel"
            @click="isOpen ? close() : openPicker()"
        >
            <UiIcon name="pin" :size="16" />
            <span class="area-trigger-label">{{ selectedSlug ? selectedLabel : 'All areas' }}</span>
            <span v-if="!selectedSlug" class="area-trigger-hint">Choose</span>
            <UiIcon class="area-trigger-chevron" name="chevron-down" :size="15" />
        </button>

        <div v-if="isOpen" id="area-picker-panel" class="area-panel" role="dialog" aria-label="Choose a reviewed area">
            <div class="area-panel-head">
                <p class="area-panel-title">Filter by area</p>
                <button class="area-panel-close" type="button" aria-label="Close area picker" @click="close">
                    <UiIcon name="close" :size="16" />
                </button>
            </div>

            <form class="area-search" role="search" @submit.prevent="search">
                <label class="visually-hidden" for="area-search-input">Search reviewed areas</label>
                <input
                    id="area-search-input"
                    v-model="query"
                    type="search"
                    maxlength="100"
                    autocomplete="off"
                    placeholder="Search Lagos, Ibadan North, Abuja…"
                >
                <button class="button button-secondary button-sm" type="submit" :disabled="loading" :aria-busy="loading">
                    {{ loading ? 'Searching…' : 'Search' }}
                </button>
            </form>

            <p v-if="state === 'stale'" class="area-state" role="status">
                Showing saved areas while offline. This list may be out of date.
            </p>
            <p v-if="error" class="area-state is-error" role="alert">{{ error }}</p>

            <div class="area-list" role="listbox" aria-label="Reviewed areas">
                <button
                    class="area-option"
                    :class="{ 'is-selected': !selectedSlug }"
                    type="button"
                    role="option"
                    :aria-selected="!selectedSlug"
                    @click="choose('')"
                >
                    All areas
                </button>
                <button
                    v-for="choice in choices"
                    :key="choice.id"
                    class="area-option"
                    :class="{ 'is-selected': choice.slug === selectedSlug }"
                    type="button"
                    role="option"
                    :aria-selected="choice.slug === selectedSlug"
                    @click="choose(choice.slug)"
                >
                    <span>{{ displayName(choice) }}</span>
                    <UiIcon v-if="choice.slug === selectedSlug" name="check" :size="15" />
                </button>
            </div>

            <p v-if="!loading && !error && !choices.length" class="area-state">
                No reviewed areas match that search. You can still browse all areas.
            </p>
            <p class="area-note">
                OyaFind uses reviewed, coarse areas across Nigeria’s 36 states and the FCT. No GPS or exact address is requested.
            </p>
        </div>
    </div>
</template>
