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
        title: 'Discover freely without barriers',
        icon: 'compass',
        tone: 'mint',
        summary: 'Explore all public listings without creating an account or paying any subscription.',
        details: 'Browse opportunities by keyword, category, or area. You get complete eligibility details, source information, and deadlines upfront with no hidden requirements.',
    },
    {
        number: '02',
        title: 'Filter by human-reviewed locations',
        icon: 'pin',
        tone: 'blue',
        summary: 'Find opportunities near you across Ibadan and Nigeria using coarse area pickers.',
        details: 'We believe in privacy and low-data accessibility. OyaFind never requests device GPS or residential addresses. Instead, you filter by reviewed cities, states, and LGAs.',
    },
    {
        number: '03',
        title: 'Check verified sources & apply direct',
        icon: 'shield-check',
        tone: 'violet',
        summary: 'Every public listing undergoes staff checks before being published.',
        details: 'We confirm the authenticity of the organization, check that deadlines are active, and provide direct links to the official application portal. You apply directly—never through a middleman.',
    },
    {
        number: '04',
        title: 'Personalize with a free account',
        icon: 'users',
        tone: 'amber',
        summary: 'Register with email or phone to tailor your opportunity feed.',
        details: 'Set your optional Home Area (e.g. Ibadan North, Oyo) and select the categories that match your aspirations—from scholarships to tech gigs.',
    },
    {
        number: '05',
        title: 'Track applications privately',
        icon: 'calendar',
        tone: 'mint',
        summary: 'Stay organized with your own private application progress tracker.',
        details: 'Save listings and record your status: Interested, Preparing, Applied, Interview, or Outcome. Your notes and application progress are 100% private to your account.',
    },
];

const audiences = [
    {
        title: 'Students & Graduates',
        icon: 'graduation-cap',
        tone: 'violet',
        benefits: [
            'Find verified industrial training (SIWES) and internships.',
            'Discover legitimate undergraduate and postgraduate scholarships.',
            'Access graduate trainee programs and NYSC placement opportunities.',
        ],
    },
    {
        title: 'Jobseekers & Artisans',
        icon: 'briefcase',
        tone: 'mint',
        benefits: [
            'Explore full-time, part-time, and contract openings across Ibadan.',
            'Discover trade gigs, freelance tasks, and technical opportunities.',
            'Apply with confidence knowing every listing is vetted for zero-fee compliance.',
        ],
    },
    {
        title: 'Entrepreneurs & SMEs',
        icon: 'chart',
        tone: 'amber',
        benefits: [
            'Access non-repayable startup and agribusiness grants.',
            'Join local business hubs, incubator cohorts, and mentorship programs.',
            'Find formal public and private sector tenders for procurement bids.',
        ],
    },
    {
        title: 'Employers & Organizers',
        icon: 'users',
        tone: 'blue',
        benefits: [
            'Reach motivated, local talent in Ibadan and surrounding areas.',
            'Submit listings easily for staff review and rapid publication.',
            'Build trust with candidates through our verified provenance badge.',
        ],
    },
];

const faqs = [
    {
        question: 'Does OyaFind charge jobseekers any fees?',
        answer: 'Never. OyaFind is completely free for opportunity seekers. We will never ask you to pay to browse, apply, or attend interviews. Any opportunity provider demanding money to process applications is violating our terms and will be removed immediately.',
    },
    {
        question: 'Do I apply through OyaFind or directly with the employer?',
        answer: 'You apply directly through the original, official channel. OyaFind provides verified links to the employer portal, scholarship board, or application email. We are an independent directory, not an employment agency.',
    },
    {
        question: 'Why does OyaFind use coarse locations instead of device GPS?',
        answer: 'We prioritize your privacy and phone battery. Exact residential addresses and GPS coordinates are unnecessary for finding local opportunities. Choosing your general LGA or city gives you all relevant listings while keeping your personal location safe.',
    },
    {
        question: 'What is the 30-day freshness rule?',
        answer: 'Opportunities with specific deadlines automatically expire when the deadline passes. Listings without fixed deadlines are rechecked by our staff every 30 days. If an organization does not re-confirm that the role remains open, the listing is hidden from public view.',
    },
    {
        question: 'How do I report a suspicious or expired listing?',
        answer: 'Every listing on OyaFind includes a “Report” option. Anyone—even without an account—can flag a listing with a description of the issue. Our moderation team reviews reports promptly to protect the community.',
    },
];
</script>

<template>
    <section class="detailed-content-page how-it-works-page" aria-labelledby="how-heading">
        <header class="page-intro-header">
            <p class="eyebrow"><UiIcon name="compass" :size="15" /> How OyaFind works</p>
            <h1 id="how-heading">Real opportunities. Clear, checked and closer to you.</h1>
            <p class="page-intro-copy">
                We believe finding genuine work, scholarships, and grants shouldn’t involve scams, endless rumors, or paying for application forms.
                Here is the standard we hold every opportunity to.
            </p>
        </header>

        <!-- 5 Steps Section -->
        <section class="how-steps-section" aria-label="Five steps to discovering opportunities">
            <div class="section-title-wrap">
                <p class="eyebrow">The journey</p>
                <h2>Five simple steps from search to success</h2>
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

        <!-- Audience Grid -->
        <section class="how-audiences-section" aria-label="Opportunities by audience">
            <div class="section-title-wrap">
                <p class="eyebrow">Tailored for everyone</p>
                <h2>Who OyaFind is built for</h2>
                <p class="section-subtitle">No matter where you are in your career, there is a pathway for you.</p>
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
                            <UiIcon name="shield-check" :size="16" />
                            <span>{{ benefit }}</span>
                        </li>
                    </ul>
                </article>
            </div>
        </section>

        <!-- Trust Pillars -->
        <section class="trust-pillars-section" aria-label="Trust and safety commitments">
            <div class="pillar-card">
                <span class="pillar-icon"><UiIcon name="shield-check" :size="26" /></span>
                <div>
                    <h3>Human-Verified Sources</h3>
                    <p>No automated crawlers dumping unverified social media posts. Every listing is reviewed by team members before going live.</p>
                </div>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon"><UiIcon name="calendar" :size="26" /></span>
                <div>
                    <h3>30-Day Freshness Assurance</h3>
                    <p>Passed deadlines are marked expired immediately. Ongoing listings must be re-checked every 30 days or they are taken down.</p>
                </div>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon"><UiIcon name="coins" :size="26" /></span>
                <div>
                    <h3>Zero-Tolerance for Scam Fees</h3>
                    <p>We reject any posting asking candidates to pay for interview kits, aptitude tests, or application submissions.</p>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="how-faq-section" aria-labelledby="faq-heading">
            <div class="section-title-wrap">
                <p class="eyebrow">Common questions</p>
                <h2 id="faq-heading">Frequently asked questions</h2>
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

        <!-- Bottom CTA -->
        <div class="how-cta-banner">
            <div>
                <h2>Ready to explore what’s next?</h2>
                <p>Browse verified opportunities across Ibadan and Nigeria right now.</p>
            </div>
            <div class="cta-actions">
                <a class="button button-primary" href="/" data-internal-link>
                    Explore opportunities <UiIcon name="arrow" :size="16" />
                </a>
                <a class="button button-secondary" href="/register" data-internal-link>
                    Create free account
                </a>
            </div>
        </div>
    </section>
</template>

