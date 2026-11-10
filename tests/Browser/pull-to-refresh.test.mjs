import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);

function loadComponent(path, dependencies, globals = {}) {
    const { descriptor } = parse(
        readFileSync(new URL(path, import.meta.url), 'utf8'),
    );
    const { content } = compileScript(descriptor, { id: 'refresh-test' });
    const compiled = ts.transpileModule(content, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    const exports = {};

    vm.runInNewContext(compiled, {
        exports,
        require: (name) =>
            dependencies[name] ?? (name === 'vue' ? require('vue') : {}),
        ...globals,
    });

    return exports.default;
}

function gesture(refresh = async () => {}) {
    const handlers = {};
    const mobile = vue.ref(true);
    const props = vue.reactive({ refresh, disabled: false });
    const scope = vue.effectScope();
    let cleanup;
    const style = { overscrollBehaviorY: 'auto' };
    const window = { scrollY: 0 };
    const timers = new Map();
    const component = loadComponent(
        '../../resources/js/components/PullToRefresh.vue',
        {
            vue: {
                ...vue,
                onBeforeUnmount: (fn) => {
                    cleanup = fn;
                },
            },
            '@vueuse/core': {
                useMediaQuery: () => mobile,
                useEventListener: (_, name, handler) => {
                    handlers[name] = handler;
                },
            },
        },
        {
            document: { documentElement: { style } },
            window,
            getComputedStyle: (element) => element.style ?? {},
            setTimeout: (fn) => {
                const id = timers.size + 1;
                timers.set(id, fn);

                return id;
            },
            clearTimeout: (id) => timers.delete(id),
        },
    );
    const state = scope.run(() => component.setup(props, { expose() {} }));
    const target = { closest: () => null, parentElement: null };
    const event = (x, y, extra = {}) => ({
        target,
        touches: [{ clientX: x, clientY: y }],
        cancelable: true,
        preventDefault() {
            this.prevented = true;
        },
        ...extra,
    });

    return {
        state,
        props,
        mobile,
        window,
        style,
        handlers,
        timers,
        event,
        pull(x = 100, y = 250, startExtra = {}) {
            handlers.touchstart(event(100, 100, startExtra));
            const move = event(x, y);
            handlers.touchmove(move);

            return move;
        },
        dispose() {
            cleanup();
            scope.stop();
        },
    };
}

test('refreshes once after a deliberate downward pull, with feedback and cleanup', async () => {
    let resolve;
    let calls = 0;
    const g = gesture(() => {
        calls++;

        return new Promise((done) => {
            resolve = done;
        });
    });
    assert.equal(g.style.overscrollBehaviorY, 'none');
    assert.equal(g.pull().prevented, true);
    assert.equal(g.state.label.value, 'Release to refresh');
    const refreshing = g.handlers.touchend();
    assert.equal(calls, 1);
    assert.equal(g.state.refreshing.value, true);
    assert.equal(g.state.label.value, 'Checking for updates…');
    g.pull();
    await g.handlers.touchend();
    assert.equal(calls, 1);
    resolve();
    await refreshing;
    assert.equal(g.state.label.value, 'Timeline updated');
    assert.equal(g.state.refreshing.value, false);

    for (const timer of g.timers.values()) {
timer();
}

    assert.equal(g.state.indicatorHeight.value, 0);
    g.dispose();
    assert.equal(g.style.overscrollBehaviorY, 'auto');
});

test('short pulls retract and cancelled, horizontal, upward, and multitouch gestures do not refresh', async () => {
    let calls = 0;
    const g = gesture(async () => {
        calls++;
    });
    g.pull(100, 150);
    assert.equal(g.state.label.value, 'Pull to refresh');
    await g.handlers.touchend();
    assert.equal(g.state.indicatorHeight.value, 0);

    for (const [x, y] of [
        [250, 110],
        [100, 50],
    ]) {
        g.pull(x, y);
        await g.handlers.touchend();
    }

    g.pull();
    g.handlers.touchcancel();
    await g.handlers.touchend();
    g.pull();
    g.handlers.touchmove(g.event(100, 260, { touches: [{}, {}] }));
    await g.handlers.touchend();
    assert.equal(calls, 0);
    g.dispose();
});

test('only handles mobile gestures at the top, outside controls and nested scrollers', async () => {
    let calls = 0;
    const g = gesture(async () => {
        calls++;
    });
    g.window.scrollY = 20;
    g.pull();
    await g.handlers.touchend();
    g.window.scrollY = 0;
    g.mobile.value = false;
    await vue.nextTick();
    assert.equal(g.style.overscrollBehaviorY, 'auto');
    g.pull();
    await g.handlers.touchend();
    g.mobile.value = true;
    g.props.disabled = true;
    g.pull();
    await g.handlers.touchend();
    g.props.disabled = false;
    await vue.nextTick();
    g.pull(100, 250, { target: { closest: () => ({}) } });
    await g.handlers.touchend();
    g.pull(100, 250, {
        target: {
            closest: () => null,
            parentElement: null,
            style: { overflowX: 'auto' },
            scrollWidth: 400,
            clientWidth: 200,
        },
    });
    await g.handlers.touchend();
    assert.equal(calls, 0);
    g.dispose();
});

test('failed refreshes show a retry message and allow another pull', async () => {
    let calls = 0;
    const g = gesture(async () => {
        if (++calls === 1) {
throw new Error('offline');
}
    });
    g.pull();
    await g.handlers.touchend();
    assert.match(g.state.label.value, /Couldn’t refresh/);
    assert.equal(g.state.refreshing.value, false);
    g.pull();
    await g.handlers.touchend();
    assert.equal(calls, 2);
    assert.equal(g.state.label.value, 'Timeline updated');
    g.dispose();
});

test('timeline refresh keeps filters, resets infinite scroll, and reports network failure', async () => {
    let visit;
    const filters = { search: 'birthday', tag: '', type: 'Memory', author: '' };
    const component = loadComponent('../../resources/js/pages/Home.vue', {
        '@inertiajs/vue3': {
            usePage: () => ({ props: {} }),
            router: {
                get: (url, data, options) => {
                    visit = { url, data, options };
                },
            },
        },
        '@/routes': { home: (options) => ({ url: '/', ...options }) },
    });
    const state = component.setup({ filters }, { expose() {} });
    const refresh = state.refreshTimeline();
    assert.deepEqual({ ...visit.url.query }, filters);
    assert.equal(visit.url.query.page, undefined);
    assert.deepEqual(Array.from(visit.options.only), ['timelinePosts']);
    assert.deepEqual(Array.from(visit.options.reset), ['timelinePosts']);
    assert.equal(visit.options.preserveState, true);
    assert.equal(visit.options.preserveScroll, true);
    visit.options.onSuccess();
    visit.options.onFinish();
    await refresh;
    const failure = state.refreshTimeline();
    assert.equal(visit.options.onNetworkError(), false);
    visit.options.onFinish();
    await assert.rejects(failure, /Timeline refresh failed/);
});
