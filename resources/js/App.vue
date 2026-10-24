<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import DiscoveryPage from './pages/DiscoveryPage.vue';
import OpportunityDetailPage from './pages/OpportunityDetailPage.vue';
import RegisterPage from './pages/RegisterPage.vue';
import LoginPage from './pages/LoginPage.vue';
import VerifyEmailPage from './pages/VerifyEmailPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import CategoriesPage from './pages/CategoriesPage.vue';
import HowItWorksPage from './pages/HowItWorksPage.vue';
import UiIcon from './components/UiIcon.vue';
import { getCategories, getOpportunity, getOpportunities } from './api/publicDiscovery.js';
import { getMyProfile, signOut } from './api/account.js';
import { canonicalPageUrl, parsePublicRoute, safeExternalUrl, sharePageUrl, withLocationFilter } from './publicRoutes.js';

const route = ref(parsePublicRoute(window.location));
const menuOpen = ref(false);
const currentUser = ref(null);
const opportunities = ref([]);
const categories = ref([]);
const categoriesLoading = ref(true);
const pagination = ref(null);
const selectedOpportunity = ref(null);
const loading = ref(false);
const loadingDetail = ref(false);
const error = ref('');
const detailError = ref('');
const categoriesError = ref('');
const shareMessage = ref('');
const copyFallback = ref(false);
const copyUrl = ref('');
const searchInput = ref(route.value.query.q ?? '');
let requestNumber = 0;
let detailRequestNumber = 0;

const userInitials = computed(() => (currentUser.value?.name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('') || 'OF');

const isDetailPage = computed(() => route.value.name === 'opportunity');
const isNotFoundPage = computed(() => route.value.name === 'not-found');
const pageTitle = computed(() => {
    if (isDetailPage.value) return selectedOpportunity.value?.title ?? 'Opportunity details';
    if (route.value.name === 'register') return 'Create an account';
    if (route.value.name === 'login') return 'Sign in';
    if (route.value.name === 'verify-email') return 'Verify your email';
    if (route.value.name === 'profile') return 'Your profile';
    if (route.value.name === 'categories') return 'Opportunity categories';
    if (route.value.name === 'how-it-works') return 'How OyaFind works';
    if (route.value.name === 'category') return `${humanize(route.value.slug)} opportunities`;
    return route.value.query.q ? `Search results for “${route.value.query.q}”` : 'Explore opportunities';
});

async function checkAuth() {
    try {
        currentUser.value = await getMyProfile();
    } catch {
        currentUser.value = null;
    }
}

async function handleAuthenticated(targetPath) {
    await checkAuth();
    navigate(targetPath, { replace: true });
}

function handleSignedOut() {
    currentUser.value = null;
    navigate('/login', { replace: true });
}

async function handleHeaderSignOut() {
    try {
        await signOut();
    } catch {
        // Continue clearing local state regardless
    }
    currentUser.value = null;
    navigate('/login', { replace: true });
}

function humanize(value = '') {
    return value.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function navigate(path, { replace = false } = {}) {
    const method = replace ? 'replaceState' : 'pushState';
    window.history[method]({}, '', path);
    route.value = parsePublicRoute(window.location);
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
    route.value = parsePublicRoute(window.location);
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

function categoryPath(slug) {
    return `/categories/${encodeURIComponent(slug)}`;
}

function selectLocation(slug) {
    navigate(withLocationFilter(`${window.location.pathname}${window.location.search}`, slug));
}

function opportunityPath(slug) {
    return `/opportunities/${encodeURIComponent(slug)}`;
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
    if (['register', 'login', 'profile', 'categories', 'how-it-works'].includes(currentRoute.name)) {
        requestNumber += 1;
        detailRequestNumber += 1;
        loading.value = false;
        loadingDetail.value = false;
        return;
    }
    if (currentRoute.name === 'opportunity') {
        requestNumber += 1;
        loading.value = false;
        await loadDetail(currentRoute.slug);
        return;
    }
    if (currentRoute.name === 'not-found') {
        requestNumber += 1;
        selectedOpportunity.value = null;
        loading.value = false;
        error.value = '';
        return;
    }
    selectedOpportunity.value = null;
    void loadCategories();
    await loadFeed();
}, { immediate: true });

onMounted(() => {
    window.addEventListener('popstate', handlePopState);
    document.addEventListener('click', handleInternalNavigation);
    void checkAuth();
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
    document.removeEventListener('click', handleInternalNavigation);
});
</script>

<template>
    <div class="site-shell">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <header class="site-header">
            <div class="header-inner">
                <a class="brand" href="/" data-internal-link aria-label="OyaFind home">
                    <span class="brand-mark"><img :src="'/brand/oyafind-mark.svg'" alt=""></span>
                    <span class="brand-name">Oya<span>Find</span></span>
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
                        <a href="/" data-internal-link :aria-current="['home', 'search'].includes(route.name) ? 'page' : undefined">Explore</a>
                        <a href="/categories" data-internal-link :aria-current="['categories', 'category'].includes(route.name) ? 'page' : undefined">Categories</a>
                        <a href="/how-it-works" data-internal-link :aria-current="route.name === 'how-it-works' ? 'page' : undefined">How it works</a>
                        <a v-if="currentUser" href="/profile" data-internal-link :aria-current="route.name === 'profile' ? 'page' : undefined">Profile</a>
                    </nav>
                    <div class="header-actions">
                        <template v-if="currentUser">
                            <a class="button button-secondary user-nav-pill" href="/profile" data-internal-link :title="`Signed in as ${currentUser.name || 'Account'}`">
                                <span class="user-avatar-mini" aria-hidden="true">{{ userInitials }}</span>
                                <span class="user-nav-name">{{ currentUser.name || 'Account' }}</span>
                            </a>
                            <button class="button button-secondary signout-header-btn" type="button" @click="handleHeaderSignOut">
                                Sign out
                            </button>
                        </template>
                        <template v-else>
                            <a class="button button-secondary" href="/login" data-internal-link>Sign in</a>
                            <a class="button button-primary" href="/register" data-internal-link>Get started</a>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="page-content">
            <template v-if="isNotFoundPage">
                <section class="notice-panel">
                    <p class="eyebrow">Page not found</p>
                    <h1>We couldn’t find that page.</h1>
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
            <template v-else-if="isDetailPage">
                <OpportunityDetailPage
                    :opportunity="selectedOpportunity"
                    :loading="loadingDetail"
                    :error="detailError"
                    :share-message="shareMessage"
                    :copy-fallback="copyFallback"
                    :copy-url="copyUrl"
                    :safe-external-url="safeExternalUrl"
                    @back="navigate('/search')"
                    @share="shareCurrentPage"
                    @copy="copyCurrentPageUrl"
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
                    @update:query="searchInput = $event"
                    @search="search"
                    @navigate="navigate"
                    @category="navigate(categoryPath($event))"
                    @opportunity="navigate(opportunityPath($event))"
                    @location="selectLocation"
                />
            </template>
        </main>

        <footer class="site-footer">
            <div>
                <p class="footer-brand">Oya<span>Find</span></p>
                <p>Real opportunities. Closer to you.</p>
            </div>
            <p>OyaFind shares public listings; it does not submit applications or guarantee outcomes.</p>
        </footer>
    </div>
</template>
