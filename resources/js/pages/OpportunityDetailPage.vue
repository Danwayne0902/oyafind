<script setup>
import { computed } from 'vue';
import SaveButton from '../components/SaveButton.vue';
import UiIcon from '../components/UiIcon.vue';

const props = defineProps({
    opportunity: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    shareMessage: { type: String, default: '' },
    copyFallback: { type: Boolean, default: false },
    copyUrl: { type: String, default: '' },
    safeExternalUrl: { type: Function, required: true },
    saved: { type: Boolean, default: false },
    savePending: { type: Boolean, default: false },
    tracked: { type: Boolean, default: false },
});

const emit = defineEmits(['back', 'share', 'copy', 'save', 'track']);

const APPLICATION_METHODS = {
    external_url: 'Apply on the provider’s website',
    email: 'Apply by email',
    in_person: 'Apply in person',
    instructions: 'Follow the provider’s instructions',
};

const WORK_MODES = {
    onsite: 'On-site',
    remote: 'Remote',
    hybrid: 'Hybrid',
};

const applicationUrl = computed(() => props.safeExternalUrl(props.opportunity?.application_url));
const sourceUrl = computed(() => props.safeExternalUrl(props.opportunity?.source_url));

const deadline = computed(() => {
    if (!props.opportunity?.deadline_at) return 'No deadline listed';
    const date = new Date(props.opportunity.deadline_at);
    if (Number.isNaN(date.getTime())) return 'Deadline details unavailable';
    const formatted = new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date() ? `Deadline passed · ${formatted} UTC` : `${formatted} UTC`;
});

const verificationDate = computed(() => {
    if (!props.opportunity?.verified_at) return null;
    const date = new Date(props.opportunity.verified_at);
    return Number.isNaN(date.getTime()) ? null : date;
});

const verificationLabel = computed(() => {
    if (props.opportunity?.verification_status !== 'verified') return 'Verification status not confirmed';
    if (!verificationDate.value) return 'Source and details checked';
    return `Source and details checked ${new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(verificationDate.value)}`;
});

const applicationMethod = computed(() => APPLICATION_METHODS[props.opportunity?.application_method] ?? 'See the provider’s instructions');
const workMode = computed(() => WORK_MODES[props.opportunity?.work_mode] ?? null);

const amount = computed(() => {
    const min = props.opportunity?.amount_min;
    const max = props.opportunity?.amount_max;
    const currency = props.opportunity?.currency;
    if (!currency || (min == null && max == null)) return null;
    const format = (value) => new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(Number(value));
    if (min != null && max != null) return `${format(min)} – ${format(max)}`;
    return min != null ? `From ${format(min)}` : `Up to ${format(max)}`;
});

function displayText(value, fallback = 'Not provided') {
    return typeof value === 'string' && value.trim() ? value : fallback;
}

const descriptionParagraphs = computed(() => {
    const text = typeof props.opportunity?.description === 'string' && props.opportunity.description.trim()
        ? props.opportunity.description
        : props.opportunity?.summary || '';
    const blocks = text.replace(/\r\n?/g, '\n').trim().split(/\n{2,}/).map((block) => block.trim()).filter(Boolean);
    return blocks.length ? blocks : ['More information is not available.'];
});

function formatRequirements(value) {
    if (value == null || value === '') return 'Not provided';
    if (Array.isArray(value)) return value.filter(Boolean).join(' · ') || 'Not provided';
    if (typeof value === 'object') {
        const parts = Object.values(value).flat().filter(Boolean);
        return parts.length ? parts.join(' · ') : 'Not provided';
    }
    return String(value);
}
</script>

<template>
    <section class="detail-page">
        <a class="back-link" href="/search" data-internal-link @click.prevent="emit('back')">
            ← Back to opportunities
        </a>
        <p v-if="loading" class="state-panel state-loading" role="status" aria-live="polite">
            <UiIcon class="loading-spinner" name="loader" :size="19" />
            Loading opportunity details…
        </p>
        <div v-else-if="error" class="empty-panel is-error" role="alert">
            <p class="eyebrow">Opportunity unavailable</p>
            <h1>This listing can’t be opened</h1>
            <p>{{ error }}</p>
            <a class="button button-primary" href="/" data-internal-link>Browse current opportunities</a>
        </div>
        <article v-else-if="opportunity" class="detail-card">
            <header class="detail-header">
                <div class="detail-topline">
                    <span v-if="opportunity.category?.name" class="op-cat">{{ opportunity.category.name }}</span>
                    <span v-if="workMode" class="op-mode">{{ workMode }}</span>
                    <span class="op-deadline" :class="{ 'is-urgent': opportunity.deadline_at && new Date(opportunity.deadline_at) < new Date(Date.now() + 7 * 86400000) }">
                        {{ deadline }}
                    </span>
                </div>
                <h1>{{ opportunity.title }}</h1>
                <p class="detail-organisation">
                    {{ displayText(opportunity.organisation_name, 'Organisation not listed') }}
                    <span aria-hidden="true">·</span>
                    <span class="op-org-location">
                        <UiIcon name="pin" :size="14" />
                        {{ opportunity.location?.name || 'Location not specified' }}
                    </span>
                    <template v-if="amount">
                        <span aria-hidden="true">·</span>
                        <strong class="detail-amount">{{ amount }}</strong>
                    </template>
                </p>
                <p class="detail-summary">{{ displayText(opportunity.summary) }}</p>
            </header>

            <section class="check-panel" aria-label="Listing status and checks">
                <div class="check-row">
                    <span class="check-dot is-published" aria-hidden="true"></span>
                    <div>
                        <strong>{{ opportunity.publication_status === 'published' ? 'Published on OyaFind' : 'Publication status unavailable' }}</strong>
                        <span>This listing is currently displayed to the public.</span>
                    </div>
                </div>
                <div class="check-row">
                    <span class="check-dot" :class="opportunity.verification_status === 'verified' ? 'is-checked' : 'is-warning'" aria-hidden="true"></span>
                    <div>
                        <strong>{{ verificationLabel }}</strong>
                        <span v-if="verificationDate">The check date comes from OyaFind’s review record.</span>
                        <span v-else>The check date is not recorded on this page.</span>
                    </div>
                </div>
                <p class="check-note">
                    These checks do not guarantee provider safety, selection or payment.
                    Contact and apply through the source independently.
                </p>
            </section>

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
                    <dd>{{ applicationMethod }}</dd>
                </div>
                <div v-if="opportunity.requirements">
                    <dt>Requirements</dt>
                    <dd>{{ formatRequirements(opportunity.requirements) }}</dd>
                </div>
            </dl>

            <section class="detail-copy" aria-labelledby="description-heading">
                <h2 id="description-heading">About this opportunity</h2>
                <p v-for="(paragraph, index) in descriptionParagraphs" :key="index">{{ paragraph }}</p>
                <p v-if="opportunity.application_instructions">{{ opportunity.application_instructions }}</p>
            </section>

            <div class="detail-act">
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
                    <SaveButton :saved="saved" :pending="savePending" @toggle="emit('save', opportunity)" />
                    <button class="button button-secondary" type="button" @click="emit('share')">Share</button>
                </div>

                <div class="detail-track-row">
                    <span>Pursuing this one?</span>
                    <a v-if="tracked" class="text-link" href="/tracker" data-internal-link>Open it in your tracker <span aria-hidden="true">→</span></a>
                    <button v-else class="text-link" type="button" @click="emit('track', opportunity)">
                        Track it privately <span aria-hidden="true">→</span>
                    </button>
                </div>

                <p class="application-disclaimer">
                    OyaFind does not submit applications or decide outcomes.
                    Check details with the provider before sharing personal information.
                </p>
            </div>

            <div class="share-feedback" aria-live="polite">
                <p v-if="shareMessage" role="status">{{ shareMessage }}</p>
                <div v-if="copyFallback" class="copy-fallback">
                    <label for="share-url">Public page link</label>
                    <input id="share-url" :value="copyUrl" readonly @focus="$event.target.select()">
                    <button class="text-link" type="button" @click="emit('copy')">Copy link</button>
                </div>
            </div>
        </article>
    </section>
</template>
