<script setup>
import { computed, onMounted, ref } from 'vue';
import { getRegistrationStatus, registerAccount } from '../api/account.js';
import UiIcon from '../components/UiIcon.vue';

const emit = defineEmits(['authenticated']);

const availability = ref('checking');
const availabilityError = ref('');
const availabilityMessage = ref('');
const registrationMode = ref('email_verification');
const name = ref('');
const contact = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const passwordVisible = ref(false);
const passwordMismatch = computed(() =>
    passwordConfirmation.value !== '' && password.value !== passwordConfirmation.value,
);
const hasFieldErrors = computed(() => Object.keys(fieldErrors.value).length > 0);
const fieldErrors = ref({});
const requestError = ref('');
const submissionMessage = ref('');
const submitting = ref(false);
const submitted = ref(false);

function safeReturnPath() {
    if (typeof window === 'undefined') return '/profile';
    const value = new URLSearchParams(window.location.search).get('returnTo');
    return value && value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/api/')
        ? value
        : '/profile';
}

const loginHref = computed(() => {
    const target = safeReturnPath();
    return target === '/profile' ? '/login' : `/login?returnTo=${encodeURIComponent(target)}`;
});

async function checkAvailability() {
    availability.value = 'checking';
    availabilityError.value = '';
    availabilityMessage.value = '';

    try {
        const status = await getRegistrationStatus();
        availability.value = status.available ? 'available' : 'unavailable';
        availabilityMessage.value = status.message ?? '';
        registrationMode.value = status.mode ?? 'email_verification';
    } catch (error) {
        availability.value = 'unavailable';
        availabilityError.value = error.message || 'We could not check registration right now.';
    }
}

async function submit() {
    fieldErrors.value = {};
    requestError.value = '';
    if (passwordMismatch.value) {
        fieldErrors.value = { password_confirmation: ['Passwords do not match.'] };
        return;
    }

    submitting.value = true;

    try {
        const result = await registerAccount({
            name: name.value.trim(),
            contact: contact.value.trim(),
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
        if (result.registration_mode === 'local_unverified') {
            emit('authenticated', safeReturnPath());
            return;
        }

        password.value = '';
        passwordConfirmation.value = '';
        submissionMessage.value = result.message;
        submitted.value = true;
    } catch (error) {
        requestError.value = error.message || 'We could not start your registration. Please try again.';
        fieldErrors.value = error.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

onMounted(checkAvailability);
</script>

<template>
    <section class="account-page account-register-page" aria-labelledby="register-heading">
        <div class="account-story register-story">
            <a class="account-back-link" href="/" data-internal-link>
                <UiIcon name="arrow" :size="17" /> Explore OyaFind
            </a>
            <div class="account-story-copy">
                <span class="account-story-mark"><img :src="'/brand/oyafind-mark-on-dark.svg'" alt=""></span>
                <h1>Make room for<br><span>your next move.</span></h1>
                <p>Discover opportunities for work, learning and business in every state across Nigeria.</p>
                <div class="register-category-row" aria-label="Opportunity types">
                    <span><UiIcon name="briefcase" :size="17" /> Jobs</span>
                    <span><UiIcon name="graduation-cap" :size="17" /> Scholarships</span>
                    <span><UiIcon name="bolt" :size="17" /> Gigs</span>
                    <span><UiIcon name="chart" :size="17" /> Business</span>
                </div>
            </div>
            <p class="account-story-foot">Start by exploring—no account is needed to browse.</p>
        </div>

        <div class="account-form-side">
            <div class="account-panel account-panel-auth register-status-panel">
                <template v-if="availability === 'checking'">
                    <span class="register-status-icon"><UiIcon class="loading-spinner" name="loader" :size="25" /></span>
                    <h2 id="register-heading">Preparing secure sign-up</h2>
                    <p class="account-panel-intro register-loading-copy" role="status" aria-live="polite">Checking account registration settings before asking for your details.</p>
                </template>

                <template v-else-if="availability !== 'available'">
                    <span class="register-status-icon"><UiIcon name="shield-check" :size="25" /></span>
                    <h2 id="register-heading">Sign-up is not available yet.</h2>
                    <p class="account-panel-intro">
                        We only collect your details when email verification is ready and account creation is enabled.
                    </p>
                    <p v-if="availabilityMessage" class="register-configuration-note" role="status" aria-live="polite">
                        {{ availabilityMessage }}
                    </p>
                    <p v-if="availabilityError" class="account-error" role="alert">{{ availabilityError }}</p>
                    <button class="button button-secondary register-retry-button" type="button" :disabled="availability === 'checking'" :aria-busy="availability === 'checking'" @click="checkAvailability">
                        <UiIcon v-if="availability === 'checking'" class="loading-spinner" name="loader" :size="17" />
                        <template v-else>Check again</template>
                    </button>
                    <p class="account-secondary">
                        You can still <a href="/" data-internal-link>explore opportunities</a> without an account.
                    </p>
                </template>

                <template v-else-if="submitted">
                    <span class="register-status-icon"><UiIcon name="shield-check" :size="25" /></span>
                    <h2 id="register-heading">Check your email</h2>
                    <p class="account-panel-intro" role="status" aria-live="polite">
                        {{ submissionMessage }}
                    </p>
                    <p class="account-secondary">
                        Already verified? <a :href="loginHref" data-internal-link>Sign in</a>
                    </p>
                    <p class="account-privacy-note">
                        No account is activated until verified. Unverified details remain pending until verified or manually deleted.
                    </p>
                </template>

                <template v-else>
                    <h2 id="register-heading">Create your OyaFind account</h2>
                    <p class="account-panel-intro">Create an account to keep your OyaFind profile and opportunities together.</p>
                    <form class="account-form" @submit.prevent="submit">
                        <label for="register-name">Full name</label>
                        <input
                            id="register-name"
                            v-model="name"
                            name="name"
                            type="text"
                            autocomplete="name"
                            required
                            maxlength="120"
                            :aria-invalid="Boolean(fieldErrors.name)"
                            :aria-describedby="fieldErrors.name ? 'register-name-error' : undefined"
                        >
                        <p v-if="fieldErrors.name" id="register-name-error" class="account-error" role="alert">
                            {{ fieldErrors.name[0] }}
                        </p>

                        <label for="register-contact">Email or phone number</label>
                        <input
                            id="register-contact"
                            v-model="contact"
                            name="contact"
                            type="text"
                            autocomplete="username"
                            required
                            maxlength="190"
                            placeholder="you@example.com"
                            :aria-invalid="Boolean(fieldErrors.contact)"
                            :aria-describedby="fieldErrors.contact ? 'register-contact-hint register-contact-error' : 'register-contact-hint'"
                        >
                        <p id="register-contact-hint" class="register-field-hint">
                            Use one email address or phone number. You’ll use it to sign in.
                        </p>
                        <p v-if="fieldErrors.contact" id="register-contact-error" class="account-error" role="alert">
                            {{ fieldErrors.contact[0] }}
                        </p>

                        <label for="register-password">Password</label>
                        <div class="account-password-field">
                            <input
                                id="register-password"
                                v-model="password"
                                name="password"
                                :type="passwordVisible ? 'text' : 'password'"
                                autocomplete="new-password"
                                required
                                minlength="12"
                                maxlength="255"
                                :aria-describedby="fieldErrors.password ? 'register-password-guidance register-password-error' : 'register-password-guidance'"
                                :aria-invalid="Boolean(fieldErrors.password)"
                            >
                            <button
                                class="account-password-toggle"
                                type="button"
                                :aria-label="passwordVisible ? 'Hide passwords' : 'Show passwords'"
                                :aria-pressed="passwordVisible"
                                aria-controls="register-password"
                                @click="passwordVisible = !passwordVisible"
                            >
                                <UiIcon :name="passwordVisible ? 'eye-off' : 'eye'" :size="18" />
                            </button>
                        </div>
                        <p id="register-password-guidance" class="register-field-hint">Use at least 12 characters.</p>
                        <p v-if="fieldErrors.password" id="register-password-error" class="account-error" role="alert">
                            {{ fieldErrors.password[0] }}
                        </p>

                        <label for="register-password-confirmation">Confirm password</label>
                        <div class="account-password-field">
                            <input
                                id="register-password-confirmation"
                                v-model="passwordConfirmation"
                                name="password_confirmation"
                                :type="passwordVisible ? 'text' : 'password'"
                                autocomplete="new-password"
                                required
                                minlength="12"
                                maxlength="255"
                                :aria-invalid="Boolean(fieldErrors.password_confirmation) || passwordMismatch"
                                :aria-describedby="fieldErrors.password_confirmation || passwordMismatch ? 'register-confirmation-error' : undefined"
                            >
                            <button
                                class="account-password-toggle"
                                type="button"
                                :aria-label="passwordVisible ? 'Hide passwords' : 'Show passwords'"
                                :aria-pressed="passwordVisible"
                                aria-controls="register-password register-password-confirmation"
                                @click="passwordVisible = !passwordVisible"
                            >
                                <UiIcon :name="passwordVisible ? 'eye-off' : 'eye'" :size="18" />
                            </button>
                        </div>
                        <p v-if="fieldErrors.password_confirmation || passwordMismatch" id="register-confirmation-error" class="account-error" role="alert">
                            {{ fieldErrors.password_confirmation?.[0] ?? 'Passwords do not match.' }}
                        </p>

                        <p v-if="requestError && !hasFieldErrors" class="account-error" role="alert">{{ requestError }}</p>
                        <button class="button button-primary account-submit" type="submit" :disabled="submitting" :aria-busy="submitting">
                            <UiIcon v-if="submitting" class="loading-spinner" name="loader" :size="18" />
                            {{ submitting ? (registrationMode === 'local_unverified' ? 'Creating account…' : 'Sending verification email…') : 'Create account' }}
                            <UiIcon v-if="!submitting" name="arrow" :size="18" />
                        </button>
                    </form>
                    <p class="account-secondary">
                        Already have an account? <a :href="loginHref" data-internal-link>Sign in</a>
                    </p>
                    <p class="account-privacy-note">
                        <template v-if="registrationMode === 'local_unverified'">
                            Your local account is active. Your contact remains unverified, so saving opportunities is disabled.
                        </template>
                        <template v-else>
                            Your account stays inactive until you verify. Unverified details remain pending until verified or manually deleted.
                        </template>
                    </p>
                </template>
            </div>
        </div>
    </section>
</template>
