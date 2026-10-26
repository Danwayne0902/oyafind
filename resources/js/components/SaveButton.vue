<script setup>
import UiIcon from './UiIcon.vue';

defineProps({
    saved: { type: Boolean, default: false },
    pending: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

defineEmits(['toggle']);
</script>

<template>
    <button
        class="save-btn"
        :class="{ 'is-saved': saved, 'is-compact': compact }"
        type="button"
        :aria-pressed="saved"
        :aria-label="saved ? 'Remove from saved opportunities' : 'Save this opportunity'"
        :disabled="pending"
        @click="$emit('toggle')"
    >
        <UiIcon :name="saved ? 'bookmark-check' : 'bookmark'" :size="16" />
        <span>{{ pending ? (saved ? 'Removing…' : 'Saving…') : (saved ? 'Saved' : 'Save') }}</span>
    </button>
</template>
