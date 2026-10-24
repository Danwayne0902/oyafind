<script setup>
import { computed, ref } from 'vue';
import { verifyEmail } from '../api/account.js';
import UiIcon from '../components/UiIcon.vue';

const props = defineProps({
    userId: { type: String, required: true },
    hash: { type: String, required: true },
    verificationQuery: { type: Object, required: true },
});

const submitting = ref(false);
const verified = ref(false);
const error = ref('');
const linkIsComplete = computed(() =>
    /^\d+$/.test(props.userId)
    && /^[a-f0-9]{40}$/i.test(props.hash)
    && /^\d+$/.test(props.verificationQuery.expires ?? '')
    && /^[a-f0-9]{40}$/i.test(props.verificationQuery.signature ?? ''),
);

async function confirmVerification() {
    error.value = '';
    submitting.value = true;

    try {
        await verifyEmail({
            userId: props.userId,
            hash: props.hash,
            expires: props.verificationQuery.expires,
            signature: props.verificationQuery.signature,
        });
        verified.value = true;
    } catch (requestError) {
        error.value = requestError.status === 403
            ? 'This link has expired or is no longer valid. Request a new verification email to continue.'
            : requestError.message || 'We could not verify this email. Please request a new link.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="account-page account-verification-page" aria-labelledby="verification-heading">
        <div class="account-story">
            <a class="account-back-link" href="/" data-internal-link>
                <UiIcon name="arrow" :size="17" /> Explore OyaFind
            </a>
            <div class="account-story-copy">
                <span class="account-story-mark"><img :src="'/brand/oyafind-mark-on-dark.svg'" alt=""></span>
                <h1>One quick check.<br><span>Then you’re in.</span></h1>
                <p>Confirm your email address to activate your OyaFind account and start saving opportunities.</p>
            </div>
            <p class="account-story-foot">Your account remains inactive until email verification is complete.</p>
        </div>

        <div class="account-form-side">
            <div class="account-panel account-panel-auth register-status-panel">
                <span class="register-status-icon"><UiIcon name="shield-check" :size="25" /></span>
                <template v-if="verified">
                    <h2 id="verification-heading">Your email is verified.</h2>
                    <p class="account-panel-intro" role="status">Your OyaFind account is active. Sign in to save and track opportunities.</p>
                    <a class="button button-primary register-explore-link" href="/login" data-internal-link>
                        Sign in to OyaFind <UiIcon name="arrow" :size="18" />
                    </a>
                </template>
                <template v-else-if="!linkIsComplete">
                    <h2 id="verification-heading">This verification link is incomplete.</h2>
                    <p class="account-panel-intro">Open the complete link from your OyaFind email, or request a fresh verification email.</p>
                    <a class="button button-secondary register-explore-link" href="/register" data-internal-link>
                        Request a new email
                    </a>
                </template>
                <template v-else>
                    <h2 id="verification-heading">Verify your email address</h2>
                    <p class="account-panel-intro">Confirm that this email address belongs to you. The link is valid for 60 minutes.</p>
                    <p v-if="error" class="account-error" role="alert">{{ error }}</p>
                    <button class="button button-primary account-submit" type="button" :disabled="submitting" :aria-busy="submitting" @click="confirmVerification">
                        <UiIcon v-if="submitting" class="loading-spinner" name="loader" :size="18" />
                        {{ submitting ? 'Verifying…' : 'Verify email' }}
                        <UiIcon v-if="!submitting" name="arrow" :size="18" />
                    </button>
                    <p class="account-secondary">
                        Link not working? <a href="/register" data-internal-link>Request a new verification email</a>
                    </p>
                </template>
            </div>
        </div>
    </section>
</template>
