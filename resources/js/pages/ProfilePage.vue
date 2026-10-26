<script setup>
import { computed, onMounted, ref } from 'vue';
import { getMyProfile, signOut, updateMyProfile } from '../api/account.js';
import { getCategories } from '../api/publicDiscovery.js';
import { getLocationChoices } from '../api/locationCatalogue.js';
import UiIcon from '../components/UiIcon.vue';

const props = defineProps({
    initialProfile: { type: Object, default: null },
});
const emit = defineEmits(['signed-out']);
const profile = ref(props.initialProfile);
const categories = ref([]);
const locations = ref([]);
const selectedInterests = ref(props.initialProfile?.interests ?? []);
const selectedLocation = ref(props.initialProfile?.home_location_id == null
    ? ''
    : String(props.initialProfile.home_location_id));
const selectedInterestCount = computed(() => selectedInterests.value.length);
const profileInitials = computed(() => (profile.value?.name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join(''));
const contactAddress = computed(() => profile.value?.email || profile.value?.phone || 'No contact details available');
const loading = ref(!props.initialProfile);
const saving = ref(false);
const signingOut = ref(false);
const error = ref('');
const message = ref('');
const savedRecently = ref(false);
const name = ref(props.initialProfile?.name ?? '');

const selectedLocationObject = computed(() => {
    if (!selectedLocation.value) return null;
    return locations.value.find((l) => String(l.id) === String(selectedLocation.value)) ?? null;
});

const selectedCategories = computed(() => {
    return categories.value.filter((cat) => selectedInterests.value.includes(cat.id));
});

const exploreFeedUrl = computed(() => {
    if (selectedLocationObject.value?.slug) {
        return `/?location=${encodeURIComponent(selectedLocationObject.value.slug)}`;
    }
    return '/';
});

async function loadAllLocations() {
    try {
        const all = [];
        let cursor = null;
        for (let i = 0; i < 20; i++) {
            const result = await getLocationChoices('', { cursor, limit: 100 });
            if (Array.isArray(result.items)) {
                all.push(...result.items);
            }
            if (!result.meta?.nextCursor && !result.nextCursor) break;
            cursor = result.meta?.nextCursor ?? result.nextCursor;
            if (!cursor) break;
        }
        locations.value = all;
    } catch {
        try {
            const result = await getLocationChoices('');
            locations.value = result.items || [];
        } catch {
            locations.value = [];
        }
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [currentProfile, categoriesResult] = await Promise.all([
            getMyProfile(),
            getCategories(),
        ]);
        profile.value = currentProfile;
        name.value = currentProfile.name ?? '';
        selectedInterests.value = currentProfile.interests ?? [];
        selectedLocation.value = currentProfile.home_location_id == null ? '' : String(currentProfile.home_location_id);
        categories.value = categoriesResult;
        await loadAllLocations();
    } catch (requestError) {
        error.value = requestError.message || 'Your profile could not be loaded.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    message.value = '';
    savedRecently.value = false;
    try {
        profile.value = await updateMyProfile({
            name: name.value.trim(),
            interests: selectedInterests.value,
            home_location_id: selectedLocation.value ? Number(selectedLocation.value) : null,
        });
        message.value = 'Your profile preferences have been saved.';
        savedRecently.value = true;
    } catch (requestError) {
        error.value = requestError.message || 'Your profile could not be saved.';
    } finally {
        saving.value = false;
    }
}

async function logout() {
    signingOut.value = true;
    error.value = '';
    try {
        await signOut();
        emit('signed-out');
    } catch (requestError) {
        error.value = requestError.message || 'Sign out could not be completed.';
        signingOut.value = false;
    }
}

onMounted(() => {
    if (!props.initialProfile) {
        void load();
    } else {
        name.value = props.initialProfile.name ?? '';
        selectedInterests.value = props.initialProfile.interests ?? [];
        selectedLocation.value = props.initialProfile.home_location_id == null ? '' : String(props.initialProfile.home_location_id);
        void getCategories().then((res) => { categories.value = res; }).catch(() => {});
        void loadAllLocations();
    }
});
</script>

<template>
    <section class="profile-page" aria-labelledby="profile-heading">
        <div class="profile-heading-row">
            <div>
                <p class="profile-eyebrow">Your OyaFind account</p>
                <h1 id="profile-heading">Make it yours.</h1>
                <p class="profile-intro">Choose what you’re looking for and where you’d like to find it.</p>
            </div>
            <button
                v-if="profile"
                class="button button-secondary profile-signout-top"
                type="button"
                :disabled="loading || signingOut"
                :aria-busy="signingOut"
                @click="logout"
            >
                <UiIcon v-if="signingOut" class="loading-spinner" name="loader" :size="17" />
                {{ signingOut ? 'Signing out…' : 'Sign out' }}
            </button>
        </div>

        <p v-if="loading" class="profile-loading" role="status" aria-live="polite">
            <UiIcon class="loading-spinner" name="loader" :size="20" />
            Loading your account…
        </p>

        <div v-else-if="profile" class="profile-layout">
            <div class="profile-main-column">
                <section class="profile-identity" aria-label="Account details">
                    <span class="profile-avatar" aria-hidden="true">{{ profileInitials || 'OF' }}</span>
                    <div class="profile-identity-copy">
                        <h2>{{ profile.name }}</h2>
                        <p>{{ contactAddress }}</p>
                    </div>
                    <span class="profile-account-label">Personal account</span>
                </section>

                <form class="profile-preferences" @submit.prevent="save">
                    <div class="profile-section-heading">
                        <div>
                            <h2>Profile preferences</h2>
                            <p>These details help shape the opportunities you discover.</p>
                        </div>
                    </div>

                    <label for="profile-name">Full name</label>
                    <input id="profile-name" v-model="name" name="name" maxlength="120" required>

                    <div class="profile-location-heading">
                        <label for="profile-home-area">Home area <span>Optional</span></label>
                        <span class="profile-private-note"><UiIcon name="shield-check" :size="15" /> Broad area only</span>
                    </div>
                    <select id="profile-home-area" v-model="selectedLocation" name="home_location_id">
                        <option value="">Choose a city or local area</option>
                        <option v-for="location in locations" :key="location.id" :value="String(location.id)">
                            {{ location.name }}<template v-if="location.parent"> — {{ location.parent.name }}</template>
                        </option>
                    </select>
                    <p class="profile-field-help">We use reviewed locations only. Your precise address is never requested.</p>

                    <fieldset class="profile-interest-fieldset">
                        <legend>
                            <span>What interests you?</span>
                            <span class="profile-interest-count">{{ selectedInterestCount }} selected</span>
                        </legend>
                        <p class="profile-field-help">Pick the opportunity types you want to see more often.</p>
                        <div v-if="categories.length" class="profile-interest-grid">
                            <label v-for="category in categories" :key="category.id" class="profile-interest-option">
                                <input v-model="selectedInterests" type="checkbox" :value="category.id">
                                <span>{{ category.name }}</span>
                            </label>
                        </div>
                        <p v-else class="profile-empty-interests">No active categories are available right now.</p>
                    </fieldset>

                    <p v-if="error" class="account-error profile-feedback" role="alert">{{ error }}</p>
                    <p v-if="message" class="account-success profile-feedback" role="status">{{ message }}</p>
                    <div class="profile-save-row" :aria-busy="saving">
                        <p>Your choices can be updated at any time.</p>
                        <button class="button button-primary" type="submit" :disabled="saving" :aria-busy="saving">
                            <UiIcon v-if="saving" class="loading-spinner" name="loader" :size="17" />
                            {{ saving ? 'Saving…' : 'Save preferences' }}
                            <UiIcon v-if="!saving" name="arrow" :size="17" />
                        </button>
                    </div>

                    <div v-if="savedRecently || message" class="profile-next-action-card" role="region" aria-label="Next steps">
                        <div class="next-action-head">
                            <span class="next-action-icon"><UiIcon name="shield-check" :size="20" /></span>
                            <div>
                                <h3>Preferences saved! What’s next?</h3>
                                <p>Your feed and shortcuts are now tuned to your choices.</p>
                            </div>
                        </div>

                        <div class="next-action-buttons">
                            <a class="button button-primary" :href="exploreFeedUrl" data-internal-link>
                                <span v-if="selectedLocationObject">Explore opportunities in {{ selectedLocationObject.name }}</span>
                                <span v-else>Explore all opportunities</span>
                                <UiIcon name="arrow" :size="16" />
                            </a>
                            <a class="button button-secondary" href="/categories" data-internal-link>
                                Browse all categories
                            </a>
                        </div>

                        <div v-if="selectedCategories.length" class="next-interest-chips">
                            <span class="chips-label">Browse your chosen interests:</span>
                            <div class="chips-list">
                                <a
                                    v-for="cat in selectedCategories"
                                    :key="cat.id"
                                    class="interest-quick-chip"
                                    :href="selectedLocationObject ? `/categories/${encodeURIComponent(cat.slug)}?location=${encodeURIComponent(selectedLocationObject.slug)}` : `/categories/${encodeURIComponent(cat.slug)}`"
                                    data-internal-link
                                >
                                    {{ cat.name }} &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="profile-aside" aria-label="Account status">
                <section v-if="selectedLocationObject || selectedCategories.length" class="profile-shortcuts-card" aria-label="Your active preferences">
                    <span class="profile-shortcuts-icon"><UiIcon name="compass" :size="20" /></span>
                    <div class="shortcuts-content">
                        <h2>Your quick feed</h2>
                        <div v-if="selectedLocationObject" class="shortcut-item">
                            <span class="shortcut-label">Home area:</span>
                            <strong>{{ selectedLocationObject.name }}<template v-if="selectedLocationObject.parent"> ({{ selectedLocationObject.parent.name }})</template></strong>
                            <a class="text-link" :href="exploreFeedUrl" data-internal-link>
                                Browse this area &rarr;
                            </a>
                        </div>
                        <div v-if="selectedCategories.length" class="shortcut-item">
                            <span class="shortcut-label">Interests ({{ selectedCategories.length }}):</span>
                            <div class="shortcut-pill-wrap">
                                <a
                                    v-for="cat in selectedCategories"
                                    :key="cat.id"
                                    class="interest-badge-link"
                                    :href="selectedLocationObject ? `/categories/${encodeURIComponent(cat.slug)}?location=${encodeURIComponent(selectedLocationObject.slug)}` : `/categories/${encodeURIComponent(cat.slug)}`"
                                    data-internal-link
                                >
                                    {{ cat.name }}
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="profile-verification" :class="{ 'is-verified': profile.contact_verified }">
                    <span class="profile-verification-icon">
                        <UiIcon name="shield-check" :size="21" />
                    </span>
                    <p class="profile-card-eyebrow">Contact status</p>
                    <h2>{{ profile.contact_verified ? 'Contact verified' : 'Not verified yet' }}</h2>
                    <p class="profile-verification-copy">
                        {{ profile.contact_verified
                            ? 'Your contact is verified. You can save opportunities to your account.'
                            : 'Saving opportunities is unavailable until your selected contact is verified.' }}
                    </p>
                    <div class="profile-contact-row">
                        <span>{{ profile.email ? 'Email address' : 'Phone number' }}</span>
                        <strong>{{ contactAddress }}</strong>
                    </div>
                    <p v-if="!profile.contact_verified" class="profile-verification-note">
                        Contact verification is not currently configured for local accounts.
                    </p>
                </section>

                <section class="profile-saved-note">
                    <span class="profile-saved-icon"><UiIcon name="book" :size="19" /></span>
                    <div>
                        <h2>Your workspace</h2>
                        <p v-if="profile.save_eligible">Your saved list and private tracker are ready.</p>
                        <p v-else>Saving unlocks after your contact is verified.</p>
                        <p class="profile-saved-links">
                            <a class="text-link" href="/saved" data-internal-link>Saved opportunities</a>
                            <a class="text-link" href="/tracker" data-internal-link>Application tracker</a>
                        </p>
                    </div>
                </section>
            </aside>
        </div>

        <div v-else class="profile-load-error" role="alert">
            <span class="profile-load-error-icon"><UiIcon name="shield-check" :size="22" /></span>
            <h2>We couldn’t load your profile</h2>
            <p>{{ error || 'Check your connection, then try again.' }}</p>
            <button class="button button-secondary" type="button" @click="load">Try again</button>
            <a href="/login" data-internal-link>Return to sign in</a>
        </div>
    </section>
</template>
