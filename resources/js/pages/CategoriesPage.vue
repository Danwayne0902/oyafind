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
        description: 'Verified full-time, part-time, and contract employment opportunities across Ibadan and Nigeria.',
        targetAudience: 'Jobseekers, fresh graduates, skilled workers, and experienced professionals.',
        verificationNote: 'Direct employer verification, legitimate contact checks, and a strict no-applicant-fee rule.',
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
        verificationNote: 'Clearly defined scope of work, confirmed contact person, and agreed deliverable expectations.',
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
        verificationNote: 'Authentic employer mentorship structure, official company presence, and clear placement duration.',
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
        verificationNote: 'Official institutional sponsor check, verified application portal URL, and zero application fees.',
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
        verificationNote: 'Structured curriculum, credible training provider, and transparent pricing disclosure.',
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
        verificationNote: 'Confirmed grantmaker authenticity, zero repayment terms, and documented qualification criteria.',
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
        verificationNote: 'Recognized hub track record, experienced mentors, and defined program timelines.',
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
        verificationNote: 'Verifiable issuing agency or corporate body, clear specifications, and documented deadlines.',
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
                OyaFind organizes verified opportunities into 8 clear categories.
                Each listing is independently checked before publication so you spend time applying to legitimate, active options.
            </p>

            <div class="category-search-bar" role="search">
                <UiIcon class="search-icon" name="search" :size="19" />
                <input
                    v-model="searchQuery"
                    type="search"
                    maxlength="60"
                    placeholder="Filter by keyword (e.g. jobs, tech, SIWES, grant, artisan)…"
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
                    <div class="meta-row">
                        <strong><UiIcon name="shield-check" :size="15" /> Our check:</strong>
                        <span>{{ cat.verificationNote }}</span>
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

        <aside class="trust-notice-banner" aria-label="Our publishing standards">
            <span class="notice-icon"><UiIcon name="shield-check" :size="26" /></span>
            <div>
                <h3>No paid rankings. No phantom listings.</h3>
                <p>
                    Every category on OyaFind operates under the same standard: opportunities must have verifiable sources,
                    transparent requirements, and direct application channels. We never charge jobseekers or applicants to view or apply.
                </p>
            </div>
        </aside>
    </section>
</template>

