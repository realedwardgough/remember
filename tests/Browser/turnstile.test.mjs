import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';

const source = readFileSync(
    new URL(
        '../../resources/js/components/TurnstileWidget.vue',
        import.meta.url,
    ),
    'utf8',
);
const compiled = ts.transpileModule(
    compileScript(parse(source).descriptor, { id: 'turnstile-test' }).content,
    {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;

function setup(enabled, siteKey = 'public-key', loaded = false) {
    const mounts = [];
    const cleanups = [];
    const scripts = [];
    const resets = [];
    const removals = [];
    const renders = [];
    const api = {
        render(element, options) {
            renders.push({ element, options });

            return 'widget-id';
        },
        reset(id) {
            resets.push(id);
        },
        remove(id) {
            removals.push(id);
        },
    };
    const window = loaded ? { turnstile: api } : {};
    const document = {
        querySelector: () => scripts[0] ?? null,
        createElement: () => ({
            dataset: {},
            listeners: {},
            addEventListener(name, callback) {
                this.listeners[name] = callback;
            },
        }),
        head: { append: (script) => scripts.push(script) },
    };
    const exports = {};
    vm.runInNewContext(compiled, {
        exports,
        window,
        document,
        require: (name) =>
            name === 'vue'
                ? {
                      ...vue,
                      onMounted: (fn) => mounts.push(fn),
                      onBeforeUnmount: (fn) => cleanups.push(fn),
                  }
                : {
                      usePage: () => ({
                          props: { turnstile: { enabled, siteKey } },
                      }),
                  },
    });
    const component = exports.default.setup(
        { action: 'login' },
        { expose() {} },
    );
    component.container.value = vue.markRaw({});
    mounts.forEach((fn) => fn());

    return {
        component,
        scripts,
        resets,
        removals,
        renders,
        load() {
            window.turnstile = api;
            scripts[0].listeners.load();
        },
        dispose() {
            cleanups.forEach((fn) => fn());
        },
    };
}

test('disabled Turnstile loads no external script and creates no widget', () => {
    const env = setup(false);
    assert.equal(env.scripts.length, 0);
    assert.equal(env.renders.length, 0);
    env.dispose();
});

test('enabled Turnstile renders the correct public key and action and can reset after form errors', () => {
    const env = setup(true);
    assert.equal(env.scripts.length, 1);
    assert.equal(
        env.scripts[0].src,
        'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
    );
    env.load();
    assert.equal(env.renders[0].options.sitekey, 'public-key');
    assert.equal(env.renders[0].options.action, 'login');
    env.component.reset();
    env.renders[0].options['expired-callback']();
    assert.deepEqual(env.resets, ['widget-id', 'widget-id']);
    env.renders[0].options['error-callback']();
    assert.equal(env.component.unavailable.value, true);
    env.renders[0].options.callback();
    assert.equal(env.component.unavailable.value, false);
    env.dispose();
    assert.deepEqual(env.removals, ['widget-id']);
});

test('missing configuration is visible and navigating away before script load never renders a stale widget', () => {
    const missing = setup(true, '');
    assert.equal(missing.component.unavailable.value, true);
    assert.equal(missing.scripts.length, 0);
    missing.dispose();
    const pending = setup(true);
    pending.dispose();
    pending.load();
    assert.equal(pending.renders.length, 0);
});

test('an already loaded widget API is reused without adding another script', () => {
    const env = setup(true, 'public-key', true);
    assert.equal(env.scripts.length, 0);
    assert.equal(env.renders.length, 1);
    env.dispose();
});
