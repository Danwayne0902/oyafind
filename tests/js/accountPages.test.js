import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';

const server = await createServer({
    configFile: false,
    root: process.cwd(),
    plugins: [vue()],
    server: { middlewareMode: true },
    appType: 'custom',
});

test.after(async () => {
    await server.close();
});

test('registration screen waits for email delivery readiness before collecting details', async () => {
    const { default: RegisterPage } = await server.ssrLoadModule('/resources/js/pages/RegisterPage.vue');
    const html = await renderToString(createSSRApp(RegisterPage));

    assert.match(html, /Preparing secure sign-up/);
    assert.match(html, /Checking account registration settings/);
    assert.match(html, /class="loading-spinner"/);
    assert.doesNotMatch(html, /name="password"|name="email"|name="register-name"/);
});

test('email verification screen requires a deliberate confirmation action', async () => {
    const { default: VerifyEmailPage } = await server.ssrLoadModule('/resources/js/pages/VerifyEmailPage.vue');
    const html = await renderToString(createSSRApp(VerifyEmailPage, {
        userId: '17',
        hash: 'a'.repeat(40),
        verificationQuery: { expires: '1800000000', signature: 'b'.repeat(40) },
    }));

    assert.match(html, /Verify your email address/);
    assert.match(html, /The link is valid for 60 minutes/);
    assert.match(html, /Verify email/);
    assert.doesNotMatch(html, /verified\. Sign in/);
});

test('sign-in screen explains OyaFind and accepts an email or phone identifier and password', async () => {
    const { default: LoginPage } = await server.ssrLoadModule('/resources/js/pages/LoginPage.vue');
    const html = await renderToString(createSSRApp(LoginPage));

    assert.match(html, /Email or phone number/);
    assert.match(html, /starting with the Ibadan pilot/);
    assert.match(html, /Explore by category and reviewed area/);
    assert.match(html, /See when a source and its details were checked/);
    assert.match(html, /name="identifier"/);
    assert.match(html, /name="password"/);
    assert.match(html, /class="button button-secondary account-register-link"/);
});

test('sign-in keeps a safe return path when switching to registration', async () => {
    const { default: LoginPage } = await server.ssrLoadModule('/resources/js/pages/LoginPage.vue');
    const render = async (search) => {
        globalThis.window = { location: { search } };
        try {
            return await renderToString(createSSRApp(LoginPage));
        } finally {
            delete globalThis.window;
        }
    };

    const saved = await render('?returnTo=%2Fsaved');
    assert.match(saved, /href="\/register\?returnTo=%2Fsaved"/);

    const unsafe = await render('?returnTo=%2F%2Fevil.example');
    assert.match(unsafe, /href="\/register"/);
    assert.doesNotMatch(unsafe, /evil\.example/);
});

test('private profile screen communicates the unverified-contact save gate', async () => {
    const { default: ProfilePage } = await server.ssrLoadModule('/resources/js/pages/ProfilePage.vue');
    const html = await renderToString(createSSRApp(ProfilePage, {
        initialProfile: {
            id: 1,
            name: 'Synthetic account',
            email: 'person@example.test',
            home_location_id: null,
            interests: [],
            contact_verified: false,
            save_eligible: false,
        },
    }));

    assert.match(html, /Make it yours/);
    assert.match(html, /Profile preferences/);
    assert.match(html, /What interests you\?/);
    assert.match(html, /Not verified yet/);
    assert.match(html, /Saving opportunities is unavailable until your selected contact is verified/);
    assert.match(html, /Contact verification is not currently configured/);
    assert.doesNotMatch(html, /Local testing mode: verification email is skipped/);
});

test('registration, verification and account requests use same-origin credentials and CSRF protection', async () => {
    const { getRegistrationStatus, registerAccount, verifyEmail, signIn, signOut, updateMyProfile } =
        await server.ssrLoadModule('/resources/js/api/account.js');
    const originalFetch = globalThis.fetch;
    const requests = [];
    let csrfTokenNumber = 0;

    globalThis.fetch = async (url, options = {}) => {
        requests.push({ url, options });
        if (url.endsWith('/auth/csrf-token')) {
            csrfTokenNumber += 1;
            return new Response(JSON.stringify({ csrf_token: `test-session-token-${csrfTokenNumber}` }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        }
        if (url.endsWith('/auth/registration-status')) {
            return new Response(JSON.stringify({ data: { available: true } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        }
        return new Response(JSON.stringify({ data: { id: 1 }, message: 'Signed out.' }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
        });
    };

    try {
        const registrationStatus = await getRegistrationStatus();
        assert.equal(registrationStatus.available, true);
        await registerAccount({
            name: 'Synthetic user',
            email: 'person@example.test',
            password: 'synthetic password',
            password_confirmation: 'synthetic password',
        });
        await verifyEmail({
            userId: '1',
            hash: 'a'.repeat(40),
            expires: '1800000000',
            signature: 'b'.repeat(40),
        });
        await signIn({ identifier: 'person@example.test', password: 'synthetic password' });
        await updateMyProfile({ name: 'Synthetic user', interests: [], home_location_id: null });
        await signOut();

        const registrationRequest = requests.find(({ url }) => url.endsWith('/auth/register'));
        const verificationRequest = requests.find(({ url }) => url.includes('/auth/verify-email/'));
        const loginRequest = requests.find(({ url }) => url.endsWith('/auth/login'));
        const profileUpdateRequest = requests.find(({ url, options }) =>
            url.endsWith('/me') && options.method === 'PATCH');
        const logoutRequest = requests.find(({ url }) => url.endsWith('/auth/logout'));
        assert.equal(registrationRequest.options.credentials, 'same-origin');
        assert.equal(registrationRequest.options.headers['X-CSRF-TOKEN'], 'test-session-token-1');
        assert.equal(JSON.parse(registrationRequest.options.body).email, 'person@example.test');
        assert.equal(verificationRequest.options.credentials, 'same-origin');
        assert.equal(verificationRequest.options.headers['X-CSRF-TOKEN'], 'test-session-token-2');
        assert.equal(new URL(verificationRequest.url, 'https://oyafind.test').searchParams.get('signature'), 'b'.repeat(40));
        assert.equal(loginRequest.options.credentials, 'same-origin');
        assert.equal(loginRequest.options.headers['X-CSRF-TOKEN'], 'test-session-token-3');
        assert.equal(JSON.parse(loginRequest.options.body).identifier, 'person@example.test');
        assert.equal(profileUpdateRequest.options.headers['X-CSRF-TOKEN'], 'test-session-token-4');
        assert.equal(logoutRequest.options.headers['X-CSRF-TOKEN'], 'test-session-token-4');
        assert.equal(requests.filter(({ url }) => url.endsWith('/auth/csrf-token')).length, 4);
        assert.equal(requests.filter(({ url }) => url.endsWith('/auth/registration-status')).length, 1);
    } finally {
        globalThis.fetch = originalFetch;
    }
});
