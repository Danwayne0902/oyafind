<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useLocationPicker } from '../useLocationPicker.js';
import UiIcon from './UiIcon.vue';

const props = defineProps({
    selectedSlug: { type: String, default: '' },
});

const emit = defineEmits(['select']);
const root = ref(null);
const expandedState = ref('');
const {
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
    openPicker,
    search,
    loadChoices,
    loadChildren,
} = useLocationPicker(emit);

const searchMode = computed(() => activeQuery.value !== '');

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

/*
 * The default view is a list of states. A state's LGAs are shown as children
 * only when that state is expanded, never as peers of the states themselves.
 * A search spans the whole catalogue, so results are grouped back under the
 * state each local area belongs to.
 */
const groups = computed(() => {
    if (!searchMode.value) {
        return choices.value
            .filter((choice) => choice.location_type === 'state' || !choice.location_type)
            .map((choice) => ({
                state: choice,
                children: childrenByState.value[choice.slug] ?? [],
                standalone: false,
            }));
    }

    const grouped = new Map();

    for (const choice of choices.value) {
        if (choice.location_type === 'state') {
            if (!grouped.has(choice.slug)) {
                grouped.set(choice.slug, { state: choice, children: [], standalone: false });
            }
            continue;
        }

        if (!choice.parent) {
            grouped.set(`solo:${choice.slug}`, { state: choice, children: [], standalone: true });
            continue;
        }

        const key = choice.parent.slug;
        if (!grouped.has(key)) {
            grouped.set(key, {
                state: {
                    name: choice.parent.name,
                    slug: choice.parent.slug,
                    location_type: choice.parent.location_type ?? 'state',
                },
                children: [],
                standalone: false,
            });
        }
        grouped.get(key).children.push(choice);
    }

    return [...grouped.values()];
});

function isExpanded(group) {
    return !searchMode.value && expandedState.value === group.state.slug;
}

function isSelected(group) {
    return props.selectedSlug === group.state.slug;
}

function toggle(group) {
    if (isExpanded(group)) {
        expandedState.value = '';
        return;
    }

    expandedState.value = group.state.slug;
    void loadChildren(group.state.slug);
}

function choose(slug) {
    emit('select', slug);
    isOpen.value = false;
}

function chooseState(group) {
    if (group.standalone) {
        choose(group.state.slug);
        return;
    }

    emit('select', group.state.slug);

    // In search results a tap is a selection, so the panel closes. In the
    // state list it opens the state's LGAs underneath without hiding them.
    if (searchMode.value) {
        isOpen.value = false;
        return;
    }

    expandedState.value = group.state.slug;
    void loadChildren(group.state.slug);
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
                    placeholder="Search a state, LGA or Abuja…"
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

                <div v-for="group in groups" :key="group.state.slug" class="area-group">
                    <div class="area-row">
                        <button
                            class="area-option area-option-parent"
                            :class="{ 'is-selected': isSelected(group) }"
                            type="button"
                            role="option"
                            :aria-selected="isSelected(group)"
                            @click="chooseState(group)"
                        >
                            <span class="area-option-label">
                                <span>{{ group.state.name }}</span>
                                <small v-if="!searchMode && group.children.length">
                                    {{ group.children.length }} local area{{ group.children.length === 1 ? '' : 's' }}
                                </small>
                            </span>
                            <UiIcon v-if="isSelected(group)" name="check" :size="15" />
                        </button>
                        <button
                            v-if="!group.standalone"
                            class="area-toggle"
                            type="button"
                            :aria-expanded="isExpanded(group)"
                            :aria-label="`${isExpanded(group) ? 'Hide' : 'Show'} local areas in ${group.state.name}`"
                            @click.stop="toggle(group)"
                        >
                            <UiIcon name="chevron-down" :size="15" />
                        </button>
                    </div>

                    <div v-if="isExpanded(group)" class="area-children" role="group" :aria-label="`Local areas in ${group.state.name}`">
                        <p v-if="childrenLoading === group.state.slug" class="area-state">Loading local areas…</p>
                        <p v-else-if="childrenError && !group.children.length" class="area-state is-error" role="alert">
                            {{ childrenError }}
                        </p>
                        <template v-else>
                            <button
                                v-for="child in group.children"
                                :key="child.id"
                                class="area-option area-option-child"
                                :class="{ 'is-selected': child.slug === selectedSlug }"
                                type="button"
                                role="option"
                                :aria-selected="child.slug === selectedSlug"
                                @click="choose(child.slug)"
                            >
                                <span>{{ child.name }}</span>
                                <UiIcon v-if="child.slug === selectedSlug" name="check" :size="15" />
                            </button>
                            <p v-if="!group.children.length && childrenLoading !== group.state.slug" class="area-state">
                                No local areas have been reviewed for {{ group.state.name }} yet. Choosing the state covers every area inside it.
                            </p>
                        </template>
                    </div>
                </div>
            </div>

            <p v-if="!loading && !error && !groups.length" class="area-state">
                No reviewed areas match that search. You can still browse all areas.
            </p>
            <p class="area-note">
                OyaFind uses reviewed, coarse areas across Nigeria and beyond. No GPS or exact address is requested.
            </p>
        </div>
    </div>
</template>
