<script setup>
import { computed, ref } from 'vue';
import UiIcon from '../components/UiIcon.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['navigate']);

const searchQuery = ref('');

const allCategories = [
    {
        name: 'Jobs',
        slug: 'jobs',
        tag: 'Employment',
        icon: 'briefcase',
        tone: 'mint',
        description: 'Full-time, part-time and contract roles relevant to Ibadan and Oyo State.',
        targetAudience: 'Jobseekers, fresh graduates, skilled workers, and experienced professionals.',
        examples: ['Graduate Trainees', 'Operations Associates', 'Administrative Officers', 'Field Leads', 'Technical Specialists'],
    },
    {
        name: 'Gigs',
        slug: 'gigs',
        tag: 'Flexible & Short-Term',
        icon: 'bolt',
        tone: 'amber',
        description: 'Short-term assignments, freelance projects, technical jobs, and flexible local gigs.',
        targetAudience: 'Freelancers, artisans, students seeking side income, and independent contractors.',
        examples: ['Photography & Media', 'Event Support', 'Delivery & Logistics', 'Graphic Design', 'Artisan Repairs'],
    },
    {
        name: 'Internships',
        slug: 'internships',
        tag: 'Early Career & SIWES',
        icon: 'users',
        tone: 'blue',
        description: 'Pre-graduation industrial training (IT/SIWES), clinical attachments, and graduate internships.',
        targetAudience: 'Undergraduates from universities and polytechnics, NYSC corps members, and recent graduates.',
        examples: ['Software Engineering Interns', 'Finance & Accounting Trainees', 'Lab & Clinical Interns', 'Marketing Associates'],
    },
    {
        name: 'Scholarships',
        slug: 'scholarships',
        tag: 'Educational Support',
        icon: 'graduation-cap',
        tone: 'violet',
        description: 'Tuition grants, academic merit scholarships, community bursaries, and international study funds.',
        targetAudience: 'Secondary school leavers, undergraduate students, polytechnic attendees, and postgraduates.',
        examples: ['STEM Undergraduate Bursaries', 'State Education Board Grants', 'Foundation Fellowships', 'Research Awards'],
    },
    {
        name: 'Training',
        slug: 'training',
        tag: 'Skills & Bootcamps',
        icon: 'book',
        tone: 'violet',
        description: 'Technical bootcamps, vocational skill workshops, professional certifications, and apprenticeships.',
        targetAudience: 'Youth seeking high-demand skills, artisans upgrading their trade, and career switchers.',
        examples: ['Web & Mobile Bootcamps', 'Solar Energy Installation', 'Fashion & Garment Making', 'Financial Literacy'],
    },
    {
        name: 'Grants',
        slug: 'grants',
        tag: 'Non-Repayable Funds',
        icon: 'coins',
        tone: 'amber',
        description: 'Non-equity business grants, research seed capital, and community development funding.',
        targetAudience: 'Early-stage entrepreneurs, SME founders, tech innovators, farmers, and social changemakers.',
        examples: ['Agribusiness Seed Grants', 'Youth Enterprise Capital', 'Women in Business Microgrants', 'Creative Arts Funds'],
    },
    {
        name: 'Business Support',
        slug: 'business-support',
        tag: 'Incubation & Growth',
        icon: 'chart',
        tone: 'mint',
        description: 'Business incubation, co-working access, mentorship cohorts, and SME advisory clinics.',
        targetAudience: 'Startup founders, cooperative societies, and small business owners looking to scale.',
        examples: ['Innovation Hub Cohorts', 'SME Advisory Clinics', 'Export Readiness Programs', 'Investor Pitch Days'],
    },
    {
        name: 'Tenders',
        slug: 'tenders',
        tag: 'Procurement & Contracts',
        icon: 'file',
        tone: 'blue',
        description: 'Formal public notices, corporate procurement bids, and supply contracts for registered enterprises.',
        targetAudience: 'Registered contractors, vendors, supply chain operators, and registered business owners.',
        examples: ['Office & IT Equipment Supply', 'Facility Renovation & Works', 'Catering & Event Concessions', 'Printing & Packaging'],
    },
];

const filteredCategories = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return allCategories;

    return allCategories.filter((category) =>
        category.name.toLowerCase().includes(query)
        || category.tag.toLowerCase().includes(query)
        || category.description.toLowerCase().includes(query)
        || category.targetAudience.toLowerCase().includes(query)
        || category.examples.some((ex) => ex.toLowerCase().includes(query)),
    );
});
</script>

<template>
    <section class="detailed-content-page categories-page" aria-labelledby="categories-heading">
        <header class="page-intro-header">
            <p class="eyebrow"><UiIcon name="compass" :size="15" /> Opportunity catalogue</p>
            <h1 id="categories-heading">Explore opportunities by category</h1>
            <p class="page-intro-copy">
                OyaFind organises reviewed opportunities into 8 clear categories.
                Every public listing is reviewed before publication, and only listings whose source
                and details have been checked appear in the feed.
            </p>

            <div class="category-search-bar" role="search">
                <UiIcon class="search-icon" name="search" :size="19" />
                <input
                    v-model="searchQuery"
                    type="search"
                    maxlength="60"
                    placeholder="Filter categories"
                    aria-label="Filter categories"
                >
                <button
                    v-if="searchQuery"
                    class="clear-search-btn"
                    type="button"
                    aria-label="Clear filter"
                    @click="searchQuery = ''"
                >
                    Clear
                </button>
            </div>
        </header>

        <div v-if="filteredCategories.length" class="category-detailed-grid" aria-label="Opportunity category breakdown">
            <article
                v-for="cat in filteredCategories"
                :key="cat.slug"
                class="category-detail-card"
                :class="`tone-${cat.tone}`"
            >
                <div class="card-head">
                    <span class="category-card-icon" :class="`icon-${cat.tone}`">
                        <UiIcon :name="cat.icon" :size="24" />
                    </span>
                    <div class="card-title-group">
                        <span class="category-badge">{{ cat.tag }}</span>
                        <h2>{{ cat.name }}</h2>
                    </div>
                </div>

                <p class="category-summary">{{ cat.description }}</p>

                <div class="category-meta-block">
                    <div class="meta-row">
                        <strong><UiIcon name="users" :size="15" /> Who it’s for:</strong>
                        <span>{{ cat.targetAudience }}</span>
                    </div>
                </div>

                <div class="examples-section">
                    <span class="examples-label">Common types:</span>
                    <ul class="examples-pills">
                        <li v-for="example in cat.examples" :key="example">
                            {{ example }}
                        </li>
                    </ul>
                </div>

                <div class="card-footer-action">
                    <a
                        class="button button-primary category-action-button"
                        :href="`/categories/${encodeURIComponent(cat.slug)}`"
                        data-internal-link
                    >
                        Browse {{ cat.name }}
                        <UiIcon name="arrow" :size="16" />
                    </a>
                </div>
            </article>
        </div>

        <div v-else class="empty-state" role="status">
            <span class="empty-icon" aria-hidden="true"><UiIcon name="search" :size="24" /></span>
            <h3>No categories matched “{{ searchQuery }}”</h3>
            <p>Try searching for words like jobs, grants, SIWES, scholarships, or training.</p>
            <button class="button button-secondary" type="button" @click="searchQuery = ''">
                Show all 8 categories
            </button>
        </div>

        <aside class="trust-notice-banner" aria-label="Our publishing standard">
            <span class="notice-icon"><UiIcon name="shield-check" :size="26" /></span>
            <div>
                <h3>One standard for every category</h3>
                <p>
                    Every public listing has had its source and details checked by OyaFind before it appears.
                    A check is not an endorsement or a guarantee of safety, selection or payment.
                    Apply through the provider’s own channel.
                </p>
            </div>
        </aside>
    </section>
</template>

