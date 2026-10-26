<script setup>
import { computed, onMounted, ref } from 'vue';
import UiIcon from '../components/UiIcon.vue';
import { getMyApplications, updateApplication, deleteApplication } from '../api/workspace.js';

defineProps({
    currentUser: { type: Object, default: null },
});

const emit = defineEmits(['navigate', 'removed']);

const applications = ref([]);
const loading = ref(true);
const error = ref('');
const actionMessage = ref('');
const actionError = ref('');
const currentFilter = ref('all');

const editingId = ref(null);
const editStatus = ref('applied');
const editAppliedAt = ref('');
const editNotes = ref('');
const editSaving = ref(false);
const deletingId = ref(null);

const STATUS_CONFIG = {
    interested: { label: 'Interested', tone: 'neutral' },
    preparing: { label: 'Preparing', tone: 'info' },
    applied: { label: 'Applied', tone: 'primary' },
    interview: { label: 'Interview', tone: 'warning' },
    successful: { label: 'Successful', tone: 'success' },
    unsuccessful: { label: 'Unsuccessful', tone: 'muted' },
    'user-reported': { label: 'User-reported', tone: 'neutral' },
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

const activeTabLabel = computed(() => STATUS_CONFIG[currentFilter.value]?.label ?? currentFilter.value);

function formatAppliedDate(dateStr) {
    if (!dateStr) return null;
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return null;
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeZone: 'UTC' }).format(date);
}

function formatDeadline(deadlineAt) {
    if (!deadlineAt) return null;
    const date = new Date(deadlineAt);
    if (Number.isNaN(date.getTime())) return null;
    const formatted = new Intl.DateTimeFormat('en', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
    return date < new Date() ? `Deadline passed · ${formatted}` : `Closes ${formatted}`;
}

async function loadApplications() {
    loading.value = true;
    error.value = '';
    try {
        applications.value = await getMyApplications();
    } catch (err) {
        error.value = err.message || 'Could not load your applications. Check your connection and try again.';
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

        const index = applications.value.findIndex((app) => app.id === id);
        if (index !== -1) {
            applications.value[index] = { ...applications.value[index], ...updated };
        }
        actionMessage.value = 'Tracker record updated.';
        editingId.value = null;
    } catch (err) {
        actionError.value = err.message || 'Could not update this tracker record.';
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
        applications.value = applications.value.filter((app) => app.id !== id);
        actionMessage.value = 'Removed from your tracker.';
        emit('removed', id);
        if (editingId.value === id) editingId.value = null;
    } catch (err) {
        actionError.value = err.message || 'Could not remove this tracker record.';
    } finally {
        deletingId.value = null;
    }
}

onMounted(() => {
    void loadApplications();
});
</script>

<template>
    <div class="workspace-page">
        <header class="workspace-head">
            <div>
                <p class="feed-kicker">Private to you</p>
                <h1>Application tracker</h1>
                <p class="workspace-sub">
                    Record your own progress, dates and notes. Nothing here is sent to an employer.
                </p>
            </div>
            <div class="workspace-actions">
                <a class="button button-secondary" href="/saved" data-internal-link>
                    <UiIcon name="bookmark" :size="16" />
                    Saved opportunities
                </a>
            </div>
        </header>

        <div class="inline-banner is-quiet">
            <UiIcon name="shield-check" :size="18" />
            <div>
                <strong>User-reported and private</strong>
                <p>
                    Every status, date and note here is recorded by you. OyaFind does not submit applications,
                    confirm delivery, or receive outcomes from providers.
                </p>
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

        <nav class="status-tabs" aria-label="Filter applications by stage">
            <button
                v-for="tab in filterTabs"
                :key="tab.key"
                class="status-tab"
                :class="{ 'is-active': currentFilter === tab.key }"
                :aria-pressed="currentFilter === tab.key"
                type="button"
                @click="currentFilter = tab.key"
            >
                {{ tab.label }}
                <span class="tab-count">{{ countsByStatus[tab.key] || 0 }}</span>
            </button>
        </nav>

        <div v-if="loading" class="feed-skeleton" role="status" aria-live="polite">
            <span class="visually-hidden">Loading your tracked applications…</span>
            <div v-for="n in 3" :key="n" class="skeleton-card" aria-hidden="true">
                <span class="skeleton-line is-short"></span>
                <span class="skeleton-line is-title"></span>
                <span class="skeleton-line"></span>
                <span class="skeleton-line is-medium"></span>
            </div>
        </div>

        <div v-else-if="error" class="empty-panel is-error" role="alert">
            <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="shield-check" :size="22" /></span>
            <h2>We couldn’t load your tracker</h2>
            <p>{{ error }}</p>
            <button class="button button-primary" type="button" @click="loadApplications">Try again</button>
        </div>

        <div v-else-if="filteredApplications.length === 0" class="empty-panel">
            <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="chart" :size="22" /></span>
            <h2 v-if="currentFilter === 'all'">Nothing in your tracker yet</h2>
            <h2 v-else>No applications marked “{{ activeTabLabel }}”</h2>
            <p v-if="currentFilter === 'all'">
                Add an opportunity from your saved list, or track one from a listing, to record dates and private notes.
            </p>
            <p v-else>
                Your other application records are still here. Switch back to “All” to see them.
            </p>
            <div class="empty-panel-actions">
                <a class="button button-primary" href="/saved" data-internal-link>Open saved opportunities</a>
                <button v-if="currentFilter !== 'all'" class="button button-secondary" type="button" @click="currentFilter = 'all'">
                    Show all
                </button>
                <a v-else class="button button-secondary" href="/" data-internal-link>Explore opportunities</a>
            </div>
        </div>

        <div v-else class="tracker-list">
            <article
                v-for="app in filteredApplications"
                :key="app.id"
                class="tracker-record"
                :class="{ 'is-unavailable': !app.is_available }"
            >
                <div v-if="editingId !== app.id">
                    <div class="tracker-record-head">
                        <div class="tracker-status-group">
                            <span class="status-pill" :class="`is-${STATUS_CONFIG[app.status]?.tone || 'neutral'}`">
                                <span class="visually-hidden">User-reported status: </span>
                                {{ STATUS_CONFIG[app.status]?.label || app.status }}
                            </span>
                            <span class="user-reported-tag" title="User-reported progress, not confirmed by an employer">
                                User-reported
                            </span>
                        </div>
                        <div class="tracker-record-actions">
                            <button class="button button-secondary button-sm" type="button" @click="startEdit(app)">
                                Edit
                            </button>
                            <button
                                class="text-danger"
                                type="button"
                                :disabled="deletingId === app.id"
                                :aria-label="`Remove ${app.opportunity?.title || 'application'} from tracker`"
                                @click="handleDelete(app.id)"
                            >
                                <UiIcon name="trash" :size="14" />
                                {{ deletingId === app.id ? 'Removing…' : 'Remove' }}
                            </button>
                        </div>
                    </div>

                    <template v-if="app.is_available && app.opportunity">
                        <div class="tracker-opp-topline">
                            <span v-if="app.opportunity.category?.name" class="op-cat">{{ app.opportunity.category.name }}</span>
                            <span v-if="formatDeadline(app.opportunity.deadline_at)" class="op-deadline">
                                {{ formatDeadline(app.opportunity.deadline_at) }}
                            </span>
                        </div>
                        <h2 class="tracker-title">
                            <a :href="`/opportunities/${encodeURIComponent(app.opportunity.slug)}`" data-internal-link>
                                {{ app.opportunity.title }}
                            </a>
                        </h2>
                        <p class="ledger-org">
                            {{ app.opportunity.organisation_name || 'Organisation not listed' }}
                            <span aria-hidden="true">·</span>
                            <span class="op-org-location">
                                <UiIcon name="pin" :size="13" />
                                {{ app.opportunity.location?.name || 'Location not specified' }}
                            </span>
                        </p>
                    </template>

                    <template v-else>
                        <div class="inline-banner is-warning is-compact">
                            <UiIcon name="shield-check" :size="16" />
                            <p>This opportunity is no longer public. Your notes and dates remain in your tracker.</p>
                        </div>
                    </template>

                    <div class="tracker-meta">
                        <div v-if="formatAppliedDate(app.applied_at)" class="tracker-meta-row">
                            <span class="meta-label"><UiIcon name="calendar" :size="14" /> Applied</span>
                            <span class="meta-value">{{ formatAppliedDate(app.applied_at) }}</span>
                        </div>
                        <div v-if="app.notes" class="tracker-notes">
                            <span class="meta-label">Private notes</span>
                            <p>{{ app.notes }}</p>
                        </div>
                        <p v-else class="tracker-no-notes">No notes yet. Add a confirmation number, contact or next step.</p>
                    </div>
                </div>

                <div v-else class="tracker-edit">
                    <header class="tracker-edit-head">
                        <div>
                            <p class="feed-kicker">Editing record</p>
                            <h3>{{ app.opportunity?.title || 'Tracked application' }}</h3>
                        </div>
                        <button class="modal-close" type="button" aria-label="Cancel edit" @click="cancelEdit">
                            <UiIcon name="close" :size="17" />
                        </button>
                    </header>

                    <form @submit.prevent="saveEdit(app.id)">
                        <div class="form-row">
                            <div class="field">
                                <label :for="`edit-status-${app.id}`">Stage</label>
                                <select :id="`edit-status-${app.id}`" v-model="editStatus" required>
                                    <option value="interested">Interested (researching)</option>
                                    <option value="preparing">Preparing (gathering materials)</option>
                                    <option value="applied">Applied (submitted)</option>
                                    <option value="interview">Interview / assessment</option>
                                    <option value="successful">Successful / offered</option>
                                    <option value="unsuccessful">Unsuccessful</option>
                                    <option value="user-reported">User-reported</option>
                                </select>
                            </div>

                            <div class="field">
                                <label :for="`edit-applied-${app.id}`">Date applied <span>optional</span></label>
                                <input :id="`edit-applied-${app.id}`" v-model="editAppliedAt" type="date">
                            </div>
                        </div>

                        <div class="field">
                            <label :for="`edit-notes-${app.id}`">Private notes <span>optional</span></label>
                            <textarea
                                :id="`edit-notes-${app.id}`"
                                v-model="editNotes"
                                rows="3"
                                maxlength="2000"
                                placeholder="Next step, contact person, interview date…"
                            ></textarea>
                            <span class="field-hint">Only you can read these notes.</span>
                        </div>

                        <div class="modal-actions">
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
