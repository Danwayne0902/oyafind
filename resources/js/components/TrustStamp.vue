<script setup>
import { computed } from 'vue';
import UiIcon from './UiIcon.vue';

const props = defineProps({
    verifiedAt: { type: String, default: null },
    size: { type: Number, default: 15 },
});

const checked = computed(() => {
    if (!props.verifiedAt) return null;
    const date = new Date(props.verifiedAt);
    return Number.isNaN(date.getTime()) ? null : date;
});

const shortDate = computed(() => {
    if (!checked.value) return '';
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(checked.value);
});

const longDate = computed(() => {
    if (!checked.value) return '';
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(checked.value);
});

const label = computed(() => (shortDate.value ? `Source checked ${shortDate.value}` : 'Check date not recorded'));

const description = computed(() => (longDate.value
    ? `OyaFind checked the source and details on ${longDate.value}. A check is not an endorsement or a guarantee of safety, selection or payment.`
    : 'OyaFind publishes this listing after a source and details check, but the check date is not recorded on this page.'));
</script>

<template>
    <span class="trust-stamp" :title="description">
        <UiIcon name="shield-check" :size="size" />
        <span>{{ label }}</span>
    </span>
</template>
