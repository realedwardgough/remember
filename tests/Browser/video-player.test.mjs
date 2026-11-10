import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';

const source = readFileSync(
    new URL('../../resources/js/components/VideoPlayer.vue', import.meta.url),
    'utf8',
);
const compiled = ts.transpileModule(
    compileScript(parse(source).descriptor, {
        id: 'video-player-test',
    }).content,
    {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;

function setup(savedVolume = null) {
    const mounts = [];
    const cleanups = [];
    const timers = new Map();
    const listeners = new Map();
    const storage = new Map([
        ['remember-video-volume', JSON.stringify(savedVolume)],
    ]);
    const otherVideo = {
        paused: false,
        pause() {
            this.paused = true;
        },
    };
    const video = {
        paused: true,
        ended: false,
        duration: 30,
        currentTime: 0,
        volume: 1,
        muted: false,
        async play() {
            this.paused = false;
        },
        pause() {
            this.paused = true;
        },
    };
    const document = {
        fullscreenEnabled: true,
        fullscreenElement: null,
        querySelectorAll: () => [video, otherVideo],
        addEventListener: (name, callback) => listeners.set(name, callback),
        removeEventListener: (name) => listeners.delete(name),
        async exitFullscreen() {
            this.fullscreenElement = null;
            listeners.get('fullscreenchange')?.();
        },
    };
    const container = {
        async requestFullscreen() {
            document.fullscreenElement = container;
            listeners.get('fullscreenchange')?.();
        },
    };
    const exports = {};
    vm.runInNewContext(compiled, {
        exports,
        require: (name) =>
            name === 'vue'
                ? {
                      ...vue,
                      onMounted: (fn) => mounts.push(fn),
                      onBeforeUnmount: (fn) => cleanups.push(fn),
                  }
                : {},
        document,
        localStorage: {
            getItem: (key) => storage.get(key),
            setItem: (key, value) => storage.set(key, value),
        },
        setTimeout: (fn) => {
            const id = Symbol();
            timers.set(id, fn);

            return id;
        },
        clearTimeout: (id) => timers.delete(id),
    });
    const player = exports.default.setup(
        { src: '/video.mp4', name: 'Family video' },
        { expose() {} },
    );
    vue.markRaw(video);
    vue.markRaw(container);
    player.element.value = video;
    player.container.value = container;
    mounts.forEach((fn) => fn());

    return {
        player,
        video,
        otherVideo,
        container,
        document,
        storage,
        listeners,
        timers,
        key(key, target = container) {
            player.handleKeydown({ key, target, preventDefault() {} });
        },
        flush() {
            [...timers.values()].forEach((fn) => fn());
            timers.clear();
        },
        dispose() {
            cleanups.forEach((fn) => fn());
        },
    };
}

test('playing pauses other videos, reveals controls, and clears buffering when paused', () => {
    const env = setup();
    env.video.paused = false;
    env.player.handlePlay();
    assert.equal(env.otherVideo.paused, true);
    assert.equal(env.player.playing.value, true);
    env.flush();
    assert.equal(env.player.controlsVisible.value, false);
    env.player.buffering.value = true;
    env.video.ended = true;
    env.player.handlePause();
    assert.equal(env.player.controlsVisible.value, true);
    assert.equal(env.player.buffering.value, false);
    assert.equal(env.player.ended.value, true);
    env.dispose();
});

test('keyboard playback and seeking respect video bounds and leave child controls alone', async () => {
    const env = setup();
    env.key(' ');
    await Promise.resolve();
    assert.equal(env.video.paused, false);
    env.key('ArrowLeft');
    assert.equal(env.video.currentTime, 0);
    env.video.currentTime = 28;
    env.key('ArrowRight');
    assert.equal(env.video.currentTime, 30);
    env.key('ArrowLeft', { tagName: 'INPUT' });
    assert.equal(env.video.currentTime, 30);
    env.key(' ');
    assert.equal(env.video.paused, true);
    env.dispose();
});

test('first touch reveals hidden controls, subsequent touch toggles playback', () => {
    const env = setup();
    env.video.paused = false;
    env.player.handlePlay();
    env.flush();
    env.player.handleVideoClick({ pointerType: 'touch' });
    assert.equal(env.player.controlsVisible.value, true);
    assert.equal(env.video.paused, false);
    env.player.handleVideoClick({ pointerType: 'touch' });
    assert.equal(env.video.paused, true);
    env.dispose();
});

test('saved sound preference is restored and a zero-volume video can be unmuted', () => {
    const env = setup({ volume: 0, muted: false });
    assert.equal(env.video.volume, 0);
    env.player.toggleMute();
    assert.equal(env.video.volume, 1);
    assert.equal(env.video.muted, false);
    env.player.setVolume({ target: { value: '0.4' } });
    env.player.rememberVolume();
    assert.deepEqual(JSON.parse(env.storage.get('remember-video-volume')), {
        volume: 0.4,
        muted: false,
    });
    env.dispose();
});

test('fullscreen tracks browser state and unmount releases playback, listeners, and timers', async () => {
    const env = setup();
    await env.player.toggleFullscreen();
    assert.equal(env.player.fullscreen.value, true);
    await env.player.toggleFullscreen();
    assert.equal(env.player.fullscreen.value, false);
    env.video.paused = false;
    env.player.handlePlay();
    env.dispose();
    assert.equal(env.video.paused, true);
    assert.equal(env.timers.size, 0);
    assert.equal(env.listeners.size, 0);
});

test('blocked playback keeps the play controls available without an unhandled rejection', async () => {
    const env = setup();
    env.video.play = async () => {
        throw new Error('NotAllowedError');
    };
    await env.player.togglePlayback();
    assert.equal(env.player.playing.value, false);
    assert.equal(env.player.controlsVisible.value, true);
    env.dispose();
});
