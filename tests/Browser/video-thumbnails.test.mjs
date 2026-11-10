import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import ts from 'typescript';
import * as vue from 'vue';

const source = readFileSync(new URL('../../resources/js/components/TimelineVideo.vue', import.meta.url), 'utf8');
const compiled = ts.transpileModule(compileScript(parse(source).descriptor, {
    id: 'video-thumbnail-test', inlineTemplate: true,
}).content, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
const exports = {};
const dependencies = {
    vue,
    'lucide-vue-next': {
        Play: { render: () => vue.h('span') },
        Video: { render: () => vue.h('span') },
    },
};
vm.runInNewContext(compiled, {
    exports, require: (name) => dependencies[name] ?? {},
    setTimeout: () => 0, clearTimeout() {},
});
const component = exports.default;
const media = {
    id: 1, name: 'Family video', url: '/media/1', downloadUrl: '/media/1/download',
    thumbnailUrl: '/media/1/thumbnail', status: 'ready', size: 1024,
    mimeType: 'video/mp4', width: 640, height: 480,
};
const render = (props) => renderToString(vue.createSSRApp({ render: () => vue.h(component, props) }));

test('gallery previews render lazy images and open a player without loading a video element', async () => {
    const html = await render({ media, preview: true });
    assert.match(html, /<button[^>]*aria-label="Open video: Family video"/);
    assert.match(html, /<img[^>]*src="\/media\/1\/thumbnail"[^>]*loading="lazy"/);
    assert.doesNotMatch(html, /<video[\s>]/);
});

test('post players use thumbnail posters and retain native playback controls', async () => {
    const html = await render({ media });
    assert.match(html, /<video[^>]*poster="\/media\/1\/thumbnail"/);
    assert.match(html, /<video[^>]*controls/);
    assert.match(html, /<video[^>]*playsinline/);
    assert.doesNotMatch(html, /<video[^>]*autoplay/);
});

test('the gallery viewer requests autoplay and processing previews remain available to open', async () => {
    const html = await render({ media, autoplay: true });
    assert.match(html, /<video[^>]*autoplay/);
    const processing = await render({ media: { ...media, status: 'processing', thumbnailUrl: null }, preview: true });
    assert.match(processing, /Preparing video/);
    assert.match(processing, /<button/);
    assert.doesNotMatch(processing, /<video[\s>]/);
});
