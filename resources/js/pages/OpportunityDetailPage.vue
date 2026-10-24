<script setup>
import { computed } from 'vue';
import UiIcon from '../components/UiIcon.vue';

const props = defineProps({
    opportunity: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    shareMessage: { type: String, default: '' },
    copyFallback: { type: Boolean, default: false },
    copyUrl: { type: String, default: '' },
    safeExternalUrl: { type: Function, required: true },
});

const emit = defineEmits(['back', 'share', 'copy']);

const applicationUrl = computed(() => props.safeExternalUrl(props.opportunity?.application_url));
const sourceUrl = computed(() => props.safeExternalUrl(props.opportunity?.source_url));
const deadline = computed(() => {
    if (!props.opportunity?.deadline_at) return 'No deadline listed';
    const date = new Date(props.opportunity.deadline_at);
    if (Number.isNaN(date.getTime())) return 'Deadline details unavailable';
    const formatted = new Intl.DateTimeFormat('en', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date()
        ? `Deadline passed (${formatted} UTC)`
        : `${formatted} UTC`;
});

function displayText(value, fallback = 'Not provided') {
    return typeof value === 'string' && value.trim() ? value : fallback;
}
</script>

<template>
    <section class="detail-page">
        <a class="back-link" href="/search" data-internal-link @click.prevent="emit('back')">← Back to opportunities</a>
        <p v-if="loading" class="state-panel state-loading" role="status" aria-live="polite">
            <UiIcon class="loading-spinner" name="loader" :size="19" />
            Loading opportunity details…
        </p>
        <div v-else-if="error" class="notice-panel state-error" role="alert">
            <p class="eyebrow">Opportunity unavailable</p>
            <h1>This listing can’t be opened</h1>
            <p>{{ error }}</p>
            <a class="button button-primary" href="/" data-internal-link>Browse current opportunities</a>
        </div>
        <article v-else-if="opportunity" class="detail-card">
            <div class="detail-topline">
                <span v-if="opportunity.category?.name" class="category-label">{{ opportunity.category.name }}</span>
                <span class="deadline-label">{{ deadline }}</span>
            </div>
            <h1>{{ opportunity.title }}</h1>
            <p class="detail-organisation">{{ displayText(opportunity.organisation_name, 'Organisation not listed') }}</p>
            <p class="detail-summary">{{ displayText(opportunity.summary) }}</p>

            <div class="trust-status" aria-label="Listing status">
                <div>
                    <span class="status-dot status-published" aria-hidden="true"></span>
                    <strong>{{ opportunity.publication_status === 'published' ? 'Published on OyaFind' : 'Publication status unavailable' }}</strong>
                </div>
                <div>
                    <span class="status-dot" :class="opportunity.verification_status === 'verified' ? 'status-verified' : 'status-warning'" aria-hidden="true"></span>
                    <strong>{{ opportunity.verification_status === 'verified' ? 'Source and details checked' : 'Verification status not confirmed' }}</strong>
                </div>
                <p>These checks do not guarantee provider safety, selection or payment. Contact and apply through the source independently.</p>
            </div>

            <dl class="detail-facts">
                <div>
                    <dt>Deadline</dt>
                    <dd>{{ deadline }}</dd>
                </div>
                <div>
                    <dt>Location</dt>
                    <dd>{{ opportunity.location?.name || 'Not specified' }}</dd>
                </div>
                <div>
                    <dt>Eligibility</dt>
                    <dd>{{ displayText(opportunity.eligibility) }}</dd>
                </div>
                <div>
                    <dt>How to apply</dt>
                    <dd>{{ displayText(opportunity.application_method) }}</dd>
                </div>
                <div v-if="opportunity.requirements">
                    <dt>Requirements</dt>
                    <dd>{{ opportunity.requirements }}</dd>
                </div>
            </dl>

            <section class="detail-copy" aria-labelledby="description-heading">
                <h2 id="description-heading">About this opportunity</h2>
                <p>{{ displayText(opportunity.description, opportunity.summary || 'More information is not available.') }}</p>
                <p v-if="opportunity.application_instructions">{{ opportunity.application_instructions }}</p>
            </section>

            <section class="source-box" aria-labelledby="source-heading">
                <h2 id="source-heading">Source information</h2>
                <p>Review the provider’s original information before applying.</p>
                <a
                    v-if="sourceUrl"
                    class="text-link"
                    :href="sourceUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                >Open source website <span aria-hidden="true">↗</span><span class="visually-hidden">(opens in a new tab)</span></a>
                <p v-else class="muted-copy">A public source link is not available for this listing.</p>
            </section>

            <div class="detail-actions">
                <a
                    v-if="applicationUrl"
                    class="button button-primary"
                    :href="applicationUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                >Continue to provider <span aria-hidden="true">↗</span><span class="visually-hidden">(opens in a new tab)</span></a>
                <span v-else class="button button-disabled" aria-disabled="true">Application link unavailable</span>
                <button class="button button-secondary" type="button" @click="emit('share')">Share opportunity</button>
            </div>
            <p class="application-disclaimer">OyaFind does not submit applications or decide outcomes. Check details with the provider before sharing personal information.</p>

            <div class="share-feedback" aria-live="polite">
                <p v-if="shareMessage" role="status">{{ shareMessage }}</p>
                <div v-if="copyFallback" class="copy-fallback">
                    <label for="share-url">Public page link</label>
                    <input id="share-url" :value="copyUrl" readonly @focus="$event.target.select()">
                    <button class="text-link" type="button" @click="emit('copy')">Copy link</button>
                </div>
            </div>
            <p class="report-entry">
                Notice a problem with this listing?
                <a class="text-link" href="#report-status">Report a problem</a>
            </p>
            <p id="report-status" class="report-entry" tabindex="-1">
                Public reporting is coming soon. No report has been submitted.
            </p>
        </article>
    </section>
</template>
