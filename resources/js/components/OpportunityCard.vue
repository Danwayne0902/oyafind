<script setup>
import { computed } from 'vue';
import SaveButton from './SaveButton.vue';
import TrustStamp from './TrustStamp.vue';
import UiIcon from './UiIcon.vue';

const props = defineProps({
    opportunity: { type: Object, required: true },
    saved: { type: Boolean, default: false },
    pending: { type: Boolean, default: false },
});

defineEmits(['save']);

const WORK_MODES = {
    onsite: 'On-site',
    remote: 'Remote',
    hybrid: 'Hybrid',
};

const workMode = computed(() => WORK_MODES[props.opportunity.work_mode] ?? null);

const deadline = computed(() => {
    if (!props.opportunity.deadline_at) return { label: 'No deadline listed', urgent: false, title: '' };
    const date = new Date(props.opportunity.deadline_at);
    if (Number.isNaN(date.getTime())) return { label: 'Deadline details unavailable', urgent: false, title: '' };

    const now = new Date();
    const days = Math.ceil((date.getTime() - now.getTime()) / 86_400_000);
    const formatted = new Intl.DateTimeFormat('en', {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(date);
    const full = new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date);

    if (date < now) return { label: 'Deadline passed', urgent: true, title: `${full} UTC` };
    if (days <= 7) return {
        label: days <= 1 ? 'Closes today' : `Closes in ${days} days`,
        urgent: true,
        title: `${full} UTC`,
    };

    return { label: `Closes ${formatted}`, urgent: false, title: `${full} UTC` };
});

const amount = computed(() => {
    const min = props.opportunity.amount_min;
    const max = props.opportunity.amount_max;
    const currency = props.opportunity.currency;
    if (!currency || (min == null && max == null)) return null;
    const format = (value) => new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(Number(value));
    if (min != null && max != null) return `${format(min)} – ${format(max)}`;
    return min != null ? `From ${format(min)}` : `Up to ${format(max)}`;
});
</script>

<template>
    <article class="op-card">
        <div class="op-card-top">
            <span v-if="opportunity.category?.name" class="op-cat">{{ opportunity.category.name }}</span>
            <span v-if="workMode" class="op-mode">{{ workMode }}</span>
            <span class="op-deadline" :class="{ 'is-urgent': deadline.urgent }" :title="deadline.title">
                {{ deadline.label }}
            </span>
        </div>

        <h3 class="op-title">
            <a :href="`/opportunities/${encodeURIComponent(opportunity.slug)}`" data-internal-link>
                {{ opportunity.title }}
            </a>
        </h3>

        <p class="op-org">
            <span>{{ opportunity.organisation_name || 'Organisation not listed' }}</span>
            <span class="op-org-sep" aria-hidden="true">·</span>
            <span class="op-org-location">
                <UiIcon name="pin" :size="13" />
                {{ opportunity.location?.name || 'Location not specified' }}
            </span>
            <span v-if="amount" class="op-org-sep" aria-hidden="true">·</span>
            <span v-if="amount" class="op-amount">{{ amount }}</span>
        </p>

        <p class="op-summary">{{ opportunity.summary || 'Open the listing for the full details.' }}</p>

        <div class="op-foot">
            <TrustStamp :verified-at="opportunity.verified_at" />
            <div class="op-actions">
                <SaveButton
                    :saved="saved"
                    :pending="pending"
                    compact
                    @toggle="$emit('save', opportunity)"
                />
                <a class="op-view" :href="`/opportunities/${encodeURIComponent(opportunity.slug)}`" data-internal-link>
                    View <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </article>
</template>
