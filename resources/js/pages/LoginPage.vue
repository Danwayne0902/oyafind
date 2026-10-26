<script setup>
import { computed, ref } from 'vue';
import { signIn } from '../api/account.js';
import UiIcon from '../components/UiIcon.vue';

const emit = defineEmits(['authenticated']);
const identifier = ref('');
const password = ref('');
const error = ref('');
const fieldErrors = ref({});
const submitting = ref(false);

function safeReturnPath() {
    if (typeof window === 'undefined') return '/profile';
    const value = new URLSearchParams(window.location.search).get('returnTo');
    return value && value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/api/')
        ? value
        : '/profile';
}

const registerHref = computed(() => {
    const target = safeReturnPath();
    return target === '/profile' ? '/register' : `/register?returnTo=${encodeURIComponent(target)}`;
});

async function submit() {
    error.value = '';
    fieldErrors.value = {};
    submitting.value = true;
    try {
        await signIn({ identifier: identifier.value.trim(), password: password.value });
        emit('authenticated', safeReturnPath());
    } catch (requestError) {
        error.value = requestError.message || 'Sign in was unsuccessful. Check your details and try again.';
        fieldErrors.value = requestError.errors ?? {};
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="account-page account-login-page" aria-labelledby="login-heading">
        <div class="account-story">
            <a class="account-back-link" href="/" data-internal-link>
                <UiIcon name="arrow" :size="17" /> Explore OyaFind
            </a>
            <div class="account-story-copy">
                <span class="account-story-mark"><img :src="'/brand/oyafind-mark-on-dark.svg'" alt=""></span>
                <h1>Find what’s next.<br><span>Keep it moving.</span></h1>
                <p>Jobs, scholarships, internships, grants, training and more, across all 36 states and the FCT — so the next step feels easier to find.</p>
                <ul class="account-value-list">
                    <li><UiIcon name="search" :size="19" /><span>Explore by category and reviewed area</span></li>
                    <li><UiIcon name="shield-check" :size="19" /><span>See when a source and its details were checked</span></li>
                    <li><UiIcon name="calendar" :size="19" /><span>Keep deadlines and private notes in view</span></li>
                </ul>
            </div>
            <p class="account-story-foot">Browse public opportunities freely, with or without an account.</p>
        </div>

        <div class="account-form-side">
            <div class="account-panel account-panel-auth">
                <p class="account-kicker">Welcome back</p>
                <h2 id="login-heading">Sign in to OyaFind</h2>
                <p class="account-panel-intro">Continue to your profile and the opportunities you’re keeping track of.</p>

                <form class="account-form" @submit.prevent="submit">
                    <label for="account-identifier">Email or phone number</label>
                    <input
                        id="account-identifier"
                        v-model="identifier"
                        name="identifier"
                        type="text"
                        autocomplete="username"
                        required
                        maxlength="190"
                        :aria-invalid="Boolean(fieldErrors.identifier)"
                        aria-describedby="identifier-error"
                    >
                    <p v-if="fieldErrors.identifier" id="identifier-error" class="account-error" role="alert">
                        {{ fieldErrors.identifier[0] }}
                    </p>

                    <label for="account-password">Password</label>
                    <input
                        id="account-password"
                        v-model="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        maxlength="255"
                        :aria-invalid="Boolean(fieldErrors.password)"
                        aria-describedby="password-error"
                    >
                    <p v-if="fieldErrors.password" id="password-error" class="account-error" role="alert">
                        {{ fieldErrors.password[0] }}
                    </p>

                    <p v-if="error" class="account-error" role="alert">{{ error }}</p>
                    <button class="button button-primary account-submit" type="submit" :disabled="submitting" :aria-busy="submitting">
                        <UiIcon v-if="submitting" class="loading-spinner" name="loader" :size="18" />
                        {{ submitting ? 'Signing in…' : 'Sign in' }}
                        <UiIcon v-if="!submitting" name="arrow" :size="18" />
                    </button>
                </form>
                <div class="account-panel-divider"><span>New to OyaFind?</span></div>
                <a class="button button-secondary account-register-link" :href="registerHref" data-internal-link>Create an account</a>
                <p class="account-privacy-note">Your profile and opportunity tracker are for account holders.</p>
            </div>
        </div>
    </section>
</template>
