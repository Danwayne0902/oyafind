<script setup>
import { computed, onMounted, ref } from 'vue';
import UiIcon from '../components/UiIcon.vue';
import { getMyApplications, updateApplication, deleteApplication } from '../api/workspace.js';

const props = defineProps({
    currentUser: { type: Object, default: null },
});

const emit = defineEmits(['navigate']);

const applications = ref([]);
const loading = ref(true);
const error = ref('');
const actionMessage = ref('');
const actionError = ref('');
const currentFilter = ref('all');

// Editing state
const editingId = ref(null);
const editStatus = ref('applied');
const editAppliedAt = ref('');
const editNotes = ref('');
const editSaving = ref(false);
const deletingId = ref(null);

const STATUS_CONFIG = {
    interested: { label: 'Interested', colorClass: 'badge-status-neutral' },
    preparing: { label: 'Preparing application', colorClass: 'badge-status-info' },
    applied: { label: 'Applied', colorClass: 'badge-status-primary' },
    interview: { label: 'Interview / Assessment', colorClass: 'badge-status-warning' },
    successful: { label: 'Successful / Offer', colorClass: 'badge-status-success' },
    unsuccessful: { label: 'Unsuccessful', colorClass: 'badge-status-muted' },
    'user-reported': { label: 'User-reported', colorClass: 'badge-status-neutral' },
};

const filterTabs = [
    { key: 'all', label: 'All' },
    { key: 'interested', label: 'Interested' },
    { key: 'preparing', label: 'Preparing' },
    { key: 'applied', label: 'Applied' },
    { key: 'interview', label: 'Interview' },
    { key: 'successful', label: 'Successful' },
    { key: 'unsuccessful', label: 'Unsuccessful' },
];

const countsByStatus = computed(() => {
    const counts = { all: applications.value.length };
    filterTabs.forEach((tab) => {
        if (tab.key !== 'all') {
            counts[tab.key] = applications.value.filter((app) => app.status === tab.key).length;
        }
    });
    return counts;
});

const filteredApplications = computed(() => {
    if (currentFilter.value === 'all') return applications.value;
    return applications.value.filter((app) => app.status === currentFilter.value);
});

function formatAppliedDate(dateStr) {
    if (!dateStr) return null;
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return null;
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(date);
}

function formatDeadline(deadlineAt) {
    if (!deadlineAt) return null;
    const date = new Date(deadlineAt);
    if (Number.isNaN(date.getTime())) return null;
    const formatted = new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date() ? `Deadline passed (${formatted} UTC)` : `Deadline: ${formatted} UTC`;
}

async function loadApplications() {
    loading.value = true;
    error.value = '';
    try {
        applications.value = await getMyApplications();
    } catch (err) {
        error.value = err.message || 'Could not load your applications. Please check your connection and try again.';
    } finally {
        loading.value = false;
    }
}

function startEdit(app) {
    editingId.value = app.id;
    editStatus.value = app.status;
    editAppliedAt.value = app.applied_at || '';
    editNotes.value = app.notes || '';
    actionMessage.value = '';
    actionError.value = '';
}

function cancelEdit() {
    editingId.value = null;
    editSaving.value = false;
}

async function saveEdit(id) {
    if (editSaving.value) return;
    editSaving.value = true;
    actionMessage.value = '';
    actionError.value = '';
    try {
        const updated = await updateApplication(id, {
            status: editStatus.value,
            notes: editNotes.value.trim() || null,
            applied_at: editAppliedAt.value || null,
        });

        // Update in-place
        const index = applications.value.findIndex((a) => a.id === id);
        if (index !== -1) {
            applications.value[index] = {
                ...applications.value[index],
                ...updated,
            };
        }
        actionMessage.value = 'Application record updated.';
        editingId.value = null;
    } catch (err) {
        actionError.value = err.message || 'Could not update application record.';
    } finally {
        editSaving.value = false;
    }
}

async function handleDelete(id) {
    if (deletingId.value) return;
    deletingId.value = id;
    actionMessage.value = '';
    actionError.value = '';
    try {
        await deleteApplication(id);
        applications.value = applications.value.filter((a) => a.id !== id);
        actionMessage.value = 'Application removed from tracker.';
        if (editingId.value === id) {
            editingId.value = null;
        }
    } catch (err) {
        actionError.value = err.message || 'Failed to remove application.';
    } finally {
        deletingId.value = null;
    }
}

onMounted(() => {
    void loadApplications();
});
</script>

<template>
    <div class="tracker-page-container">
        <header class="tracker-header">
            <div class="tracker-header-title">
                <h1>Private application tracker</h1>
                <p class="tracker-subtitle">
                    Keep track of applications, interview stages, and notes in one private place.
                </p>
            </div>
            <div class="tracker-header-actions">
                <a class="button button-secondary" href="/saved" data-internal-link>
                    <UiIcon name="bookmark" :size="16" />
                    Saved opportunities
                </a>
            </div>
        </header>

        <!-- Trust & privacy policy banner -->
        <div class="tracker-trust-banner">
            <div class="trust-banner-icon" aria-hidden="true">
                <UiIcon name="shield-check" :size="20" />
            </div>
            <div class="trust-banner-content">
                <strong>Strictly user-reported and private</strong>
                <p>
                    All statuses, dates, and notes are recorded by you for your own tracking. OyaFind does not submit applications to employers, verify application delivery, or receive outcome confirmations from providers.
                </p>
            </div>
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

        <!-- Filter tabs -->
        <nav class="tracker-filter-tabs" aria-label="Filter applications by stage">
            <button
                v-for="tab in filterTabs"
                :key="tab.key"
                class="filter-tab-btn"
                :class="{ 'is-active': currentFilter === tab.key }"
                :aria-pressed="currentFilter === tab.key"
                type="button"
                @click="currentFilter = tab.key"
            >
                <span>{{ tab.label }}</span>
                <span class="tab-count-pill">{{ countsByStatus[tab.key] || 0 }}</span>
            </button>
        </nav>

        <!-- Loading state -->
        <div v-if="loading" class="state-panel state-loading" role="status" aria-live="polite">
            <UiIcon class="loading-spinner" name="loader" :size="24" />
            <p>Loading your tracked applications…</p>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="notice-panel state-error" role="alert">
            <p class="eyebrow">Unable to load tracker</p>
            <p>{{ error }}</p>
            <button class="button button-primary" type="button" @click="loadApplications">Try again</button>
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredApplications.length === 0" class="tracker-empty-state">
            <div class="empty-icon-wrap" aria-hidden="true">
                <UiIcon name="chart" :size="36" />
            </div>
            <h2 v-if="currentFilter === 'all'">Your application tracker is empty</h2>
            <h2 v-else>No applications marked as “{{ STATUS_CONFIG[currentFilter]?.label || currentFilter }}”</h2>
            <p>
                Add opportunities you are pursuing from your <a href="/saved" data-internal-link class="text-link">saved list</a> or while exploring to record interview dates, preparation progress, and notes.
            </p>
            <div class="empty-actions">
                <a class="button button-primary" href="/saved" data-internal-link>View saved opportunities</a>
                <a class="button button-secondary" href="/" data-internal-link>Explore opportunities</a>
            </div>
        </div>

        <!-- Application cards -->
        <div v-else class="tracker-items-list">
            <article
                v-for="app in filteredApplications"
                :key="app.id"
                class="tracker-card"
                :class="{ 'is-unavailable': !app.is_available }"
            >
                <!-- View Mode -->
                <div v-if="editingId !== app.id" class="tracker-card-content">
                    <div class="tracker-card-header">
                        <div class="status-indicator-wrap">
                            <span
                                class="status-badge"
                                :class="STATUS_CONFIG[app.status]?.colorClass || 'badge-status-neutral'"
                            >
                                <span class="visually-hidden">User-reported status: </span>
                                {{ STATUS_CONFIG[app.status]?.label || app.status }}
                            </span>
                            <span class="user-reported-tag" title="User-reported progress, not verified by employer">
                                User-reported
                            </span>
                        </div>

                        <div class="tracker-card-actions">
                            <button
                                class="button button-secondary button-sm"
                                type="button"
                                @click="startEdit(app)"
                            >
                                Edit stage & notes
                            </button>
                            <button
                                class="button-text-danger button-sm"
                                type="button"
                                :disabled="deletingId === app.id"
                                aria-label="Remove from tracker"
                                @click="handleDelete(app.id)"
                            >
                                <UiIcon name="trash" :size="14" />
                                Remove
                            </button>
                        </div>
                    </div>

                    <!-- Available opportunity information -->
                    <template v-if="app.is_available && app.opportunity">
                        <div class="tracker-opp-details">
                            <div class="tracker-opp-topline">
                                <span v-if="app.opportunity.category?.name" class="category-label">
                                    {{ app.opportunity.category.name }}
                                </span>
                                <span v-if="formatDeadline(app.opportunity.deadline_at)" class="deadline-label">
                                    {{ formatDeadline(app.opportunity.deadline_at) }}
                                </span>
                            </div>

                            <h2 class="tracker-opp-title">
                                <a :href="`/opportunities/${encodeURIComponent(app.opportunity.slug)}`" data-internal-link>
                                    {{ app.opportunity.title }}
                                </a>
                            </h2>

                            <p class="tracker-opp-org">{{ app.opportunity.organisation_name || 'Organisation not listed' }}</p>

                            <div v-if="app.opportunity.location?.name" class="tracker-opp-meta">
                                <span class="meta-location">
                                    <UiIcon name="pin" :size="14" />
                                    {{ app.opportunity.location.name }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <!-- Safe unavailable listing display -->
                    <template v-else>
                        <div class="unavailable-listing-banner">
                            <span class="status-dot status-warning" aria-hidden="true"></span>
                            <strong>Opportunity no longer public</strong>
                        </div>
                        <p class="unavailable-copy">
                            This opportunity is no longer published or has expired. Your private notes and application dates remain safely preserved in your tracker.
                        </p>
                    </template>

                    <!-- Application details metadata -->
                    <div class="tracker-meta-box">
                        <div v-if="formatAppliedDate(app.applied_at)" class="tracker-meta-row">
                            <span class="meta-label">
                                <UiIcon name="calendar" :size="14" />
                                Applied date:
                            </span>
                            <span class="meta-value">{{ formatAppliedDate(app.applied_at) }}</span>
                        </div>

                        <div v-if="app.notes" class="tracker-notes-block">
                            <span class="meta-label">Private notes:</span>
                            <p class="tracker-notes-text">{{ app.notes }}</p>
                        </div>
                        <p v-else class="tracker-no-notes">
                            No notes added yet. Click “Edit stage & notes” to record notes.
                        </p>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div v-else class="tracker-card-edit-form">
                    <header class="edit-form-header">
                        <h3>Update tracking record</h3>
                        <p class="field-hint">Statuses and notes are private and user-reported.</p>
                    </header>

                    <form @submit.prevent="saveEdit(app.id)">
                        <div class="form-row">
                            <div class="form-group">
                                <label :for="`edit-status-${app.id}`">Stage</label>
                                <select
                                    :id="`edit-status-${app.id}`"
                                    v-model="editStatus"
                                    class="form-control"
                                    required
                                >
                                    <option value="interested">Interested (Researching)</option>
                                    <option value="preparing">Preparing application</option>
                                    <option value="applied">Applied (Submitted)</option>
                                    <option value="interview">Interview / Assessment</option>
                                    <option value="successful">Successful / Offered</option>
                                    <option value="unsuccessful">Unsuccessful</option>
                                    <option value="user-reported">User-reported</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label :for="`edit-applied-${app.id}`">Application date</label>
                                <input
                                    :id="`edit-applied-${app.id}`"
                                    v-model="editAppliedAt"
                                    type="date"
                                    class="form-control"
                                />
                            </div>
                        </div>

                        <div class="form-group">
                            <label :for="`edit-notes-${app.id}`">Private notes</label>
                            <textarea
                                :id="`edit-notes-${app.id}`"
                                v-model="editNotes"
                                class="form-control"
                                rows="3"
                                placeholder="E.g. application confirmation number, next steps, interview dates, questions asked..."
                            ></textarea>
                            <span class="field-hint">Only you can view and edit these notes.</span>
                        </div>

                        <div class="edit-form-actions">
                            <button class="button button-secondary" type="button" @click="cancelEdit">Cancel</button>
                            <button class="button button-primary" type="submit" :disabled="editSaving">
                                {{ editSaving ? 'Saving…' : 'Save changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </article>
        </div>
    </div>
</template>

