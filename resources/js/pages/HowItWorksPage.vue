<script setup>
import { ref } from 'vue';
import UiIcon from '../components/UiIcon.vue';

const openFaq = ref(null);

function toggleFaq(index) {
    openFaq.value = openFaq.value === index ? null : index;
}

const steps = [
    {
        number: '01',
        title: 'Browse without an account',
        icon: 'compass',
        tone: 'mint',
        summary: 'Public listings are open to everyone. No signup, no paywall.',
        details: 'Search by keyword, category or reviewed area. You can read the organisation, location, deadline, eligibility and application method before deciding whether to sign in.',
    },
    {
        number: '02',
        title: 'Filter by a reviewed area',
        icon: 'pin',
        tone: 'blue',
        summary: 'Use coarse areas from OyaFind’s own catalogue.',
        details: 'The pilot starts with Ibadan and Oyo State. OyaFind never requests device GPS, an exact address, or a live map. Location is optional, and all-area browsing always remains available.',
    },
    {
        number: '03',
        title: 'Check the source, then apply there',
        icon: 'shield-check',
        tone: 'violet',
        summary: 'Every public listing has had its source and details checked.',
        details: 'OyaFind reviews a listing before publication and shows when that check was recorded. You apply through the provider’s own channel. OyaFind does not submit applications or decide outcomes.',
    },
    {
        number: '04',
        title: 'Save and track privately',
        icon: 'bookmark',
        tone: 'amber',
        summary: 'Create an account to keep a shortlist and record your own progress.',
        details: 'Saving requires a verified email address or phone number. Your tracker stages, dates and notes are user-reported and visible only to you.',
    },
];

const audiences = [
    {
        title: 'Students & graduates',
        icon: 'graduation-cap',
        tone: 'violet',
        benefits: [
            'Find industrial training, internships and entry-level roles.',
            'Browse scholarships, training and graduate programmes.',
            'See the deadline and application channel before you start.',
        ],
    },
    {
        title: 'Jobseekers & skilled workers',
        icon: 'briefcase',
        tone: 'mint',
        benefits: [
            'Explore full-time, part-time and contract openings.',
            'Find trade gigs, freelance work and technical roles.',
            'Check the source and location before you spend time applying.',
        ],
    },
    {
        title: 'Entrepreneurs & small businesses',
        icon: 'chart',
        tone: 'amber',
        benefits: [
            'Browse grants, business support and training.',
            'Find public and private sector tenders.',
            'Keep a private record of the opportunities you pursue.',
        ],
    },
    {
        title: 'Organisations',
        icon: 'users',
        tone: 'blue',
        benefits: [
            'A staff-reviewed route to relevant local candidates.',
            'Listing submission is planned for approved contributors.',
            'Every listing is reviewed before it can be published.',
        ],
    },
];

const faqs = [
    {
        question: 'Do I need an account to use OyaFind?',
        answer: 'No. Public listings, search, categories and area filters work without an account. You need an account to save opportunities or use the private application tracker, and your selected contact must be verified before saving is enabled.',
    },
    {
        question: 'What does “source and details checked” mean?',
        answer: 'Before a listing is published, OyaFind reviews its source and details. The detailed checklist, evidence standard and recheck process are still being settled for the pilot. A check is not an endorsement or a guarantee of provider safety, selection or payment.',
    },
    {
        question: 'Why does OyaFind use coarse areas instead of GPS?',
        answer: 'A general area is enough to find relevant local opportunities, and it avoids collecting a precise home address. The picker uses reviewed areas from OyaFind’s own catalogue. It does not request GPS, call a geocoder or load map tiles.',
    },
    {
        question: 'What happens when a deadline passes?',
        answer: 'Listings with a deadline stop appearing when that deadline has passed. Listings without a deadline are rechecked every 30 days; if that check is overdue, the listing is hidden until it is confirmed again.',
    },
    {
        question: 'How do I report a problem with a listing?',
        answer: 'Public reporting is a required part of the pilot, but it is not enabled in this build yet. Until it is, check the provider’s own information carefully before sharing personal details or paying anything.',
    },
];
</script>

<template>
    <section class="detailed-content-page how-it-works-page" aria-labelledby="how-heading">
        <header class="page-intro-header">
            <p class="eyebrow"><UiIcon name="compass" :size="15" /> How OyaFind works</p>
            <h1 id="how-heading">What we check, what we don’t, and how to use it.</h1>
            <p class="page-intro-copy">
                OyaFind is a local opportunity board for the Ibadan pilot. This page explains the standard a listing
                has to meet before it appears, and what happens after you find one.
            </p>
        </header>

        <section class="how-steps-section" aria-label="Four steps to using OyaFind">
            <div class="section-title-wrap">
                <p class="eyebrow">The path</p>
                <h2>From a rumour to a checked listing</h2>
            </div>

            <div class="steps-grid">
                <article v-for="step in steps" :key="step.number" class="step-card" :class="`tone-${step.tone}`">
                    <div class="step-header">
                        <span class="step-number">{{ step.number }}</span>
                        <span class="step-icon" :class="`icon-${step.tone}`">
                            <UiIcon :name="step.icon" :size="22" />
                        </span>
                    </div>
                    <h3>{{ step.title }}</h3>
                    <p class="step-summary">{{ step.summary }}</p>
                    <p class="step-details">{{ step.details }}</p>
                </article>
            </div>
        </section>

        <section class="how-audiences-section" aria-label="Who OyaFind is for">
            <div class="section-title-wrap">
                <p class="eyebrow">Who it is for</p>
                <h2>Different starting points, one board</h2>
                <p class="section-subtitle">The service is open to everyone. Categories, search and area filters do the work.</p>
            </div>

            <div class="audiences-grid">
                <article v-for="item in audiences" :key="item.title" class="audience-card" :class="`tone-${item.tone}`">
                    <div class="audience-head">
                        <span class="audience-icon" :class="`icon-${item.tone}`">
                            <UiIcon :name="item.icon" :size="24" />
                        </span>
                        <h3>{{ item.title }}</h3>
                    </div>
                    <ul class="audience-list">
                        <li v-for="(benefit, bIndex) in item.benefits" :key="bIndex">
                            <UiIcon name="check" :size="16" />
                            <span>{{ benefit }}</span>
                        </li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="trust-pillars-section" aria-label="What OyaFind commits to">
            <div class="pillar-card">
                <span class="pillar-icon"><UiIcon name="shield-check" :size="26" /></span>
                <div>
                    <h3>Reviewed before publication</h3>
                    <p>Every listing is reviewed by OyaFind before it appears. Only listings whose source and details have been checked are published.</p>
                </div>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon"><UiIcon name="calendar" :size="26" /></span>
                <div>
                    <h3>The 30-day rule</h3>
                    <p>Listings with a deadline expire when it passes. Listings without one are rechecked every 30 days or hidden from public view.</p>
                </div>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon"><UiIcon name="arrow" :size="26" /></span>
                <div>
                    <h3>Apply at the source</h3>
                    <p>OyaFind sends you to the provider’s own channel. It does not submit applications, take payments or decide outcomes.</p>
                </div>
            </div>
        </section>

        <section class="how-faq-section" aria-labelledby="faq-heading">
            <div class="section-title-wrap">
                <p class="eyebrow">Common questions</p>
                <h2 id="faq-heading">Straight answers</h2>
            </div>

            <div class="faq-accordion">
                <div
                    v-for="(faq, index) in faqs"
                    :key="index"
                    class="faq-item"
                    :class="{ 'is-open': openFaq === index }"
                >
                    <button
                        class="faq-question-btn"
                        type="button"
                        :aria-expanded="openFaq === index"
                        @click="toggleFaq(index)"
                    >
                        <span>{{ faq.question }}</span>
                        <UiIcon class="faq-arrow" name="arrow" :size="17" />
                    </button>
                    <div v-show="openFaq === index" class="faq-answer-panel">
                        <p>{{ faq.answer }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="how-cta-banner">
            <div>
                <h2>Start with what is open now</h2>
                <p>Browse the Ibadan pilot feed, or create an account to save and track.</p>
            </div>
            <div class="cta-actions">
                <a class="button button-primary" href="/" data-internal-link>
                    Explore opportunities <UiIcon name="arrow" :size="16" />
                </a>
                <a class="button button-secondary" href="/register" data-internal-link>
                    Create an account
                </a>
            </div>
        </div>
    </section>
</template>
