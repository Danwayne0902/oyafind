<script setup>
import { computed, ref } from 'vue';
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
});

const emit = defineEmits(['update:query', 'search', 'navigate', 'category', 'opportunity', 'location']);
const activePreview = ref('');

const categoryDetails = [
    { name: 'Jobs', slug: 'jobs', description: 'Full-time, part-time and remote jobs.', icon: 'briefcase', tone: 'mint' },
    { name: 'Scholarships', slug: 'scholarships', description: 'Funding for your education and future.', icon: 'graduation-cap', tone: 'violet' },
    { name: 'Internships', slug: 'internships', description: 'Gain experience and build your career.', icon: 'users', tone: 'blue' },
    { name: 'Grants', slug: 'grants', description: 'Support for individuals, startups and businesses.', icon: 'coins', tone: 'amber' },
    { name: 'Training', slug: 'training', description: 'Build new skills and increase your value.', icon: 'book', tone: 'violet' },
    { name: 'Business Support', slug: 'business-support', description: 'Resources for your business growth.', icon: 'chart', tone: 'mint' },
    { name: 'Gigs', slug: 'gigs', description: 'Short-term and flexible work opportunities.', icon: 'bolt', tone: 'amber' },
    { name: 'Tenders', slug: 'tenders', description: 'Government and private sector tenders.', icon: 'file', tone: 'blue' },
];

const categoryCards = computed(() => {
    const apiCategories = new Map(props.categories.map((category) => [category.slug, category]));

    return categoryDetails.map((category) => ({
        ...category,
        name: apiCategories.get(category.slug)?.name ?? category.name,
    }));
});

function retry() {
    emit('navigate', `${window.location.pathname}${window.location.search}`);
}

function changePage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    emit('navigate', `${window.location.pathname}?${params.toString()}`);
}

function searchSuggestion(value) {
    emit('update:query', value);
    emit('search');
}
</script>

<template>
    <section class="discovery">
        <section class="hero" aria-labelledby="hero-heading">
            <div class="hero-content">
                <p class="hero-label"><UiIcon name="compass" :size="15" /> Your next opportunity starts here</p>
                <h1 id="hero-heading">Good opportunities.<br><span class="hero-highlight">Within your reach.</span></h1>
                <p class="hero-copy">Discover jobs, scholarships, grants, training and gigs across Nigeria — all in one place.</p>
                <form class="search-form" role="search" @submit.prevent="emit('search')">
                    <label class="visually-hidden" for="opportunity-search">Search opportunities</label>
                    <UiIcon class="search-icon" name="search" :size="21" />
                    <input
                        id="opportunity-search"
                        :value="query"
                        type="search"
                        maxlength="100"
                        placeholder="Search jobs, scholarships, grants..."
                        autocomplete="off"
                        @input="emit('update:query', $event.target.value)"
                    >
                    <button class="button button-primary" type="submit">
                        Search <UiIcon name="arrow" :size="18" />
                    </button>
                </form>
                <div class="popular-searches" aria-label="Popular searches">
                    <span class="popular-label">Popular searches:</span>
                    <button type="button" @click="searchSuggestion('remote jobs')">
                        <UiIcon name="search" :size="14" /> Remote jobs
                    </button>
                    <button type="button" @click="searchSuggestion('scholarships')">
                        <UiIcon name="search" :size="14" /> Scholarships
                    </button>
                    <button type="button" @click="searchSuggestion('internships')">
                        <UiIcon name="search" :size="14" /> Internships
                    </button>
                </div>
            </div>

            <div class="hero-visual" aria-label="Illustrative preview of opportunity cards, not live listings">
                <div class="visual-orbit" aria-hidden="true"></div>
                <div class="visual-dots" aria-hidden="true"></div>
                <svg class="visual-curve" viewBox="0 0 420 280" fill="none" aria-hidden="true">
                    <path d="M28 174c48-97 120-124 193-89 51 24 50 72 99 83 32 7 55-6 77-28" />
                </svg>
                <span class="visual-lime-dot" aria-hidden="true"></span>
                <span class="visual-compass" aria-hidden="true"><UiIcon name="compass" :size="44" /></span>
                <button
                    class="preview-card preview-card-one"
                    :class="{ 'is-active': activePreview === 'graduate' }"
                    type="button"
                    :aria-pressed="activePreview === 'graduate'"
                    @click="activePreview = 'graduate'"
                >
                    <span class="preview-card-content">
                        <span class="preview-icon preview-icon-mint"><UiIcon name="briefcase" :size="22" /></span>
                        <span class="preview-copy">
                            <span class="preview-title" role="heading" aria-level="2">Graduate Trainee</span>
                            <span class="preview-location"><UiIcon name="pin" :size="13" /> Ibadan, Oyo State</span>
                            <span class="preview-status preview-status-teal">Example: source checked</span>
                        </span>
                        <UiIcon class="preview-arrow" name="arrow" :size="18" />
                    </span>
                </button>

                <button
                    class="preview-card preview-card-two"
                    :class="{ 'is-active': activePreview === 'scholarship' }"
                    type="button"
                    :aria-pressed="activePreview === 'scholarship'"
                    @click="activePreview = 'scholarship'"
                >
                    <span class="preview-card-content">
                        <span class="preview-icon preview-icon-violet"><UiIcon name="graduation-cap" :size="22" /></span>
                        <span class="preview-copy">
                            <span class="preview-title" role="heading" aria-level="2">Scholarship Programme</span>
                            <span class="preview-location"><UiIcon name="pin" :size="13" /> Ibadan, Oyo State</span>
                            <span class="preview-status preview-status-lime">Example: applications open</span>
                        </span>
                        <UiIcon class="preview-arrow" name="arrow" :size="18" />
                    </span>
                </button>
            </div>
        </section>

        <section id="how-it-works" class="benefit-strip-wrap" aria-label="Why browse OyaFind">
            <div class="benefit-strip">
                <div class="benefit-item">
                    <span class="benefit-icon"><UiIcon name="users" :size="22" /></span>
                    <span><strong>Browse without an account</strong><small>Explore opportunities freely.</small></span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon"><UiIcon name="shield-check" :size="22" /></span>
                    <span><strong>Source links included</strong><small>Go directly to the original source.</small></span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon"><UiIcon name="calendar" :size="22" /></span>
                    <span><strong>Clear deadlines</strong><small>Never miss an opportunity.</small></span>
                </div>
            </div>
            <div class="benefit-cta-row">
                <a class="text-link" href="/how-it-works" data-internal-link>
                    Learn how verification, locations &amp; tracking work &rarr;
                </a>
            </div>
        </section>

        <section id="categories" class="category-section" aria-labelledby="category-heading">
            <div class="section-heading">
                <div>
                    <h2 id="category-heading">Explore by category</h2>
                    <p>Find the right opportunity for your next move.</p>
                </div>
                <a class="text-link section-heading-link" href="/categories" data-internal-link>
                    Detailed category guide &rarr;
                </a>
            </div>
            <p v-if="categoriesError" class="category-api-note" role="status">{{ categoriesError }}</p>
            <div class="category-grid" aria-label="Opportunity categories">
                <a
                    v-for="category in categoryCards"
                    :key="category.slug"
                    class="category-card"
                    :href="selectedLocation ? `/categories/${encodeURIComponent(category.slug)}?location=${encodeURIComponent(selectedLocation)}` : `/categories/${encodeURIComponent(category.slug)}`"
                    data-internal-link
                >
                    <span class="category-icon" :class="`tone-${category.tone}`"><UiIcon :name="category.icon" :size="23" /></span>
                    <span class="category-card-copy">
                        <strong>{{ category.name }}</strong>
                        <small>{{ category.description }}</small>
                    </span>
                    <UiIcon class="category-arrow" name="arrow" :size="18" />
                </a>
            </div>
            <p v-if="categoriesLoading && !categories.length" class="category-loading-copy" role="status" aria-live="polite">
                <UiIcon class="loading-spinner" name="loader" :size="16" />
                Loading opportunity categories…
            </p>
        </section>

        <LocationPicker :selected-slug="selectedLocation" @select="emit('location', $event)" />

        <section class="results-section" aria-labelledby="results-heading">
            <div class="results-heading">
                <div>
                    <p class="eyebrow">Discover</p>
                    <h2 id="results-heading">{{ title }}</h2>
                </div>
                <p v-if="pagination && !loading" class="result-count">
                    {{ pagination.total }} {{ pagination.total === 1 ? 'opportunity' : 'opportunities' }}
                </p>
            </div>

            <p v-if="locationFiltered" class="filter-note">
                Showing results for the location filter in this link. Clear the location parameter to browse all areas.
            </p>
            <p v-else class="filter-note">
                Browsing all areas. Location is optional; no location filter is selected.
            </p>

            <p v-if="loading" class="state-panel state-loading" role="status" aria-live="polite">
                <UiIcon class="loading-spinner" name="loader" :size="19" />
                Loading public opportunities…
            </p>
            <div v-else-if="error" class="state-panel state-error" role="alert">
                <h3>We couldn’t load opportunities</h3>
                <p>{{ error }}</p>
                <button class="button button-secondary" type="button" @click="retry">Try again</button>
            </div>
            <div v-else-if="opportunities.length" class="opportunity-list" aria-live="polite">
                <OpportunityCard v-for="opportunity in opportunities" :key="opportunity.id" :opportunity="opportunity" />
            </div>
            <div v-else class="empty-state">
                <span class="empty-icon" aria-hidden="true"><UiIcon name="search" :size="25" /></span>
                <h3>{{ query ? 'No results match this search' : 'No public opportunities to show yet' }}</h3>
                <p>There are no current listings matching this view. Listings appear here only after the required review and source/detail checks. We do not add sample listings to the live feed.</p>
                <a v-if="query" class="text-link" href="/search" data-internal-link>Clear search and browse all</a>
                <a v-else-if="locationFiltered" class="text-link" href="/" data-internal-link>Browse all areas</a>
            </div>

            <div v-if="pagination && pagination.current_page < pagination.last_page && !loading && !error" class="pagination">
                <button class="button button-secondary" type="button" @click="changePage(pagination.current_page + 1)">
                    Next page
                </button>
            </div>
        </section>

        <aside class="trust-note" aria-label="About OyaFind listings">
            <span class="trust-icon" aria-hidden="true"><UiIcon name="shield-check" :size="20" /></span>
            <div>
                <h2>Clear information, careful checks</h2>
                <p>OyaFind displays eligible public listings after review and source/detail checks. A check is not a guarantee of provider safety, selection or payment. Apply directly through the opportunity provider.</p>
            </div>
        </aside>
    </section>
</template>
