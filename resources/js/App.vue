<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import DiscoveryPage from './pages/DiscoveryPage.vue';
import OpportunityDetailPage from './pages/OpportunityDetailPage.vue';
import SavedPage from './pages/SavedPage.vue';
import MyOpportunitiesPage from './pages/MyOpportunitiesPage.vue';
import RegisterPage from './pages/RegisterPage.vue';
import LoginPage from './pages/LoginPage.vue';
import VerifyEmailPage from './pages/VerifyEmailPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import CategoriesPage from './pages/CategoriesPage.vue';
import HowItWorksPage from './pages/HowItWorksPage.vue';
import UiIcon from './components/UiIcon.vue';
import { getCategories, getOpportunity, getOpportunities } from './api/publicDiscovery.js';
import { getMyProfile, signOut } from './api/account.js';
import {
    getMyApplications,
    getSavedOpportunities,
    saveOpportunity,
    trackApplication,
    unsaveOpportunity,
} from './api/workspace.js';
import { canonicalPageUrl, parsePublicRoute, safeExternalUrl, sharePageUrl, withLocationFilter } from './publicRoutes.js';
import { useClickSound } from './useClickSound.js';

const route = ref(parsePublicRoute(window.location));
const menuOpen = ref(false);
const currentUser = ref(null);
const authReady = ref(false);
const opportunities = ref([]);
const categories = ref([]);
const categoriesLoading = ref(true);
const pagination = ref(null);
const selectedOpportunity = ref(null);
const loading = ref(false);
const loadingDetail = ref(false);
const isNavigating = ref(false);
const { soundEnabled, toggleSound } = useClickSound();
const error = ref('');
const detailError = ref('');
const categoriesError = ref('');
const shareMessage = ref('');
const copyFallback = ref(false);
const copyUrl = ref('');
const searchInput = ref(route.value.query.q ?? '');
const savedIds = ref([]);
const trackedIds = ref([]);
const savePendingId = ref(null);
const notice = ref(null);
let requestNumber = 0;
let detailRequestNumber = 0;
let noticeTimer = null;
let navigationTimer = null;

const pageBusy = computed(() => isNavigating.value || loading.value || loadingDetail.value);

const userInitials = computed(() => (currentUser.value?.name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('') || 'OF');

const isDetailPage = computed(() => route.value.name === 'opportunity');
const isNotFoundPage = computed(() => route.value.name === 'not-found');
const isAuthPage = computed(() => ['register', 'login', 'verify-email', 'not-found'].includes(route.value.name));

const pageTitle = computed(() => {
    if (isDetailPage.value) return selectedOpportunity.value?.title ?? 'Opportunity details';
    if (route.value.name === 'register') return 'Create an account';
    if (route.value.name === 'login') return 'Sign in';
    if (route.value.name === 'verify-email') return 'Verify your email';
    if (route.value.name === 'profile') return 'Your profile';
    if (route.value.name === 'saved') return 'Saved opportunities';
    if (route.value.name === 'tracker') return 'Application tracker';
    if (route.value.name === 'categories') return 'Opportunity categories';
    if (route.value.name === 'how-it-works') return 'How OyaFind works';
    if (route.value.name === 'category') return `${humanize(route.value.slug)} opportunities`;
    if (route.value.name === 'search') return route.value.query.q ? `Results for “${route.value.query.q}”` : 'Search opportunities';
    return 'Open opportunities';
});

const returnPath = ref(`${window.location.pathname}${window.location.search}`);
const loginHref = computed(() => `/login?returnTo=${encodeURIComponent(returnPath.value)}`);
const registerHref = computed(() => `/register?returnTo=${encodeURIComponent(returnPath.value)}`);

const showBottomNav = computed(() => !isAuthPage.value);

const bottomNavItems = computed(() => [
    { label: 'Explore', href: '/', icon: 'compass', active: ['home', 'search', 'category', 'categories', 'opportunity', 'how-it-works'].includes(route.value.name) },
    { label: 'Saved', href: '/saved', icon: 'bookmark', active: route.value.name === 'saved' },
    { label: 'Tracker', href: '/tracker', icon: 'chart', active: route.value.name === 'tracker' },
    currentUser.value
        ? { label: 'Profile', href: '/profile', icon: 'users', active: route.value.name === 'profile' }
        : { label: 'Sign in', href: '/login', icon: 'users', active: ['login', 'register'].includes(route.value.name) },
]);

async function checkAuth() {
    authReady.value = false;
    try {
        currentUser.value = await getMyProfile();
        await loadAccountLists();
    } catch {
        currentUser.value = null;
        savedIds.value = [];
        trackedIds.value = [];
    } finally {
        authReady.value = true;
    }
}

async function loadAccountLists() {
    if (!currentUser.value) {
        savedIds.value = [];
        trackedIds.value = [];
        return;
    }
    const [savedResult, trackerResult] = await Promise.allSettled([
        getSavedOpportunities(),
        getMyApplications(),
    ]);
    savedIds.value = savedResult.status === 'fulfilled'
        ? savedResult.value.map((item) => item.opportunity_id)
        : [];
    trackedIds.value = trackerResult.status === 'fulfilled'
        ? trackerResult.value.map((item) => item.opportunity_id)
        : [];
}

function showNotice(tone, message, linkHref = null, linkLabel = null) {
    notice.value = { tone, message, linkHref, linkLabel };
    window.clearTimeout(noticeTimer);
    noticeTimer = window.setTimeout(() => {
        notice.value = null;
    }, 7000);
}

async function handleAuthenticated(targetPath) {
    await checkAuth();
    navigate(targetPath || '/profile', { replace: true });
}

function handleSignedOut() {
    currentUser.value = null;
    savedIds.value = [];
    trackedIds.value = [];
    navigate('/login', { replace: true });
}

async function handleHeaderSignOut() {
    try {
        await signOut();
    } catch {
        // Clear local state regardless of the response.
    }
    currentUser.value = null;
    savedIds.value = [];
    trackedIds.value = [];
    navigate('/login', { replace: true });
}

function humanize(value = '') {
    return value.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function beginNavigation() {
    window.clearTimeout(navigationTimer);
    isNavigating.value = true;
}

function finishNavigation(delay = 180) {
    window.clearTimeout(navigationTimer);
    navigationTimer = window.setTimeout(() => {
        isNavigating.value = false;
    }, delay);
}

function navigate(path, { replace = false } = {}) {
    beginNavigation();
    const method = replace ? 'replaceState' : 'pushState';
    window.history[method]({}, '', path);
    route.value = parsePublicRoute(window.location);
    returnPath.value = `${window.location.pathname}${window.location.search}`;
    searchInput.value = route.value.query.q ?? '';
    menuOpen.value = false;
    shareMessage.value = '';
    copyFallback.value = false;
    const hash = new URL(path, window.location.origin).hash;
    if (hash) {
        void nextTick(() => {
            document.getElementById(decodeURIComponent(hash.slice(1)))?.scrollIntoView({ behavior: 'smooth' });
        });
    } else {
        window.scrollTo({ top: 0, behavior: 'auto' });
    }
}

function handlePopState() {
    beginNavigation();
    route.value = parsePublicRoute(window.location);
    returnPath.value = `${window.location.pathname}${window.location.search}`;
    searchInput.value = route.value.query.q ?? '';
    menuOpen.value = false;
    shareMessage.value = '';
    copyFallback.value = false;
}

function handleInternalNavigation(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!(event.target instanceof Element)) return;
    const anchor = event.target.closest('a[data-internal-link]');
    if (!anchor || anchor.target === '_blank') return;
    const url = new URL(anchor.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    event.preventDefault();
    const target = `${url.pathname}${url.search}${url.hash}`;
    const preserveLocation = ['/search', '/categories/', '/opportunities/']
        .some((path) => path === url.pathname || url.pathname.startsWith(path));
    navigate(preserveLocation && route.value.query.location && !url.searchParams.has('location')
        ? withLocationFilter(target, route.value.query.location)
        : target);
}

function search() {
    const params = new URLSearchParams();
    const query = searchInput.value.trim();
    const preservedLocation = route.value.query.location;
    const sort = route.value.query.sort;
    if (query) params.set('q', query);
    if (preservedLocation) params.set('location', preservedLocation);
    if (sort) params.set('sort', sort);
    const queryString = params.toString();
    navigate(`/search${queryString ? `?${queryString}` : ''}`);
}

function selectLocation(slug) {
    navigate(withLocationFilter(`${window.location.pathname}${window.location.search}`, slug));
}

async function loadCategories() {
    if (categories.value.length || categoriesError.value) return;
    try {
        categories.value = await getCategories();
        categoriesError.value = '';
    } catch {
        categoriesError.value = 'Categories are temporarily unavailable. You can still browse all opportunities.';
    } finally {
        categoriesLoading.value = false;
    }
}

async function loadFeed() {
    const currentRequest = ++requestNumber;
    loading.value = true;
    error.value = '';
    opportunities.value = [];
    pagination.value = null;
    try {
        const result = await getOpportunities({
            ...route.value.query,
            category: route.value.name === 'category' ? route.value.slug : route.value.query.category,
        });
        if (currentRequest !== requestNumber) return;
        opportunities.value = result.data;
        pagination.value = result.meta;
    } catch (requestError) {
        if (currentRequest !== requestNumber) return;
        error.value = requestError.message || 'We could not load opportunities right now.';
    } finally {
        if (currentRequest === requestNumber) loading.value = false;
    }
}

async function loadDetail(slug) {
    const currentRequest = ++detailRequestNumber;
    selectedOpportunity.value = null;
    detailError.value = '';
    loadingDetail.value = true;
    try {
        selectedOpportunity.value = await getOpportunity(slug);
    } catch {
        if (currentRequest !== detailRequestNumber) return;
        detailError.value = 'This opportunity is no longer available or could not be found. Public listings may be removed when they expire or no longer meet publication requirements.';
    } finally {
        if (currentRequest === detailRequestNumber) loadingDetail.value = false;
    }
}

async function toggleSave(opportunity) {
    const opportunityId = opportunity.id;
    if (!currentUser.value) {
        showNotice('info', 'Sign in to save opportunities and keep a private shortlist.');
        navigate(loginHref.value);
        return;
    }
    if (!currentUser.value.save_eligible) {
        showNotice(
            'warning',
            'Verify the email address or phone number on your account before saving opportunities.',
            '/profile',
            'Open profile',
        );
        return;
    }
    if (savePendingId.value != null) return;

    const wasSaved = savedIds.value.includes(opportunityId);
    savePendingId.value = opportunityId;
    try {
        if (wasSaved) {
            await unsaveOpportunity(opportunityId);
            savedIds.value = savedIds.value.filter((id) => id !== opportunityId);
            showNotice('success', 'Removed from your saved list.');
        } else {
            await saveOpportunity(opportunityId);
            savedIds.value = [...savedIds.value, opportunityId];
            showNotice('success', 'Saved. Open Saved to review it later.', '/saved', 'Open saved');
        }
    } catch (saveError) {
        showNotice('error', saveError.message || 'Could not update your saved list. Try again.');
    } finally {
        savePendingId.value = null;
    }
}

async function handleTrack(opportunity) {
    const opportunityId = opportunity.id;
    if (!currentUser.value) {
        navigate(loginHref.value);
        return;
    }
    if (trackedIds.value.includes(opportunityId)) {
        navigate('/tracker');
        return;
    }
    try {
        await trackApplication({ opportunity_id: opportunityId, status: 'interested' });
        trackedIds.value = [...trackedIds.value, opportunityId];
        showNotice('success', 'Added to your private tracker.', '/tracker', 'Open tracker');
    } catch (trackError) {
        showNotice('error', trackError.message || 'Could not add this opportunity to your tracker.');
    }
}

function markUnsaved(opportunityId) {
    savedIds.value = savedIds.value.filter((id) => id !== opportunityId);
}

function markTracked(opportunityId) {
    if (!trackedIds.value.includes(opportunityId)) {
        trackedIds.value = [...trackedIds.value, opportunityId];
    }
}

function markRemoved() {
    void loadAccountLists();
}

async function copyCurrentPageUrl() {
    const url = canonicalPageUrl(window.location);
    copyUrl.value = url;
    try {
        await navigator.clipboard.writeText(url);
        copyFallback.value = false;
        shareMessage.value = 'Link copied. You can share this public page.';
    } catch {
        copyFallback.value = true;
        shareMessage.value = 'Copy the public link below to share this page.';
    }
}

async function shareCurrentPage() {
    const url = canonicalPageUrl(window.location);
    copyUrl.value = url;
    if (typeof navigator.share === 'function') {
        try {
            await sharePageUrl(navigator, url, selectedOpportunity.value?.title ?? 'OyaFind opportunity');
            shareMessage.value = 'Share sheet opened.';
            copyFallback.value = false;
            return;
        } catch (shareError) {
            if (shareError?.name === 'AbortError') return;
        }
    }

    await copyCurrentPageUrl();
}

watch(route, async (currentRoute) => {
    beginNavigation();
    if (['register', 'login', 'profile', 'categories', 'how-it-works', 'verify-email', 'saved', 'tracker'].includes(currentRoute.name)) {
        requestNumber += 1;
        detailRequestNumber += 1;
        loading.value = false;
        loadingDetail.value = false;
        selectedOpportunity.value = null;
        if (currentRoute.name === 'categories') void loadCategories();
        finishNavigation(420);
        return;
    }
    if (currentRoute.name === 'opportunity') {
        requestNumber += 1;
        loading.value = false;
        await loadDetail(currentRoute.slug);
        finishNavigation(220);
        return;
    }
    if (currentRoute.name === 'not-found') {
        requestNumber += 1;
        selectedOpportunity.value = null;
        loading.value = false;
        error.value = '';
        finishNavigation(420);
        return;
    }
    selectedOpportunity.value = null;
    void loadCategories();
    await loadFeed();
    finishNavigation(180);
}, { immediate: true });

onMounted(() => {
    window.addEventListener('popstate', handlePopState);
    document.addEventListener('click', handleInternalNavigation);
    void checkAuth();
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
    document.removeEventListener('click', handleInternalNavigation);
    window.clearTimeout(noticeTimer);
    window.clearTimeout(navigationTimer);
});
</script>

<template>
    <div class="site-shell" :class="{ 'has-bottom-nav': showBottomNav }">
        <a class="skip-link" href="#main-content">Skip to content</a>

        <div class="page-loader" :class="{ 'is-active': pageBusy }" role="status" aria-live="polite">
            <span class="page-loader-track" aria-hidden="true"><span class="page-loader-sweep"></span></span>
            <span class="visually-hidden">Loading page…</span>
        </div>

        <Transition name="route-loader">
            <div v-if="isNavigating" class="route-loader" role="status">
                <UiIcon class="loading-spinner" name="loader" :size="15" />
                <span>Loading page</span>
            </div>
        </Transition>

        <header class="site-header">
            <div class="header-inner">
                <a class="brand" href="/" data-internal-link aria-label="OyaFind home">
                    <span class="brand-mark"><img :src="'/brand/oyafind-mark.svg'" alt=""></span>
                    <span class="brand-name">Oya<span>Find</span></span>
                </a>
                <a class="pilot-tag" href="/how-it-works" data-internal-link>
                    <span class="pilot-tag-dot" aria-hidden="true"></span>
                    <span class="pilot-tag-long">Nigeria</span>
                    <span class="pilot-tag-short" aria-hidden="true">Nigeria</span>
                </a>

                <button
                    class="menu-toggle"
                    type="button"
                    :aria-expanded="menuOpen"
                    aria-controls="site-navigation"
                    :aria-label="menuOpen ? 'Close navigation' : 'Open navigation'"
                    @click="menuOpen = !menuOpen"
                ><span class="menu-toggle-lines" aria-hidden="true"></span></button>

                <div id="site-navigation" class="header-panel" :class="{ 'is-open': menuOpen }">
                    <nav class="main-nav" aria-label="Main navigation">
                        <a href="/" data-internal-link :aria-current="['home', 'search', 'category'].includes(route.name) ? 'page' : undefined">Explore</a>
                        <a v-if="currentUser" href="/saved" data-internal-link :aria-current="route.name === 'saved' ? 'page' : undefined">Saved</a>
                        <a v-if="currentUser" href="/tracker" data-internal-link :aria-current="route.name === 'tracker' ? 'page' : undefined">Tracker</a>
                        <a href="/categories" data-internal-link :aria-current="['categories'].includes(route.name) ? 'page' : undefined">Categories</a>
                        <a href="/how-it-works" data-internal-link :aria-current="route.name === 'how-it-works' ? 'page' : undefined">How it works</a>
                    </nav>
                    <div class="header-actions">
                        <template v-if="currentUser">
                            <a class="user-nav-pill" href="/profile" data-internal-link :title="`Signed in as ${currentUser.name || 'Account'}`">
                                <span class="user-avatar-mini" aria-hidden="true">{{ userInitials }}</span>
                                <span class="user-nav-name">{{ currentUser.name || 'Account' }}</span>
                            </a>
                            <button class="signout-header-btn" type="button" @click="handleHeaderSignOut">
                                Sign out
                            </button>
                        </template>
                        <template v-else>
                            <a class="header-signin" href="/login" data-internal-link>Sign in</a>
                            <a class="button button-primary button-sm" href="/register" data-internal-link>Create account</a>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="page-content" :aria-busy="pageBusy">
            <template v-if="isNotFoundPage">
                <section class="empty-panel">
                    <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="compass" :size="22" /></span>
                    <h1>We couldn’t find that page</h1>
                    <p>Try exploring current public opportunities instead.</p>
                    <a class="button button-primary" href="/" data-internal-link>Explore opportunities</a>
                </section>
            </template>
            <CategoriesPage
                v-else-if="route.name === 'categories'"
                :categories="categories"
                @navigate="navigate"
            />
            <HowItWorksPage v-else-if="route.name === 'how-it-works'" />
            <RegisterPage v-else-if="route.name === 'register'" @authenticated="handleAuthenticated($event)" />
            <LoginPage v-else-if="route.name === 'login'" @authenticated="handleAuthenticated($event)" />
            <VerifyEmailPage
                v-else-if="route.name === 'verify-email'"
                :user-id="route.userId"
                :hash="route.hash"
                :verification-query="route.query"
            />
            <ProfilePage v-else-if="route.name === 'profile'" :initial-profile="currentUser" @signed-out="handleSignedOut" />

            <template v-else-if="route.name === 'saved'">
                <SavedPage
                    v-if="currentUser"
                    :current-user="currentUser"
                    @unsaved="markUnsaved"
                    @tracked="markTracked"
                />
                <div v-else-if="!authReady" class="workspace-page">
                    <p class="state-panel state-loading" role="status" aria-live="polite">
                        <UiIcon class="loading-spinner" name="loader" :size="19" />
                        Checking your account…
                    </p>
                </div>
                <section v-else class="auth-gate">
                    <p class="feed-kicker">Your shortlist</p>
                    <h1>Sign in to see your saved opportunities</h1>
                    <p>Saved listings are private to your account. Sign in, or create an account to start a shortlist.</p>
                    <div class="auth-gate-actions">
                        <a class="button button-primary" :href="loginHref" data-internal-link>Sign in</a>
                        <a class="button button-secondary" :href="registerHref" data-internal-link>Create an account</a>
                    </div>
                    <a class="text-link" href="/" data-internal-link>Keep browsing without an account</a>
                </section>
            </template>

            <template v-else-if="route.name === 'tracker'">
                <MyOpportunitiesPage
                    v-if="currentUser"
                    :current-user="currentUser"
                    @removed="markRemoved"
                />
                <div v-else-if="!authReady" class="workspace-page">
                    <p class="state-panel state-loading" role="status" aria-live="polite">
                        <UiIcon class="loading-spinner" name="loader" :size="19" />
                        Checking your account…
                    </p>
                </div>
                <section v-else class="auth-gate">
                    <p class="feed-kicker">Private tracker</p>
                    <h1>Sign in to open your application tracker</h1>
                    <p>Your stages, dates and notes are private to your account. OyaFind never sends them to an employer.</p>
                    <div class="auth-gate-actions">
                        <a class="button button-primary" :href="loginHref" data-internal-link>Sign in</a>
                        <a class="button button-secondary" :href="registerHref" data-internal-link>Create an account</a>
                    </div>
                    <a class="text-link" href="/" data-internal-link>Keep browsing without an account</a>
                </section>
            </template>

            <template v-else-if="isDetailPage">
                <OpportunityDetailPage
                    :opportunity="selectedOpportunity"
                    :loading="loadingDetail"
                    :error="detailError"
                    :share-message="shareMessage"
                    :copy-fallback="copyFallback"
                    :copy-url="copyUrl"
                    :safe-external-url="safeExternalUrl"
                    :saved="selectedOpportunity ? savedIds.includes(selectedOpportunity.id) : false"
                    :save-pending="selectedOpportunity ? savePendingId === selectedOpportunity.id : false"
                    :tracked="selectedOpportunity ? trackedIds.includes(selectedOpportunity.id) : false"
                    @back="navigate('/search')"
                    @share="shareCurrentPage"
                    @copy="copyCurrentPageUrl"
                    @save="toggleSave"
                    @track="handleTrack"
                />
            </template>
            <template v-else>
                <DiscoveryPage
                    :title="pageTitle"
                    :query="searchInput"
                    :categories="categories"
                    :categories-loading="categoriesLoading"
                    :categories-error="categoriesError"
                    :opportunities="opportunities"
                    :loading="loading"
                    :error="error"
                    :pagination="pagination"
                    :location-filtered="Boolean(route.query.location)"
                    :selected-location="route.query.location ?? ''"
                    :saved-ids="savedIds"
                    :save-pending-id="savePendingId"
                    :is-authenticated="Boolean(currentUser)"
                    @update:query="searchInput = $event"
                    @search="search"
                    @navigate="navigate"
                    @location="selectLocation"
                    @save="toggleSave"
                />
            </template>
        </main>

        <footer class="site-footer">
            <div class="footer-brand-block">
                <p class="footer-brand">Oya<span>Find</span></p>
                <p>Real opportunities. Closer to you.</p>
                <p class="footer-pilot">Across Nigeria · 36 states + FCT</p>
            </div>
            <div class="footer-copy">
                <p>OyaFind shares public listings. It does not submit applications, decide outcomes, or guarantee provider safety, selection or payment.</p>
                <p class="footer-links">
                    <a href="/how-it-works" data-internal-link>How it works</a>
                    <a href="/categories" data-internal-link>Categories</a>
                    <button class="sound-toggle" type="button" :aria-pressed="soundEnabled" @click="toggleSound">
                        <UiIcon :name="soundEnabled ? 'volume' : 'volume-off'" :size="15" />
                        {{ soundEnabled ? 'Sound on' : 'Sound off' }}
                    </button>
                </p>
            </div>
        </footer>

        <nav v-if="showBottomNav" class="bottom-nav" aria-label="Primary">
            <a
                v-for="item in bottomNavItems"
                :key="item.label"
                class="bottom-nav-link"
                :class="{ 'is-active': item.active }"
                :href="item.href"
                data-internal-link
                :aria-current="item.active ? 'page' : undefined"
            >
                <span class="bottom-nav-icon"><UiIcon :name="item.icon" :size="20" /><span class="bottom-nav-dot" aria-hidden="true"></span></span>
                <span class="bottom-nav-label">{{ item.label }}</span>
            </a>
        </nav>

        <div v-if="notice" class="notice-toast" :class="`is-${notice.tone}`" role="status" aria-live="polite">
            <span class="notice-toast-message">{{ notice.message }}</span>
            <a v-if="notice.linkHref" class="notice-toast-link" :href="notice.linkHref" data-internal-link @click="notice = null">
                {{ notice.linkLabel }}
            </a>
            <button class="notice-toast-close" type="button" aria-label="Dismiss" @click="notice = null">
                <UiIcon name="close" :size="15" />
            </button>
        </div>
    </div>
</template>
