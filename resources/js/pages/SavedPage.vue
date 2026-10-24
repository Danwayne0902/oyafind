<script setup>
import { computed, onMounted, ref } from 'vue';
import UiIcon from '../components/UiIcon.vue';
import { getSavedOpportunities, unsaveOpportunity, trackApplication, getMyApplications } from '../api/workspace.js';

const props = defineProps({
    currentUser: { type: Object, default: null },
});

const emit = defineEmits(['navigate']);

const savedList = ref([]);
const existingApplications = ref([]);
const loading = ref(true);
const error = ref('');
const actionMessage = ref('');
const actionError = ref('');
const trackingModalItem = ref(null);
const trackingStatus = ref('interested');
const trackingNotes = ref('');
const trackingAppliedAt = ref('');
const trackingSubmitting = ref(false);
const removingId = ref(null);

const saveEligible = computed(() => Boolean(props.currentUser?.save_eligible));

const trackedOpportunityIds = computed(() => {
    return new Set(existingApplications.value.map((app) => app.opportunity_id));
});

function formatDeadline(deadlineAt) {
    if (!deadlineAt) return 'No deadline listed';
    const date = new Date(deadlineAt);
    if (Number.isNaN(date.getTime())) return 'Deadline details unavailable';
    const formatted = new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date() ? `Deadline passed · ${formatted} UTC` : `Deadline ${formatted} UTC`;
}

async function loadData() {
    loading.value = true;
    error.value = '';
    try {
        const [saved, apps] = await Promise.all([
            getSavedOpportunities(),
            getMyApplications().catch(() => []),
        ]);
        savedList.value = saved;
        existingApplications.value = apps;
    } catch (err) {
        error.value = err.message || 'Could not load saved opportunities. Please check your connection and try again.';
    } finally {
        loading.value = false;
    }
}

async function handleUnsave(item) {
    if (removingId.value) return;
    removingId.value = item.opportunity_id;
    actionMessage.value = '';
    actionError.value = '';
    try {
        await unsaveOpportunity(item.opportunity_id);
        savedList.value = savedList.value.filter((s) => s.opportunity_id !== item.opportunity_id);
        actionMessage.value = 'Opportunity removed from saved list.';
    } catch (err) {
        actionError.value = err.message || 'Failed to remove saved opportunity.';
    } finally {
        removingId.value = null;
    }
}

function openTrackModal(item) {
    trackingModalItem.value = item;
    trackingStatus.value = 'interested';
    trackingNotes.value = '';
    trackingAppliedAt.value = '';
    actionMessage.value = '';
    actionError.value = '';
}

function closeTrackModal() {
    trackingModalItem.value = null;
    trackingSubmitting.value = false;
}

async function handleTrackSubmit() {
    if (!trackingModalItem.value || trackingSubmitting.value) return;
    trackingSubmitting.value = true;
    actionError.value = '';
    try {
        const payload = {
            opportunity_id: trackingModalItem.value.opportunity_id,
            status: trackingStatus.value,
            notes: trackingNotes.value.trim() || null,
            applied_at: trackingAppliedAt.value || null,
        };
        const app = await trackApplication(payload);
        existingApplications.value.push(app);
        actionMessage.value = 'Opportunity added to your application tracker.';
        closeTrackModal();
    } catch (err) {
        actionError.value = err.message || 'Could not add to tracker.';
        trackingSubmitting.value = false;
    }
}

onMounted(() => {
    void loadData();
});
</script>

<template>
    <div class="saved-page-container">
        <header class="saved-header">
            <div class="saved-header-title">
                <h1>Saved opportunities</h1>
                <p class="saved-subtitle">Bookmarked opportunities to review later or track your application progress.</p>
            </div>
            <div class="saved-header-actions">
                <a class="button button-secondary" href="/tracker" data-internal-link>
                    <UiIcon name="chart" :size="16" />
                    Application tracker
                </a>
            </div>
        </header>

        <!-- Unverified contact gate notice if applicable -->
        <div v-if="currentUser && !saveEligible" class="notice-panel notice-warning" role="alert">
            <p class="eyebrow">Contact verification required</p>
            <p><strong>Saving new opportunities is currently paused.</strong> You must verify your email address or phone number before saving additional opportunities.</p>
            <a class="button button-secondary" href="/profile" data-internal-link>Verify contact in profile</a>
        </div>

        <!-- Feedback notices -->
        <div v-if="actionMessage" class="toast-notice success-notice" role="status" aria-live="polite">
            <UiIcon name="check" :size="18" />
            <span>{{ actionMessage }}</span>
            <button class="toast-close-btn" type="button" aria-label="Dismiss message" @click="actionMessage = ''">×</button>
        </div>

        <div v-if="actionError" class="toast-notice error-notice" role="alert">
            <span>{{ actionError }}</span>
            <button class="toast-close-btn" type="button" aria-label="Dismiss error" @click="actionError = ''">×</button>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="state-panel state-loading" role="status" aria-live="polite">
            <UiIcon class="loading-spinner" name="loader" :size="24" />
            <p>Loading your saved opportunities…</p>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="notice-panel state-error" role="alert">
            <p class="eyebrow">Unable to load saves</p>
            <p>{{ error }}</p>
            <button class="button button-primary" type="button" @click="loadData">Try again</button>
        </div>

        <!-- Empty state -->
        <div v-else-if="savedList.length === 0" class="saved-empty-state">
            <div class="empty-icon-wrap" aria-hidden="true">
                <UiIcon name="bookmark" :size="36" />
            </div>
            <h2>You haven’t saved any opportunities yet</h2>
            <p>Bookmark opportunities while exploring so you can easily return to them, review requirements, or track your progress.</p>
            <a class="button button-primary" href="/" data-internal-link>Explore opportunities</a>
        </div>

        <!-- Saved list -->
        <div v-else class="saved-items-grid">
            <article
                v-for="item in savedList"
                :key="item.id"
                class="saved-card"
                :class="{ 'is-unavailable': !item.is_available }"
            >
                <template v-if="item.is_available && item.opportunity">
                    <div class="saved-card-top">
                        <span v-if="item.opportunity.category?.name" class="category-label">
                            {{ item.opportunity.category.name }}
                        </span>
                        <span class="deadline-label">{{ formatDeadline(item.opportunity.deadline_at) }}</span>
                    </div>

                    <h2 class="saved-card-title">
                        <a :href="`/opportunities/${encodeURIComponent(item.opportunity.slug)}`" data-internal-link>
                            {{ item.opportunity.title }}
                        </a>
                    </h2>

                    <p class="saved-card-org">{{ item.opportunity.organisation_name || 'Organisation not listed' }}</p>
                    <p class="saved-card-summary">{{ item.opportunity.summary }}</p>

                    <div class="saved-card-meta">
                        <span class="meta-location">
                            <UiIcon name="pin" :size="14" />
                            {{ item.opportunity.location?.name || 'Location not specified' }}
                        </span>
                    </div>

                    <div class="saved-card-footer">
                        <div class="saved-card-links">
                            <a class="button button-secondary button-sm" :href="`/opportunities/${encodeURIComponent(item.opportunity.slug)}`" data-internal-link>
                                View details
                            </a>
                            <button
                                v-if="!trackedOpportunityIds.has(item.opportunity_id)"
                                class="button button-outline button-sm"
                                type="button"
                                @click="openTrackModal(item)"
                            >
                                <UiIcon name="chart" :size="14" />
                                Track progress
                            </button>
                            <span v-else class="badge-tracked">
                                <UiIcon name="check" :size="12" />
                                In tracker
                            </span>
                        </div>
                        <button
                            class="button-text-danger button-sm"
                            type="button"
                            :disabled="removingId === item.opportunity_id"
                            @click="handleUnsave(item)"
                        >
                            <UiIcon name="trash" :size="14" />
                            Remove
                        </button>
                    </div>
                </template>

                <!-- Non-public or expired listing safe state -->
                <template v-else>
                    <div class="unavailable-listing-banner">
                        <span class="status-dot status-warning" aria-hidden="true"></span>
                        <strong>Opportunity no longer public</strong>
                    </div>
                    <p class="unavailable-copy">
                        This opportunity is no longer publicly available or has expired. Restricted listing details are not disclosed.
                    </p>
                    <div class="saved-card-footer">
                        <button
                            class="button button-secondary button-sm"
                            type="button"
                            :disabled="removingId === item.opportunity_id"
                            @click="handleUnsave(item)"
                        >
                            <UiIcon name="trash" :size="14" />
                            Remove from saved
                        </button>
                    </div>
                </template>
            </article>
        </div>

        <!-- Track application modal -->
        <div v-if="trackingModalItem" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="track-modal-title">
            <div class="modal-card">
                <header class="modal-header">
                    <h2 id="track-modal-title">Track application progress</h2>
                    <button class="modal-close-btn" type="button" aria-label="Close" @click="closeTrackModal">×</button>
                </header>

                <div class="modal-body">
                    <p class="modal-subtitle">
                        Tracking <strong>{{ trackingModalItem.opportunity?.title }}</strong>
                    </p>

                    <div class="trust-notice-box">
                        <UiIcon name="shield-check" :size="16" />
                        <p><strong>Private tracker:</strong> Statuses and notes are user-reported and private to your account. OyaFind does not submit applications or communicate with employers.</p>
                    </div>

                    <form @submit.prevent="handleTrackSubmit">
                        <div class="form-group">
                            <label for="track-status">Application stage</label>
                            <select id="track-status" v-model="trackingStatus" class="form-control" required>
                                <option value="interested">Interested (Researching)</option>
                                <option value="preparing">Preparing (Gathering materials)</option>
                                <option value="applied">Applied (Submitted application)</option>
                                <option value="interview">Interview / Assessment</option>
                                <option value="successful">Successful / Offered</option>
                                <option value="unsuccessful">Unsuccessful</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="track-applied-at">Date applied (optional)</label>
                            <input
                                id="track-applied-at"
                                v-model="trackingAppliedAt"
                                type="date"
                                class="form-control"
                            />
                        </div>

                        <div class="form-group">
                            <label for="track-notes">Private notes (optional)</label>
                            <textarea
                                id="track-notes"
                                v-model="trackingNotes"
                                class="form-control"
                                rows="3"
                                placeholder="E.g. submission confirmation number, contact person, interview dates, questions asked..."
                            ></textarea>
                            <span class="field-hint">Notes are private to you and never shared.</span>
                        </div>

                        <div class="modal-footer">
                            <button class="button button-secondary" type="button" @click="closeTrackModal">Cancel</button>
                            <button class="button button-primary" type="submit" :disabled="trackingSubmitting">
                                {{ trackingSubmitting ? 'Saving…' : 'Add to tracker' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

