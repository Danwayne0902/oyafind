<script setup>
import { computed } from 'vue';
import OpportunityCard from '../components/OpportunityCard.vue';
import LocationPicker from '../components/LocationPicker.vue';
import UiIcon from '../components/UiIcon.vue';

const props = defineProps({
    title: { type: String, required: true },
    query: { type: String, default: '' },
    categories: { type: Array, default: () => [] },
    categoriesLoading: { type: Boolean, default: true },
    categoriesError: { type: String, default: '' },
    opportunities: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    pagination: { type: Object, default: null },
    locationFiltered: { type: Boolean, default: false },
    selectedLocation: { type: String, default: '' },
    savedIds: { type: Array, default: () => [] },
    savePendingId: { type: Number, default: null },
    isAuthenticated: { type: Boolean, default: false },
});

const emit = defineEmits(['update:query', 'search', 'navigate', 'location', 'save', 'sort']);

const FALLBACK_CATEGORIES = [
    { name: 'Jobs', slug: 'jobs', icon: 'briefcase' },
    { name: 'Scholarships', slug: 'scholarships', icon: 'graduation-cap' },
    { name: 'Internships', slug: 'internships', icon: 'users' },
    { name: 'Grants', slug: 'grants', icon: 'coins' },
    { name: 'Training', slug: 'training', icon: 'book' },
    { name: 'Business Support', slug: 'business-support', icon: 'chart' },
    { name: 'Gigs', slug: 'gigs', icon: 'bolt' },
    { name: 'Tenders', slug: 'tenders', icon: 'file' },
];

const categoryChips = computed(() => {
    const active = new Map(props.categories.map((category) => [category.slug, category]));
    return FALLBACK_CATEGORIES.map((fallback) => ({
        name: active.get(fallback.slug)?.name ?? fallback.name,
        slug: fallback.slug,
        icon: fallback.icon,
    }));
});

const hasFilters = computed(() => Boolean(
    props.query.trim() || props.selectedLocation || props.locationFiltered,
));

const sortValue = computed(() => {
    if (typeof window === 'undefined') return 'newest';
    return new URLSearchParams(window.location.search).get('sort') ?? 'newest';
});

function retry() {
    emit('navigate', `${window.location.pathname}${window.location.search}`);
}

function changePage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    emit('navigate', `${window.location.pathname}?${params.toString()}`);
}

function clearFilters() {
    emit('navigate', '/');
}

function categoryHref(slug) {
    const base = `/categories/${encodeURIComponent(slug)}`;
    return props.selectedLocation ? `${base}?location=${encodeURIComponent(props.selectedLocation)}` : base;
}

function changeSort(event) {
    const params = new URLSearchParams(window.location.search);
    if (event.target.value === 'newest') params.delete('sort');
    else params.set('sort', event.target.value);
    params.delete('page');
    const queryString = params.toString();
    emit('navigate', `${window.location.pathname}${queryString ? `?${queryString}` : ''}`);
}
</script>

<template>
    <section class="explore-page">
        <header class="masthead">
            <div class="masthead-inner">
                <div class="masthead-lead">
                    <p class="pilot-eyebrow">
                        <span>Nigeria-wide</span>
                        <span class="pilot-sep" aria-hidden="true"></span>
                        <span class="pilot-sub">36 states + FCT</span>
                    </p>
                    <h1>
                        Find what’s next,<br>
                        <span class="masthead-highlight">closer to home.</span>
                    </h1>
                    <p class="masthead-copy">
                        Jobs, scholarships, grants, training and gigs for people building a living anywhere in Nigeria.
                        Every public listing records when its source and details were checked.
                    </p>

                    <form class="explore-search" role="search" @submit.prevent="emit('search')">
                        <UiIcon class="explore-search-icon" name="search" :size="20" />
                        <label class="visually-hidden" for="opportunity-search">Search opportunities</label>
                        <input
                            id="opportunity-search"
                            :value="query"
                            type="search"
                            maxlength="100"
                            placeholder="Role, organisation or state"
                            autocomplete="off"
                            @input="emit('update:query', $event.target.value)"
                        >
                        <button class="button button-primary" type="submit">
                            Search <UiIcon name="arrow" :size="17" />
                        </button>
                    </form>

                    <div class="filter-row">
                        <LocationPicker :selected-slug="selectedLocation" @select="emit('location', $event)" />
                        <span class="filter-row-label">Browse</span>
                        <nav class="category-chips" aria-label="Browse by category">
                            <a
                                v-for="chip in categoryChips"
                                :key="chip.slug"
                                class="category-chip"
                                :href="categoryHref(chip.slug)"
                                data-internal-link
                            >
                                <UiIcon :name="chip.icon" :size="15" />
                                {{ chip.name }}
                            </a>
                        </nav>
                    </div>
                </div>

                <aside class="masthead-cards" aria-label="What OyaFind does">
                    <article class="masthead-card">
                        <span class="masthead-card-icon"><UiIcon name="shield-check" :size="18" /></span>
                        <p class="masthead-card-kicker">Checked before listing</p>
                        <h2>Source and details checked, with the date shown.</h2>
                        <p>Every public listing records when OyaFind last checked its source. A check is not an endorsement or a guarantee.</p>
                        <a class="masthead-card-link" href="/how-it-works" data-internal-link>
                            See what a check covers <span aria-hidden="true">→</span>
                        </a>
                    </article>

                    <article class="masthead-card is-ink">
                        <div class="masthead-card-top">
                            <span class="masthead-card-icon"><UiIcon name="pin" :size="18" /></span>
                            <span class="masthead-card-stat"><strong>36</strong><span>states + FCT</span></span>
                        </div>
                        <p class="masthead-card-kicker">One national ledger</p>
                        <h2>Browse your state, your LGA, or the whole country.</h2>
                        <p>Choose a reviewed area without sharing GPS or an exact address. All-area browsing stays available.</p>
                        <a class="masthead-card-link" href="/categories" data-internal-link>
                            Browse by category <span aria-hidden="true">→</span>
                        </a>
                    </article>
                </aside>

                <p v-if="categoriesError" class="masthead-note" role="status">{{ categoriesError }}</p>
            </div>
        </header>

        <div class="feed-layout">
            <div class="feed-main">
                <div class="feed-head">
                    <div>
                        <p class="feed-kicker">{{ hasFilters ? 'Filtered view' : 'Live listings' }}</p>
                        <h2 class="feed-title">{{ title }}</h2>
                    </div>
                    <div class="feed-head-tools">
                        <p v-if="pagination && !loading" class="feed-count">
                            <strong>{{ pagination.total }}</strong>
                            {{ pagination.total === 1 ? 'opportunity' : 'opportunities' }}
                        </p>
                        <label class="sort-control">
                            <span>Sort</span>
                            <select :value="sortValue" @change="changeSort">
                                <option value="newest">Newest</option>
                                <option value="deadline">Closing soon</option>
                                <option value="title">A–Z</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div v-if="hasFilters" class="active-filters">
                    <span v-if="query" class="active-filter">Search: “{{ query }}”</span>
                    <span v-if="selectedLocation" class="active-filter">Area selected</span>
                    <button class="active-filter-clear" type="button" @click="clearFilters">
                        Clear filters
                    </button>
                </div>

                <div v-if="loading" class="feed-skeleton" role="status" aria-live="polite">
                    <span class="visually-hidden">Loading public opportunities…</span>
                    <div v-for="n in 3" :key="n" class="skeleton-card" aria-hidden="true">
                        <span class="skeleton-line is-short"></span>
                        <span class="skeleton-line is-title"></span>
                        <span class="skeleton-line"></span>
                        <span class="skeleton-line is-medium"></span>
                    </div>
                </div>

                <div v-else-if="error" class="empty-panel is-error" role="alert">
                    <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="shield-check" :size="22" /></span>
                    <h2>We couldn’t load opportunities</h2>
                    <p>{{ error }}</p>
                    <button class="button button-secondary" type="button" @click="retry">Try again</button>
                </div>

                <div v-else-if="opportunities.length" class="feed-list" aria-live="polite">
                    <OpportunityCard
                        v-for="opportunity in opportunities"
                        :key="opportunity.id"
                        :opportunity="opportunity"
                        :saved="savedIds.includes(opportunity.id)"
                        :pending="savePendingId === opportunity.id"
                        @save="emit('save', $event)"
                    />
                </div>

                <div v-else class="empty-panel">
                    <span class="empty-panel-icon" aria-hidden="true"><UiIcon name="search" :size="22" /></span>
                    <h2>{{ hasFilters ? 'Nothing matches this view yet' : 'No live listings right now' }}</h2>
                    <p v-if="hasFilters">
                        Try a broader area, a different category, or clear the search. The feed is still small while listings are checked.
                    </p>
                    <p v-else>
                        Listings appear here only after OyaFind has reviewed the source and details.
                        We don’t pad the feed with examples, so an empty board is an honest one.
                    </p>
                    <div class="empty-panel-actions">
                        <button v-if="hasFilters" class="button button-primary" type="button" @click="clearFilters">
                            Show all areas
                        </button>
                        <a class="button button-secondary" href="/how-it-works" data-internal-link>
                            How OyaFind works
                        </a>
                    </div>
                </div>

                <div v-if="pagination && pagination.current_page < pagination.last_page && !loading && !error" class="pagination">
                    <button class="button button-secondary" type="button" @click="changePage(pagination.current_page + 1)">
                        Next page <UiIcon name="arrow" :size="16" />
                    </button>
                </div>
            </div>

            <aside class="feed-rail" aria-label="About OyaFind listings">
                <section class="rail-card rail-card-check">
                    <p class="rail-kicker">What a check means</p>
                    <h2>Checked before it is listed.</h2>
                    <p>
                        Every public listing has had its source and details checked by OyaFind.
                        A check is not an endorsement, or a guarantee of safety, selection or payment.
                    </p>
                </section>

                <section class="rail-card">
                    <p class="rail-kicker">The 30-day rule</p>
                    <p>
                        Listings without a deadline are rechecked every 30 days. If that check is overdue,
                        the listing is hidden until it is confirmed again.
                    </p>
                </section>

                <section class="rail-card">
                    <p class="rail-kicker">Saving is private</p>
                    <p>
                        Sign in to keep a shortlist and track your own application progress.
                        Your notes belong to you, and tracking is user-reported.
                    </p>
                    <a v-if="!isAuthenticated" class="rail-link" href="/login" data-internal-link>
                        Sign in or create an account <span aria-hidden="true">→</span>
                    </a>
                    <a v-else class="rail-link" href="/saved" data-internal-link>
                        Open your saved list <span aria-hidden="true">→</span>
                    </a>
                </section>

                <a class="rail-guide-link" href="/how-it-works" data-internal-link>
                    Read how verification, areas and tracking work <span aria-hidden="true">→</span>
                </a>
            </aside>
        </div>

        <section class="how-band" aria-label="How OyaFind works">
            <div class="how-band-head">
                <p class="feed-kicker">Three things to know</p>
                <h2>Built for a phone, a tight budget and a short walk to the truth.</h2>
            </div>
            <div class="how-band-steps">
                <article>
                    <span class="how-step-num">01</span>
                    <h3>Browse without an account</h3>
                    <p>Explore public listings, categories and areas before you decide to sign in.</p>
                </article>
                <article>
                    <span class="how-step-num">02</span>
                    <h3>Filter by reviewed areas</h3>
                    <p>Coarse, human-reviewed locations. No GPS, no maps, no exact address.</p>
                </article>
                <article>
                    <span class="how-step-num">03</span>
                    <h3>Apply at the source</h3>
                    <p>OyaFind takes you to the provider’s own channel. It does not submit applications.</p>
                </article>
            </div>
        </section>

        <aside class="trust-note" aria-label="About OyaFind listings">
            <span class="trust-icon" aria-hidden="true"><UiIcon name="shield-check" :size="20" /></span>
            <div>
                <h2>Clear information, careful checks</h2>
                <p>
                    OyaFind displays public listings after staff review and a source/details check.
                    A check is not a guarantee of provider safety, selection or payment.
                    Apply directly through the opportunity provider.
                </p>
            </div>
        </aside>
    </section>
</template>
