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

function touchEvent(timeStamp, x = 100, y = 100, ended = false) {
    const point = { clientX: x, clientY: y };

    return {
        timeStamp,
        touches: ended ? [] : [point],
        changedTouches: [point],
        target: { closest: () => false },
        cancelable: true,
        preventDefault() {
            this.defaultPrevented = true;
        },
    };
}

test('real touch sequence tolerates finger jitter and ignores zero-detail synthetic mouse clicks', () => {
    const env = setup();
    const card = env.component('TimelineCard', {
        post: { id: 7, type: 'Memory', heartedByViewer: false },
    });

    for (const start of [100, 350]) {
        card.startPostTouch(touchEvent(start));
        card.handleTouchMove(touchEvent(start + 30, 103, 102));
        const end = touchEvent(start + 60, 103, 102, true);
        card.endPostTouch(end);
        assert.equal(end.defaultPrevented, true);
        // Some mobile browsers emit a click even when its detail/type looks like
        // a keyboard or mouse click. It must not bypass the touch recognizer.
        card.openPost(tap(start + 70, { detail: 0, pointerType: 'mouse' }));
    }

    env.flush();
    assert.equal(env.visits.length, 1);
    assert.equal(env.visits[0].url.url, '/posts/7/heart');
    env.dispose();
});

test('starting the second touch pauses single-tap navigation until finger lift', () => {
    const env = setup();
    let singles = 0;
    let doubles = 0;
    const gesture = env.useDoubleTap({
        enabled: () => true,
        onDoubleTap: () => doubles++,
    });
    const single = () => singles++;
    gesture.handleTouchStart(touchEvent(100));
    gesture.handleTouchEnd(touchEvent(150, 100, 100, true), single);
    gesture.handleTouchStart(touchEvent(500));
    env.flush();
    assert.equal(
        singles,
        0,
        'second touch prevents the navigation timer firing',
    );
    gesture.handleTouchEnd(touchEvent(620, 100, 100, true), single);
    env.flush();
    assert.equal(singles, 0);
    assert.equal(doubles, 1);
    env.dispose();
});

test('a single real touch opens once, and keyboard activation remains immediate', () => {
    const env = setup();
    const card = env.component('TimelineCard', {
        post: { id: 7, type: 'Memory' },
    });
    card.startPostTouch(touchEvent(100));
    card.endPostTouch(touchEvent(150, 100, 100, true));
    card.openPost(tap(160));
    assert.equal(env.visits.length, 0);
    env.flush();
    assert.equal(env.visits.length, 1);
    assert.equal(env.visits[0].url, '/posts/7');
    card.openPost(
        tap(200, { detail: 0, clientX: 0, clientY: 0, pointerType: '' }),
    );
    assert.equal(env.visits.length, 2);
    env.dispose();
});

test('scrolling, long presses, and multitouch do not become taps or open the post', () => {
    const env = setup();
    let calls = 0;
    const gesture = env.useDoubleTap({
        enabled: () => true,
        onDoubleTap: () => calls++,
    });
    const single = () => calls++;
    gesture.handleTouchStart(touchEvent(100));
    gesture.handleTouchMove(touchEvent(130, 100, 150));
    gesture.handleTouchEnd(touchEvent(180, 100, 150, true), single);
    gesture.handleTap(tap(190), single);
    gesture.handleTouchStart(touchEvent(1000));
    gesture.handleTouchEnd(touchEvent(1700, 100, 100, true), single);
    gesture.handleTouchStart(touchEvent(2000));
    gesture.handleTouchMove({ ...touchEvent(2050), touches: [{}, {}] });
    gesture.handleTouchEnd(touchEvent(2100, 100, 100, true), single);
    env.flush();
    assert.equal(calls, 0);
    env.dispose();
});

test('real photo touches like without opening the viewer, including the compatibility clicks', () => {
    const env = setup();
    const emitted = [];
    const media = env.component(
        'TimelineMedia',
        {
            post: { type: 'Memory', images: [], files: [] },
        },
        (...args) => emitted.push(args),
    );
    const open = () => media.openImage({ id: 1 });

    for (const start of [100, 350]) {
        media.handleTouchStart(touchEvent(start));
        media.handleTouchMove(touchEvent(start + 20, 102, 102));
        media.handleTouchEnd(touchEvent(start + 50, 102, 102, true), open);
        media.handleTap(tap(start + 60, { pointerType: 'mouse' }), open);
    }

    env.flush();
    assert.equal(media.selectedImage.value, null);
    assert.equal(emitted.length, 1);
    assert.equal(emitted[0][0], 'doubleTap');
    env.dispose();
});

test('video touches and compatibility clicks preserve native playback without opening or liking the post', () => {
    const env = setup();
    const card = env.component('TimelineCard', {
        post: { id: 7, type: 'Memory', heartedByViewer: false },
    });
    // Native video controls retarget their events to the video element.
    const video = {
        closest: (selector) => selector.split(',').some((part) => part.trim() === 'video'),
    };

    for (const start of [100, 350]) {
        card.cancelInteractiveTap({ target: video });
        card.startPostTouch({ ...touchEvent(start), target: video });
        const end = { ...touchEvent(start + 50, 100, 100, true), target: video };
        card.endPostTouch(end);
        assert.equal(end.defaultPrevented, undefined, 'native player behavior is preserved');
        const click = tap(start + 60, { target: video, detail: 0, pointerType: 'mouse' });
        card.openPost(click);
    }

    env.flush();
    assert.equal(env.visits.length, 0, 'player interactions neither navigate nor like');
    env.dispose();
});

test('touching video controls cancels an already pending post navigation', () => {
    const env = setup();
    const card = env.component('TimelineCard', {
        post: { id: 7, type: 'Memory', heartedByViewer: false },
    });
    card.openPost(tap());
    card.cancelInteractiveTap({
        target: {
            closest: (selector) => selector.split(',').some((part) => part.trim() === 'video'),
        },
    });
    env.flush();
    assert.equal(env.visits.length, 0);
    env.dispose();
});
