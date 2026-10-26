<script setup>
import { computed, onMounted, ref } from 'vue';
import TrustStamp from '../components/TrustStamp.vue';
import UiIcon from '../components/UiIcon.vue';
import { getSavedOpportunities, unsaveOpportunity, trackApplication, getMyApplications } from '../api/workspace.js';

const props = defineProps({
    currentUser: { type: Object, default: null },
});

const emit = defineEmits(['navigate', 'unsaved', 'tracked']);

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

const trackedOpportunityIds = computed(() => new Set(existingApplications.value.map((app) => app.opportunity_id)));

function formatDeadline(deadlineAt) {
    if (!deadlineAt) return 'No deadline listed';
    const date = new Date(deadlineAt);
    if (Number.isNaN(date.getTime())) return 'Deadline details unavailable';
    const formatted = new Intl.DateTimeFormat('en', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date() ? `Deadline passed · ${formatted}` : `Closes ${formatted}`;
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
        error.value = err.message || 'Could not load saved opportunities. Check your connection and try again.';
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
        savedList.value = savedList.value.filter((saved) => saved.opportunity_id !== item.opportunity_id);
        actionMessage.value = 'Removed from your saved list.';
        emit('unsaved', item.opportunity_id);
    } catch (err) {
        actionError.value = err.message || 'Could not remove this saved opportunity.';
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
        const app = await trackApplication({
            opportunity_id: trackingModalItem.value.opportunity_id,
            status: trackingStatus.value,
            notes: trackingNotes.value.trim() || null,
            applied_at: trackingAppliedAt.value || null,
        });
        existingApplications.value.push(app);
        emit('tracked', app.opportunity_id);
        actionMessage.value = 'Added to your private tracker.';
        closeTrackModal();
    } catch (err) {
        actionError.value = err.message || 'Could not add this opportunity to your tracker.';
        trackingSubmitting.value = false;
    }
}

onMounted(() => {
    void loadData();
});
</script>

<template>
    <div class="workspace-page">
        <header class="workspace-head">
            <div>
                <p class="feed-kicker">Your shortlist</p>
                <h1>Saved opportunities</h1>
                <p class="workspace-sub">
                    Keep a shortlist, compare details, and move the ones you are pursuing into your private tracker.
                </p>
            </div>
            <div class="workspace-actions">
                <a class="button button-secondary" href="/tracker" data-internal-link>
                    <UiIcon name="chart" :size="16" />
                    Application tracker
                </a>
            </div>
        </header>

        <div v-if="currentUser && !saveEligible" class="inline-banner is-warning" role="alert">
            <UiIcon name="shield-check" :size="18" />
            <div>
                <strong>Your contact is not verified yet.</strong>
                <p>Saving new opportunities stays paused until you verify the email address or phone number on your account.</p>
                <a class="text-link" href="/profile" data-internal-link>Open profile settings <span aria-hidden="true">→</span></a>
            </div>
        </div>

        <div v-if="actionMessage" class="toast is-success" role="status" aria-live="polite">
            <UiIcon name="check" :size="17" />
            <span>{{ actionMessage }}</span>
            <button class="toast-close" type="button" aria-label="Dismiss message" @click="actionMessage = ''">
                <UiIcon name="close" :size="15" />
            </button>
        </div>

        <div v-if="actionError" class="toast is-error" role="alert">
            <span>{{ actionError }}</span>
            <button class="toast-close" type="button" aria-label="Dismiss error" @click="actionError = ''">
                <UiIcon name="close" :size="15" />
            </button>
        </div>

        <div v-if="loading" class="feed-skeleton" role="status" aria-live="polite">
            <span class="visually-hidden">Loading your saved opportunities…</span>
            <div v-for="n in 3" :key="n" class="skeleton-card" aria-hidden="true">
                <span class="skeleton-line is-short"></span>
                <span class="skeleton-line is-title"></span>
                <span class="skeleton-line"></span>
                <span class="skeleton-line is-medium"></span>
            </div>
        </div>

        <div v-else-if="error" class="empty-panel is-error" role="alert">
            <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="shield-check" :size="22" /></span>
            <h2>We couldn’t load your saves</h2>
            <p>{{ error }}</p>
            <button class="button button-primary" type="button" @click="loadData">Try again</button>
        </div>

        <div v-else-if="savedList.length === 0" class="empty-panel">
            <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="bookmark" :size="22" /></span>
            <h2>Nothing saved yet</h2>
            <p>
                Use Save on any public listing to keep it here. Saved listings stay private to your account,
                and you can remove them at any time.
            </p>
            <div class="empty-panel-actions">
                <a class="button button-primary" href="/" data-internal-link>Explore opportunities</a>
                <a class="button button-secondary" href="/how-it-works" data-internal-link>How saving works</a>
            </div>
        </div>

        <div v-else class="ledger-list">
            <article
                v-for="item in savedList"
                :key="item.id"
                class="ledger-card"
                :class="{ 'is-unavailable': !item.is_available }"
            >
                <template v-if="item.is_available && item.opportunity">
                    <div class="ledger-top">
                        <span v-if="item.opportunity.category?.name" class="op-cat">{{ item.opportunity.category.name }}</span>
                        <span class="op-deadline">{{ formatDeadline(item.opportunity.deadline_at) }}</span>
                    </div>

                    <h2 class="ledger-title">
                        <a :href="`/opportunities/${encodeURIComponent(item.opportunity.slug)}`" data-internal-link>
                            {{ item.opportunity.title }}
                        </a>
                    </h2>

                    <p class="ledger-org">
                        {{ item.opportunity.organisation_name || 'Organisation not listed' }}
                        <span aria-hidden="true">·</span>
                        <span class="op-org-location">
                            <UiIcon name="pin" :size="13" />
                            {{ item.opportunity.location?.name || 'Location not specified' }}
                        </span>
                    </p>
                    <p class="ledger-summary">{{ item.opportunity.summary }}</p>

                    <div class="ledger-foot">
                        <TrustStamp :verified-at="item.opportunity.verified_at" />
                        <div class="op-actions">
                            <a class="button button-secondary button-sm" :href="`/opportunities/${encodeURIComponent(item.opportunity.slug)}`" data-internal-link>
                                View
                            </a>
                            <button
                                v-if="!trackedOpportunityIds.has(item.opportunity_id)"
                                class="button button-secondary button-sm"
                                type="button"
                                @click="openTrackModal(item)"
                            >
                                Track
                            </button>
                            <span v-else class="badge is-trust">
                                <UiIcon name="check" :size="12" /> In tracker
                            </span>
                            <button
                                class="text-danger saved-remove"
                                type="button"
                                :disabled="removingId === item.opportunity_id"
                                @click="handleUnsave(item)"
                            >
                                <UiIcon name="trash" :size="15" />
                                <span>{{ removingId === item.opportunity_id ? 'Removing…' : 'Remove' }}</span>
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="ledger-top">
                        <span class="badge is-warning">No longer public</span>
                    </div>
                    <h2 class="ledger-title is-unavailable">Opportunity no longer available</h2>
                    <p class="ledger-summary">
                        This listing is no longer published or has expired. Restricted details are not shown.
                        Your saved record can be removed below.
                    </p>
                    <div class="ledger-foot">
                        <span class="muted-copy">OyaFind keeps the link out of public view.</span>
                        <div class="op-actions">
                            <button
                                class="text-danger saved-remove"
                                type="button"
                                :disabled="removingId === item.opportunity_id"
                                @click="handleUnsave(item)"
                            >
                                <UiIcon name="trash" :size="15" />
                                <span>{{ removingId === item.opportunity_id ? 'Removing…' : 'Remove' }}</span>
                            </button>
                        </div>
                    </div>
                </template>
            </article>
        </div>

        <div v-if="trackingModalItem" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="track-modal-title" @click.self="closeTrackModal">
            <div class="modal-card">
                <header class="modal-head">
                    <div>
                        <p class="feed-kicker">Private tracker</p>
                        <h2 id="track-modal-title">Track this application</h2>
                    </div>
                    <button class="modal-close" type="button" aria-label="Close" @click="closeTrackModal">
                        <UiIcon name="close" :size="17" />
                    </button>
                </header>

                <div class="modal-body">
                    <p class="modal-subtitle">{{ trackingModalItem.opportunity?.title }}</p>

                    <div class="inline-banner is-quiet">
                        <UiIcon name="shield-check" :size="17" />
                        <p>
                            Statuses and notes are user-reported and private to your account.
                            OyaFind does not submit applications or communicate with employers.
                        </p>
                    </div>

                    <form @submit.prevent="handleTrackSubmit">
                        <div class="field">
                            <label for="track-status">Stage</label>
                            <select id="track-status" v-model="trackingStatus" required>
                                <option value="interested">Interested (researching)</option>
                                <option value="preparing">Preparing (gathering materials)</option>
                                <option value="applied">Applied (submitted)</option>
                                <option value="interview">Interview / assessment</option>
                                <option value="successful">Successful / offered</option>
                                <option value="unsuccessful">Unsuccessful</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="track-applied-at">Date applied <span>optional</span></label>
                            <input id="track-applied-at" v-model="trackingAppliedAt" type="date">
                        </div>

                        <div class="field">
                            <label for="track-notes">Private notes <span>optional</span></label>
                            <textarea
                                id="track-notes"
                                v-model="trackingNotes"
                                rows="3"
                                maxlength="2000"
                                placeholder="Confirmation number, contact person, interview date…"
                            ></textarea>
                            <span class="field-hint">Only you can read these notes.</span>
                        </div>

                        <div class="modal-actions">
                            <button class="button button-secondary" type="button" @click="closeTrackModal">Cancel</button>
                            <button class="button button-primary" type="submit" :disabled="trackingSubmitting">
                                {{ trackingSubmitting ? 'Adding…' : 'Add to tracker' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
