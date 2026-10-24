import assert from 'node:assert/strict';
import test from 'node:test';
import { createRenderer, defineComponent, h, nextTick } from 'vue';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { useLocationPicker } from '../../resources/js/useLocationPicker.js';

const server = await createServer({
    configFile: false,
    root: process.cwd(),
    plugins: [vue()],
    server: { middlewareMode: true, hmr: false },
    appType: 'custom',
});

test.after(async () => {
    await server.close();
});

function createHost() {
    return {
        createElement: (type) => ({ type, props: {}, children: [], parent: null, text: '' }),
        createText: (text) => ({ type: '#text', text, parent: null }),
        createComment: (text) => ({ type: '#comment', text, parent: null }),
        setText: (node, text) => { node.text = text; },
        setElementText: (node, text) => {
            node.children = [];
            node.text = text;
        },
        patchProp: (node, key, _previous, value) => { node.props[key] = value; },
        insert: (node, parent, anchor = null) => {
            if (node.parent) {
                const oldIndex = node.parent.children.indexOf(node);
                if (oldIndex >= 0) node.parent.children.splice(oldIndex, 1);
            }
            node.parent = parent;
            const index = anchor ? parent.children.indexOf(anchor) : -1;
            if (index >= 0) parent.children.splice(index, 0, node);
            else parent.children.push(node);
        },
        remove: (node) => {
            if (!node.parent) return;
            const index = node.parent.children.indexOf(node);
            if (index >= 0) node.parent.children.splice(index, 1);
            node.parent = null;
        },
        parentNode: (node) => node.parent,
        nextSibling: (node) => {
            if (!node.parent) return null;
            return node.parent.children[node.parent.children.indexOf(node) + 1] ?? null;
        },
    };
}

function findNode(root, predicate) {
    if (predicate(root)) return root;
    for (const child of root.children ?? []) {
        const match = findNode(child, predicate);
        if (match) return match;
    }
    return null;
}

function findByText(root, text) {
    return findNode(root, (node) => typeof node.text === 'string' && node.text.includes(text));
}

test('picker exposes labeled keyboard-operable search and selection controls', async () => {
    const { default: LocationPicker } = await server.ssrLoadModule('/resources/js/components/LocationPicker.vue');
    const html = await renderToString(createSSRApp(LocationPicker));

    assert.match(html, /Browse by area/);
    assert.match(html, /Location is optional/);
    assert.match(html, /Choose an area/);
    assert.doesNotMatch(html, /OpenStreetMap|ODbL/);
});

test('discovery declares the location selection event for its URL-state handler', async () => {
    const { default: DiscoveryPage } = await server.ssrLoadModule('/resources/js/pages/DiscoveryPage.vue');

    assert.ok(DiscoveryPage.emits.includes('location'));
});

test('picker open, search, select and clear actions use the public catalogue client', async (t) => {
    const originalFetch = globalThis.fetch;
    const originalStorage = globalThis.localStorage;
    const originalWindow = globalThis.window;
    const storage = new Map();
    const windowListeners = new Map();
    let offline = false;
    globalThis.localStorage = {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
    };
    globalThis.window = {
        addEventListener: (type, listener) => windowListeners.set(type, listener),
        removeEventListener: (type) => windowListeners.delete(type),
    };
    t.after(() => {
        globalThis.fetch = originalFetch;
        if (typeof originalStorage === 'undefined') delete globalThis.localStorage;
        else globalThis.localStorage = originalStorage;
        if (typeof originalWindow === 'undefined') delete globalThis.window;
        else globalThis.window = originalWindow;
    });

    const urls = [];
    globalThis.fetch = async (url) => {
        urls.push(url);
        if (offline) throw new TypeError('offline');
        const query = new URL(url, 'https://oyafind.test').searchParams.get('q');
        const locations = query
            ? [{ id: 7001, name: 'Synthetic Area', slug: 'synthetic-area', location_type: null, parent: null }]
            : [
                { id: 7001, name: 'Synthetic Area', slug: 'synthetic-area', location_type: null, parent: null },
                { id: 7002, name: 'Synthetic Other Area', slug: 'synthetic-other-area', location_type: null, parent: null },
            ];
        return new Response(JSON.stringify({
            data: locations,
            meta: { catalogueVersion: 'synthetic-v1', nextCursor: null },
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/json', 'X-Catalogue-Version': 'synthetic-v1' },
        });
    };

    let selected = null;
    const PickerHarness = defineComponent({
        emits: ['select'],
        setup(_props, { emit }) {
            const picker = useLocationPicker(emit);

            return () => h('div', [
                h('button', { onClick: picker.openPicker }, 'Choose an area'),
                h('output', picker.state.value),
                picker.isOpen.value
                    ? h('form', {
                        role: 'search',
                        onSubmit: (event) => {
                            event.preventDefault();
                            picker.search();
                        },
                    }, [
                        h('input', {
                            onInput: (event) => { picker.query.value = event.target.value; },
                        }),
                        h('select', { onChange: picker.selectChoice }, picker.choices.value.map((choice) =>
                            h('option', { value: choice.slug }, choice.name))),
                    ])
                    : null,
                h('button', { onClick: picker.clearLocation }, 'Browse all areas'),
            ]);
        },
    });

    const root = { type: 'root', children: [], parent: null };
    const renderer = createRenderer(createHost());
    const app = renderer.createApp(PickerHarness, { onSelect: (value) => { selected = value; } });
    app.mount(root);

    findByText(root, 'Choose an area').props.onClick();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
    assert.ok(findNode(root, (node) => node.type === 'select'));
    assert.equal(urls.length, 1);

    const input = findNode(root, (node) => node.type === 'input');
    input.props.onInput({ target: { value: 'Synthetic Area' } });
    await nextTick();
    const form = findNode(root, (node) => node.type === 'form' && node.props.role === 'search');
    form.props.onSubmit({ preventDefault() {} });
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();

    assert.equal(new URL(urls[1], 'https://oyafind.test').searchParams.get('q'), 'Synthetic Area');
    const select = findNode(root, (node) => node.type === 'select');
    select.props.onChange({ target: { value: 'synthetic-area' } });
    assert.equal(selected, 'synthetic-area');

    findByText(root, 'Browse all areas').props.onClick();
    assert.equal(selected, '');

    offline = true;
    windowListeners.get('online')();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
    assert.match(findByText(root, 'stale').text, /stale/);
    const offlineSelect = findNode(root, (node) => node.type === 'select');
    offlineSelect.props.onChange({ target: { value: 'synthetic-area' } });
    assert.equal(selected, 'synthetic-area');

    offline = false;
    windowListeners.get('online')();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
    assert.match(findByText(root, 'fresh').text, /fresh/);
    app.unmount();
});
