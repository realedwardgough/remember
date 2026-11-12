import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';

function loadComponent(path, dependencies, globals = {}) {
    const { descriptor } = parse(
        readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8'),
    );
    const { content } = compileScript(descriptor, { id: 'notification-test' });
    const compiled = ts.transpileModule(content, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    const exports = {};
    vm.runInNewContext(compiled, {
        exports,
        require: (name) => dependencies[name] ?? (name === 'vue' ? vue : {}),
        ...globals,
    });

    return exports.default;
}

function layout(user = { id: 1, name: 'Sam' }) {
    const listeners = new Map();
    const calls = [];
    let mounted;
    let cleanup;
    let poll;
    let starts = 0;
    const page = vue.reactive({ props: { auth: { user } } });
    const target = (name) => ({
        addEventListener: (event, handler) => listeners.set(`${name}:${event}`, handler),
        removeEventListener: (event) => listeners.delete(`${name}:${event}`),
    });
    const document = { ...target('document'), visibilityState: 'visible' };
    const component = loadComponent('layouts/BaseLayout.vue', {
        vue: {
            ...vue,
            onMounted: (callback) => {
 mounted = callback; 
},
            onBeforeUnmount: (callback) => {
 cleanup = callback; 
},
        },
        '@inertiajs/vue3': {
            usePage: () => page,
            router: { reload: (options) => calls.push(options) },
            usePoll: (...args) => {
                poll = args;

                return { start: () => starts++ };
            },
        },
        '@/lib/notifications': { reconcilePushDevice: async () => {} },
        '@/lib/messaging-live': { connectMessaging: () => () => {} },
    }, { document, window: target('window') });
    component.setup({}, { expose() {} });
    mounted();

    return { listeners, calls, document, poll, starts, cleanup };
}

test('notifications refresh periodically and on return to the app, with listeners removed on unmount', () => {
    const state = layout();
    assert.equal(state.poll[0], 30_000);
    assert.equal(state.poll[2].autoStart, false);
    assert.equal(state.starts, 1);
    assert.deepEqual(Array.from(state.poll[1].only), ['unreadNotificationsCount', 'activityNotifications']);
    state.listeners.get('window:focus')();
    assert.equal(state.calls.length, 1);
    state.document.visibilityState = 'hidden';
    state.listeners.get('document:visibilitychange')();
    assert.equal(state.calls.length, 1);
    state.document.visibilityState = 'visible';
    state.listeners.get('document:visibilitychange')();
    assert.equal(state.calls.length, 2);
    state.cleanup();
    assert.equal(state.listeners.size, 0);
});

test('guest screens do not start notification polling', () => {
    const state = layout(null);
    assert.equal(state.starts, 0);
    assert.equal(state.listeners.size, 0);
    state.cleanup();
});

test('the bell uses the initial server count and reacts to later read updates', () => {
    const page = vue.reactive({ props: { unreadNotificationsCount: 7 } });
    const component = loadComponent('components/NotificationBell.vue', {
        '@inertiajs/vue3': { usePage: () => page },
    });
    const state = component.setup({}, { expose() {} });
    assert.equal(state.count.value, 7);
    page.props.unreadNotificationsCount = 0;
    assert.equal(state.count.value, 0);
});

test('post detail renders every comment so a notification can reach comments beyond the feed preview', () => {
    const comments = Array.from({ length: 8 }, (_, id) => ({ id: id + 1 }));
    const props = vue.reactive({ post: { id: 1, comments }, expanded: false });
    const component = loadComponent('components/TimelineEngagement.vue', {
        '@inertiajs/vue3': {
            usePage: () => ({ props: { filters: { author: '' } } }),
            useForm: () => ({}),
        },
    });
    const state = component.setup(props, { expose() {}, emit() {} });
    assert.equal(state.visibleComments.value.length, 3);
    props.expanded = true;
    assert.equal(state.visibleComments.value.length, 8);
    assert.equal(state.visibleComments.value[7].id, 8);
    assert.equal(state.hiddenCommentCount.value, 0);
});
