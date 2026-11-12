import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';

function component(name, dependencies, globals) {
    const source = readFileSync(new URL(`../../resources/js/components/${name}.vue`, import.meta.url), 'utf8');
    const compiled = ts.transpileModule(compileScript(parse(source).descriptor, { id: name }).content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    vm.runInNewContext(compiled, { exports, require: (name) => dependencies[name] ?? {}, ...globals });

    return exports.default;
}

const settle = async () => {
    for (let i = 0; i < 8; i++) {
        await vue.nextTick();
    }
};

function video(responseFactory) {
    const timers = new Map();
    const listeners = new Map();
    const calls = [];
    let mount;
    let cleanup;
    let intersect;
    let disconnected = false;
    let nextId = 0;
    const media = { id: 1, status: 'processing', statusUrl: '/media/1/status' };
    const document = {
        visibilityState: 'visible',
        addEventListener: (event, handler) => listeners.set(event, handler),
        removeEventListener: (event) => listeners.delete(event),
    };
    const player = component('TimelineVideo', {
        vue: { ...vue, onMounted: (fn) => {
 mount = fn; 
}, onBeforeUnmount: (fn) => {
 cleanup = fn; 
} },
    }, {
        document, AbortController,
        IntersectionObserver: class {
            constructor(fn) {
 intersect = fn; 
}
            observe() {}
            disconnect() {
 disconnected = true; 
}
        },
        setTimeout: (fn) => {
 timers.set(++nextId, fn);

 return nextId; 
},
        clearTimeout: (id) => timers.delete(id),
        fetch: async (url, options) => {
            calls.push({ url, options });

            if (responseFactory) {
                return responseFactory();
            }

            return { ok: true, status: 200, json: async () => media };
        },
    }).setup({ media }, { expose() {}, emit() {} });
    player.container.value = {};
    mount();

    return {
        player, calls, timers, document, listeners,
        intersect: (visible) => intersect([{ isIntersecting: visible }]),
        async tick() {
            const [id, fn] = timers.entries().next().value ?? [];

            if (fn) {
 timers.delete(id); await fn(); 
}
        },
        cleanup: () => {
 cleanup(); assert.equal(disconnected, true); 
},
    };
}

test('video status polls only on screen in a visible tab and cleans up on unmount', async () => {
    const state = video();
    assert.equal(state.timers.size, 0);
    assert.equal(state.calls.length, 0);
    state.intersect(true);
    await state.tick();
    assert.equal(state.calls.length, 1);
    assert.equal(state.timers.size, 1);
    state.document.visibilityState = 'hidden';
    state.listeners.get('visibilitychange')();
    assert.equal(state.timers.size, 0);
    state.document.visibilityState = 'visible';
    state.listeners.get('visibilitychange')();
    await state.tick();
    assert.equal(state.calls.length, 2);
    state.intersect(false);
    assert.equal(state.calls[1].options.signal.aborted, true);
    assert.equal(state.timers.size, 0);
    state.intersect(true);
    state.cleanup();
    assert.equal(state.timers.size, 0);
    assert.equal(state.listeners.size, 0);
});

test('leaving the viewport aborts in-flight polling without applying a late response', async () => {
    let resolve;
    const state = video(() => new Promise((done) => {
 resolve = done; 
}));
    state.intersect(true);
    const request = state.tick();
    assert.equal(state.calls.length, 1);
    state.intersect(false);
    assert.equal(state.calls[0].options.signal.aborted, true);
    resolve({ ok: true, status: 200, json: async () => ({ id: 1, status: 'ready' }) });
    await request;
    assert.equal(state.player.video.value.status, 'processing');
    assert.equal(state.player.statusUnavailable.value, false);
    assert.equal(state.timers.size, 0);
    state.cleanup();
});

function comments({ expanded = false, hash = '' } = {}) {
    let mount;
    let cleanup;
    const calls = [];
    const scrolled = [];
    const responses = [];
    const visits = [];
    const props = vue.reactive({
        post: { id: 1, comments: [], commentsCount: 25, commentsUrl: '/posts/1/comments' },
        expanded, allowComments: true, showComments: true,
    });
    const state = component('TimelineEngagement', {
        vue: { ...vue, onMounted: (fn) => {
 mount = fn; 
}, onBeforeUnmount: (fn) => {
 cleanup = fn; 
} },
        '@inertiajs/vue3': {
            usePage: () => ({ props: { filters: { author: '' } } }),
            useForm: () => ({ content: '' }),
            router: { visit: (...args) => visits.push(args) },
        },
        '@/actions/App/Http/Controllers/CommentHeartController': { toggle: (id) => `/comments/${id}/heart` },
    }, {
        AbortController,
        window: { location: { hash } },
        document: { getElementById: (id) => ({ scrollIntoView: () => scrolled.push(id) }) },
        fetch: async (url, options) => {
            calls.push({ url, options });
            const data = responses.shift() ?? { data: [], total: 0, previous_page: null, next_page: null };

            if (data?.status) {
                return { ok: false, status: data.status };
            }

            return { ok: true, json: async () => data };
        },
    }).setup(props, { expose() {}, emit() {} });

    return { state, props, mount, cleanup, calls, scrolled, responses, visits };
}

test('comments wait until opened, load additional pages, and refresh liked comments', async () => {
    const view = comments();
    view.mount();
    await settle();
    assert.equal(view.calls.length, 0);
    view.responses.push({ data: [{ id: 1, heartsCount: 0 }], total: 25, previous_page: null, next_page: 2 });
    view.state.toggleCommentForm();
    await settle();
    assert.equal(view.calls[0].url, '/posts/1/comments?page=1');
    assert.equal(view.state.loadedComments.value.length, 1);
    assert.equal(view.state.nextPage.value, 2);
    view.responses.push({ data: [{ id: 25, heartsCount: 0 }], total: 25, previous_page: 1, next_page: null });
    await view.state.loadComments(2, 'after');
    assert.deepEqual(Array.from(view.state.loadedComments.value, (item) => item.id), [1, 25]);
    view.responses.push({ data: [{ id: 25, heartsCount: 1, heartedByViewer: true }], total: 25, previous_page: 1, next_page: null });
    view.state.toggleCommentHeart(25);
    view.visits[0][1].onSuccess();
    await settle();
    assert.equal(view.calls[2].url, '/posts/1/comments?focus_id=25');
    assert.equal(view.state.loadedComments.value[1].heartsCount, 1);
    view.cleanup();
});

test('opening a notification fetches the target comment page and scrolls after it renders', async () => {
    const view = comments({ expanded: true, hash: '#comment-25' });
    view.responses.push({ data: [{ id: 25 }], total: 25, previous_page: 1, next_page: null });
    view.mount();
    await settle();
    assert.equal(view.calls[0].url, '/posts/1/comments?focus_id=25');
    assert.equal(view.state.previousPage.value, 1);
    assert.deepEqual(view.scrolled, ['comment-25']);
    view.cleanup();
});

test('closing comments aborts outstanding requests and permits a later retry', async () => {
    const view = comments();
    let resolve;
    view.responses.push(new Promise((done) => {
 resolve = done; 
}));
    view.mount();
    view.state.toggleCommentForm();
    await settle();
    view.state.toggleCommentForm();
    await settle();
    assert.equal(view.calls[0].options.signal.aborted, true);
    assert.equal(view.state.commentsLoading.value, false);
    resolve({ data: [{ id: 99 }], total: 1, previous_page: null, next_page: null });
    await settle();
    assert.equal(view.state.loadedComments.value.length, 0);
    view.responses.push({ data: [{ id: 1 }], total: 1, previous_page: null, next_page: null });
    view.state.toggleCommentForm();
    await settle();
    assert.equal(view.state.loadedComments.value[0].id, 1);
    view.cleanup();
});

test('comments report request failures, retry, and reload the last page after posting', async () => {
    const view = comments();
    view.responses.push({ status: 503 });
    view.mount();
    view.state.toggleCommentForm();
    await settle();
    assert.match(view.state.commentsError.value, /could not be loaded/);
    view.responses.push({ data: [{ id: 1 }], total: 25, previous_page: null, next_page: 2 });
    await view.state.loadComments();
    assert.equal(view.state.commentsError.value, '');
    view.responses.push({ data: [{ id: 26 }], total: 26, previous_page: 1, next_page: null });
    view.props.post.commentsCount = 26;
    await settle();
    assert.equal(view.calls.at(-1).url, '/posts/1/comments?page=2');
    assert.equal(view.state.loadedComments.value[0].id, 26);
    view.cleanup();
});

test('gallery viewers fetch post siblings on demand and ignore stale responses after switching posts', async () => {
    const source = readFileSync(new URL('../../resources/js/composables/usePostMedia.ts', import.meta.url), 'utf8');
    const compiled = ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    const calls = [];
    let cleanup;
    vm.runInNewContext(compiled, {
        exports, AbortController,
        require: () => ({ ...vue, onBeforeUnmount: (fn) => {
 cleanup = fn; 
} }),
        fetch: (url, options) => new Promise((resolve) => calls.push({ url, options, resolve })),
    });
    const viewer = exports.usePostMedia('image');
    assert.equal(calls.length, 0);
    const first = viewer.load({ id: 1, postId: 1, postTitle: 'First' });
    const second = viewer.load({ id: 2, postId: 2, postTitle: 'Second' });
    assert.equal(calls[0].options.signal.aborted, true);
    assert.equal(calls[1].url, '/api/posts/2/media?type=image');
    calls[1].resolve({ ok: true, json: async () => ({ data: [{ id: 2 }, { id: 3 }] }) });
    await second;
    calls[0].resolve({ ok: true, json: async () => ({ data: [{ id: 1 }] }) });
    await first;
    assert.equal(viewer.media.value.postId, 2);
    assert.deepEqual(Array.from(viewer.media.value.items, (item) => item.id), [2, 3]);
    assert.equal(viewer.media.value.items[1].postTitle, 'Second');
    await viewer.load({ id: 3, postId: 2 });
    assert.equal(calls.length, 2);
    cleanup();
});
