import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';

function load(path, dependencies, globals) {
    let source = readFileSync(new URL(path, import.meta.url), 'utf8');

    if (path.endsWith('.vue')) {
        source = compileScript(parse(source).descriptor, {
            id: 'double-tap-test',
        }).content;
    }

    const compiled = ts.transpileModule(source, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    const exports = {};
    vm.runInNewContext(compiled, {
        exports,
        require: (name) => dependencies[name] ?? {},
        ...globals,
    });

    return exports;
}

function setup() {
    const timers = new Map();
    const cleanups = [];
    let nextTimer = 0;
    const globals = {
        setTimeout: (fn) => {
            timers.set(++nextTimer, fn);

            return nextTimer;
        },
        clearTimeout: (id) => timers.delete(id),
        window: { getSelection: () => '' },
    };
    const dependencies = {
        vue: { ...vue, onBeforeUnmount: (fn) => cleanups.push(fn) },
    };
    const { useDoubleTap } = load(
        '../../resources/js/composables/useDoubleTap.ts',
        dependencies,
        globals,
    );
    const mobile = vue.ref(true);
    const visits = [];
    Object.assign(dependencies, {
        '@vueuse/core': { useMediaQuery: () => mobile },
        '@inertiajs/vue3': {
            usePage: () => ({ url: '/', props: { filters: { author: '' } } }),
            router: { visit: (url, options) => visits.push({ url, options }) },
        },
        '@/composables/useDoubleTap': { useDoubleTap },
        '@/actions/App/Http/Controllers/HeartController': {
            toggle: (id) => ({ url: `/posts/${id}/heart`, method: 'post' }),
        },
        '@/routes/posts': { show: { url: (id) => `/posts/${id}` } },
    });

    return {
        useDoubleTap,
        mobile,
        visits,
        component(path, props, emit = () => {}) {
            return load(
                `../../resources/js/components/${path}.vue`,
                dependencies,
                globals,
            ).default.setup(props, { expose() {}, emit });
        },
        flush() {
            for (const [id, callback] of Array.from(timers)) {
                timers.delete(id);
                callback();
            }
        },
        dispose() {
            cleanups.forEach((fn) => fn());
        },
    };
}

const tap = (timeStamp = 100, extra = {}) => ({
    detail: 1,
    pointerType: 'touch',
    clientX: 100,
    clientY: 100,
    timeStamp,
    target: { closest: () => false },
    preventDefault() {},
    ...extra,
});

test('a single tap opens normally, while a nearby double tap only likes', () => {
    const env = setup();
    let singles = 0;
    let doubles = 0;
    const gesture = env.useDoubleTap({
        enabled: () => true,
        onDoubleTap: () => doubles++,
    });
    const single = () => singles++;
    gesture.handleTap(tap(), single);
    assert.equal(singles, 0);
    env.flush();
    assert.equal(singles, 1);
    gesture.handleTap(tap(1000), single);
    gesture.handleTap(tap(1150, { clientX: 110 }), single);
    env.flush();
    assert.equal(singles, 1);
    assert.equal(doubles, 1);
    env.dispose();
});

test('distant taps are separate, and scrolling or leaving cancels pending navigation', () => {
    const env = setup();
    let singles = 0;
    let doubles = 0;
    const gesture = env.useDoubleTap({
        enabled: () => true,
        onDoubleTap: () => doubles++,
    });
    gesture.handleTap(tap(), () => singles++);
    gesture.handleTap(tap(200, { clientX: 200 }), () => singles++);
    env.flush();
    assert.equal(singles, 2);
    assert.equal(doubles, 0);
    gesture.handleTap(tap(1000), () => singles++);
    gesture.cancelTap();
    env.flush();
    gesture.handleTap(tap(2000), () => singles++);
    env.dispose();
    env.flush();
    assert.equal(singles, 2);
});

test('keyboard, mouse, and disabled gestures keep immediate single-tap behaviour', () => {
    const env = setup();
    let singles = 0;
    let doubles = 0;
    let enabled = true;
    const gesture = env.useDoubleTap({
        enabled: () => enabled,
        onDoubleTap: () => doubles++,
    });
    gesture.handleTap(tap(100, { detail: 0 }), () => singles++);
    gesture.handleTap(tap(200, { pointerType: 'mouse' }), () => singles++);
    enabled = false;
    gesture.handleTap(tap(300), () => singles++);
    assert.equal(singles, 3);
    assert.equal(doubles, 0);
    env.dispose();
});

test('double tapping a post likes once, preserves scroll, and never removes an existing like', () => {
    const env = setup();
    const props = vue.reactive({
        post: { id: 7, type: 'Memory', heartedByViewer: false },
    });
    const card = env.component('TimelineCard', props);
    card.card.value = { getBoundingClientRect: () => ({ left: 10, top: 20 }) };
    card.openPost(tap());
    card.openPost(tap(200));
    assert.equal(env.visits.length, 1);
    const { url, options } = env.visits[0];
    assert.equal(url.url, '/posts/7/heart');
    assert.equal(options.preserveScroll, true);
    assert.equal(card.heartProcessing.value, true);
    card.likePost(tap(300));
    card.togglePostHeart();
    assert.equal(
        env.visits.length,
        1,
        'in-flight like prevents additional toggles',
    );
    props.post.heartedByViewer = true;
    options.onSuccess();
    options.onFinish();
    assert.equal(card.heartFeedback.value, true);
    assert.equal(card.heartPoint.value.x, 90);
    card.likePost(tap(500));
    assert.equal(env.visits.length, 1, 'already liked post is not toggled');
    env.flush();
    assert.equal(card.heartFeedback.value, false);
    env.dispose();
});

test('single taps open a post; controls, letters, and desktop do not receive gesture likes', () => {
    const env = setup();
    const props = vue.reactive({ post: { id: 7, type: 'Memory' } });
    const card = env.component('TimelineCard', props);
    card.openPost(tap());
    env.flush();
    assert.equal(env.visits[0].url, '/posts/7');
    env.visits.length = 0;
    card.openPost(tap(1000, { target: { closest: () => true } }));
    card.openPost(tap(1100, { target: { closest: () => true } }));
    env.flush();
    assert.equal(env.visits.length, 0);
    props.post.type = 'Letter';
    card.likePost(tap());
    card.openPost(tap());
    assert.equal(env.visits.length, 1, 'letters still open immediately');
    env.visits.length = 0;
    props.post.type = 'Memory';
    env.mobile.value = false;
    card.likePost(tap());
    card.openPost(tap());
    env.flush();
    assert.equal(env.visits.length, 0);
    env.dispose();
});

test('photo single taps open the viewer and double taps emit a like without opening it', () => {
    const env = setup();
    const emitted = [];
    const media = env.component(
        'TimelineMedia',
        { post: { type: 'Memory', images: [], files: [] } },
        (...args) => emitted.push(args),
    );
    const image = { id: 1 };
    media.handleTap(tap(), () => media.openImage(image));
    env.flush();
    assert.equal(media.selectedImage.value.id, 1);
    media.closeImage();
    media.handleTap(tap(1000), () => media.openImage(image));
    media.handleTap(tap(1150), () => media.openImage(image));
    env.flush();
    assert.equal(media.selectedImage.value, null);
    assert.equal(emitted.length, 1);
    assert.equal(emitted[0][0], 'doubleTap');
    env.dispose();
});
