import assert from 'node:assert/strict';
import { File } from 'node:buffer';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';
import * as vue from 'vue';

function load(path, globals) {
    const source = readFileSync(
        new URL(`../../resources/js/${path}`, import.meta.url),
        'utf8',
    );
    const compiled = ts.transpileModule(source, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    const exports = {};
    vm.runInNewContext(compiled, { exports, ...globals });

    return exports;
}

function photoProcessor(options = {}) {
    const draws = [];
    const revoked = [];
    const encodes = [];
    let whiteBackground = false;
    const canvas = {
        width: 0,
        height: 0,
        getContext: () => ({
            drawImage: (_, x, y, width, height) =>
                draws.push({ width, height }),
            fillRect: () => {
                whiteBackground = true;
            },
        }),
        toBlob(callback, type, quality) {
            encodes.push({ type, quality });
            const actualType =
                options.jpegOnly && type === 'image/webp' ? 'image/png' : type;
            callback(
                new Blob(
                    [
                        new Uint8Array(
                            options.size?.(canvas.width, type, quality) ??
                                200_000,
                        ),
                    ],
                    { type: actualType },
                ),
            );
        },
    };
    const { prepareMessagePhoto } = load('lib/message-photos.ts', {
        File,
        URL: {
            createObjectURL: () => 'blob:photo',
            revokeObjectURL: (url) => revoked.push(url),
        },
        Image: class {
            naturalWidth = options.width ?? 4032;
            naturalHeight = options.height ?? 3024;
            async decode() {
                if (options.decodeFails) {
throw new Error('Unsupported');
}
            }
        },
        document: { createElement: () => canvas },
    });

    return {
        prepareMessagePhoto,
        draws,
        revoked,
        encodes,
        whiteBackground: () => whiteBackground,
    };
}

const original = (extra = {}) => ({
    name: 'phone-photo.jpg',
    size: 15 * 1024 * 1024,
    type: 'image/jpeg',
    lastModified: 123,
    ...extra,
});

test('large phone photos are resized before uploading and preserve their aspect ratio', async () => {
    const p = photoProcessor();
    const result = await p.prepareMessagePhoto(original());
    assert.equal(p.draws[0].width, 2048);
    assert.equal(p.draws[0].height, 1536);
    assert.equal(result.type, 'image/webp');
    assert.equal(result.name, 'phone-photo.webp');
    assert.equal(result.lastModified, 123);
    assert.equal(result.size, 200_000);
    assert.equal(p.revoked.length, 1);
});

test('48-megapixel camera photos are reduced to dimensions accepted by the server', async () => {
    const p = photoProcessor({ width: 8064, height: 6048 });
    await p.prepareMessagePhoto(original({ size: 8 * 1024 * 1024 }));
    assert.equal(p.draws[0].width, 2048);
    assert.equal(p.draws[0].height, 1536);
});

test('compression adapts to a smaller PHP upload limit rather than sending the original', async () => {
    const p = photoProcessor({
        size: (width) => (width > 1600 ? 600_000 : 120_000),
    });
    const result = await p.prepareMessagePhoto(original(), 512 * 1024);
    assert.ok(result.size < 512 * 1024);
    assert.equal(p.draws.length, 2);
    assert.equal(p.draws[1].width, 1536);
});

test('browsers without WebP encoding fall back to JPEG with a white background', async () => {
    const p = photoProcessor({ jpegOnly: true });
    const result = await p.prepareMessagePhoto(original());
    assert.equal(result.type, 'image/jpeg');
    assert.equal(result.name, 'phone-photo.jpg');
    assert.equal(p.whiteBackground(), true);
});

test('unsupported HEIC is passed to the server only if it fits the upload limit', async () => {
    const p = photoProcessor({ decodeFails: true });
    const small = original({
        type: 'image/heic',
        name: 'photo.heic',
        size: 200_000,
    });
    assert.equal(await p.prepareMessagePhoto(small, 512 * 1024), small);
    await assert.rejects(
        p.prepareMessagePhoto(
            original({ type: 'image/heic', name: 'photo.heic' }),
            512 * 1024,
        ),
        /Export it as JPEG/,
    );
    assert.equal(p.revoked.length, 2);
});

test('oversized originals and unsupported files are rejected before decoding', async () => {
    const p = photoProcessor();
    await assert.rejects(
        p.prepareMessagePhoto(original({ size: 51 * 1024 * 1024 })),
        /50 MB/,
    );
    await assert.rejects(
        p.prepareMessagePhoto(original({ type: 'image/svg+xml' })),
        /Choose a JPEG/,
    );
    assert.equal(p.encodes.length, 0);
});

test('tiny supported images are still encoded instead of depending on server HEIC support', async () => {
    const p = photoProcessor({ width: 100, height: 100, size: () => 500 });
    const result = await p.prepareMessagePhoto(
        original({ type: 'image/heic', name: 'tiny.heic', size: 1000 }),
    );
    assert.equal(result.type, 'image/webp');
});

function viewport() {
    const classes = new Set();
    const frames = new Map();
    let id = 0;
    let mounted;
    let cleanup;
    const listeners = () => ({
        handlers: new Map(),
        addEventListener(name, callback) {
            this.handlers.set(name, callback);
        },
        removeEventListener(name) {
            this.handlers.delete(name);
        },
    });
    const visualViewport = {
        ...listeners(),
        height: 700,
        offsetTop: 80,
        scale: 1,
    };
    const document = {
        ...listeners(),
        activeElement: null,
        documentElement: {
            classList: {
                add: (name) => classes.add(name),
                remove: (name) => classes.delete(name),
            },
        },
    };
    class Element {
        constructor(inWorkspace = true) {
            this.inWorkspace = inWorkspace;
        }
        matches() {
            return true;
        }
        closest() {
            return this.inWorkspace;
        }
    }
    const mobile = vue.ref(true);
    const scope = vue.effectScope();
    const { useMessagesViewport } = load('composables/useMessagesViewport.ts', {
        require: (name) =>
            name === 'vue'
                ? {
                      ...vue,
                      onMounted: (fn) => {
                          mounted = fn;
                      },
                      onBeforeUnmount: (fn) => {
                          cleanup = fn;
                      },
                  }
                : { useMediaQuery: () => mobile },
        document,
        HTMLElement: Element,
        window: { visualViewport },
        requestAnimationFrame: (fn) => {
            frames.set(++id, fn);

            return id;
        },
        cancelAnimationFrame: (key) => frames.delete(key),
    });
    const state = scope.run(useMessagesViewport);
    mounted();

    return {
        classes,
        document,
        visualViewport,
        state,
        mobile,
        Element,
        update(event = 'scroll') {
            visualViewport.handlers.get(event)();

            for (const [key, fn] of frames) {
                frames.delete(key);
                fn();
            }
        },
        dispose() {
            cleanup();
            scope.stop();
        },
    };
}

test('inbox scrolling cannot drag navigation even when the visual viewport reports an offset', () => {
    const v = viewport();
    v.update();
    assert.equal(v.classes.has('messages-mobile-shell'), true);
    assert.equal(v.state.mobileNavigationStyle.value, undefined);
    assert.equal(Object.keys(v.state.workspaceStyle.value).length, 0);
    v.dispose();
    assert.equal(v.classes.size, 0);
    assert.equal(v.visualViewport.handlers.size, 0);
    assert.equal(v.document.handlers.size, 0);
});

test('only a focused message editor enables keyboard positioning; blur and pinch zoom reset it', () => {
    const v = viewport();
    v.document.activeElement = new v.Element();
    v.visualViewport.height = 400;
    v.visualViewport.offsetTop = 30;
    v.update('resize');
    assert.equal(v.state.workspaceStyle.value['--messages-viewport'], '400px');
    assert.equal(v.state.workspaceStyle.value['--messages-offset'], '30px');
    assert.match(v.state.mobileNavigationStyle.value.top, /30px \+ 400px/);
    v.visualViewport.scale = 2;
    v.update();
    assert.equal(v.state.mobileNavigationStyle.value, undefined);
    v.visualViewport.scale = 1;
    v.document.activeElement = new v.Element(false);
    v.update();
    assert.equal(v.state.mobileNavigationStyle.value, undefined);
    v.document.activeElement = null;
    v.update();
    assert.equal(v.state.mobileNavigationStyle.value, undefined);
    v.dispose();
});

test('desktop layout is not scroll locked when crossing the mobile breakpoint', async () => {
    const v = viewport();
    v.mobile.value = false;
    await vue.nextTick();
    assert.equal(v.classes.size, 0);
    v.dispose();
});

test('HTTP 413 gets a clear server upload-limit error even when the response is HTML', async () => {
    const { messagingRequest } = load('lib/messaging.ts', {
        document: { cookie: '' },
        FormData,
        fetch: async () => ({
            ok: false,
            status: 413,
            json: async () => {
                throw new Error('HTML response');
            },
        }),
    });
    await assert.rejects(
        messagingRequest('/upload'),
        (error) =>
            error.status === 413 &&
            /server rejected this photo/.test(error.message),
    );
});
