<script setup>
import { computed } from 'vue';

const props = defineProps({
    opportunity: { type: Object, required: true },
});

const deadline = computed(() => {
    if (!props.opportunity.deadline_at) return 'Deadline not listed';
    const date = new Date(props.opportunity.deadline_at);
    if (Number.isNaN(date.getTime())) return 'Deadline details unavailable';
    const formatted = new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date() ? `Deadline passed · ${formatted} UTC` : `Deadline ${formatted} UTC`;
});
</script>

<template>
    <article class="opportunity-card">
        <div class="card-meta">
            <span v-if="opportunity.category?.name" class="category-label">{{ opportunity.category.name }}</span>
            <span class="deadline-label">{{ deadline }}</span>
        </div>
        <h2><a :href="`/opportunities/${encodeURIComponent(opportunity.slug)}`" data-internal-link>{{ opportunity.title }}</a></h2>
        <p class="card-organisation">{{ opportunity.organisation_name || 'Organisation not listed' }}</p>
        <p class="card-summary">{{ opportunity.summary || opportunity.description }}</p>
        <div class="card-bottom">
            <span v-if="opportunity.location?.name">{{ opportunity.location.name }}</span>
            <span v-else>Location not specified</span>
            <a class="text-link" :href="`/opportunities/${encodeURIComponent(opportunity.slug)}`" data-internal-link>
                View details <span aria-hidden="true">→</span>
            </a>
        </div>
    </article>
</template>
