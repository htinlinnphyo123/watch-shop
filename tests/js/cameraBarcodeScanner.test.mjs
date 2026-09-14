import test from 'node:test';
import assert from 'node:assert/strict';
import JsBarcode from 'jsbarcode';
import { BitArray, Code128Reader, NotFoundException } from '@zxing/library';
import { createCameraScanner } from '../../resources/js/utils/cameraBarcodeScanner.js';

function setup(overrides = {}) {
    const state = { trackStops: 0, decoderStops: 0, codes: [], errors: [] };
    const stream = { getTracks: () => [{ stop: () => state.trackStops++ }] };
    const controls = { stop: () => state.decoderStops++ };
    const scanner = createCameraScanner({
        getUserMedia: async () => stream,
        createReader: async () => ({
            decodeFromStream: async (_, video, callback) => {
                state.callback = callback;
                return controls;
            },
        }),
        onDetected: code => state.codes.push(code),
        onError: error => state.errors.push(error),
        ...overrides,
    });
    return { scanner, state, stream, controls };
}

test('printed Code 128 data decodes to the exact system code, including leading zeros', () => {
    for (const code of ['001234567890', '123456789012', 'ABCDEFGHIJKL']) {
        const output = {};
        JsBarcode(output, code, { format: 'CODE128' });
        const bars = '0'.repeat(20) + output.encodings.map(e => e.data).join('') + '0'.repeat(20);
        const row = new BitArray(bars.length);
        [...bars].forEach((bit, index) => { if (bit === '1') row.set(index); });
        assert.equal(new Code128Reader().decodeRow(0, row).getText(), code);
    }
});

test('a successful read stops the camera and emits only once', async () => {
    const { scanner, state, controls } = setup();
    await scanner.start({});
    state.callback({ getText: () => '001234567890' }, null, controls);
    state.callback({ getText: () => '001234567890' }, null, controls);
    assert.deepEqual(state.codes, ['001234567890']);
    assert.equal(state.trackStops, 1);
    assert.ok(state.decoderStops > 0);
});

test('frames without a barcode keep scanning, including minified error names', async () => {
    const { scanner, state } = setup();
    await scanner.start({});
    const error = new NotFoundException();
    Object.defineProperty(error, 'name', { value: 'minified' });
    state.callback(null, error);
    assert.deepEqual(state.errors, []);
    assert.equal(state.trackStops, 0);
    scanner.stop();
});

test('closing during camera permission releases a stream granted later', async () => {
    let grant;
    const { scanner, state, stream } = setup({ getUserMedia: () => new Promise(resolve => { grant = resolve; }) });
    const pending = scanner.start({});
    await Promise.resolve();
    scanner.stop();
    grant(stream);
    await pending;
    assert.equal(state.trackStops, 1);
    assert.equal(state.callback, undefined);
});

test('permission errors are reported and retry can start a fresh session', async () => {
    let denied = true;
    const error = { name: 'NotAllowedError' };
    const { scanner, state, stream } = setup({ getUserMedia: async () => {
        if (denied) throw error;
        return stream;
    } });
    await scanner.start({});
    assert.deepEqual(state.errors, [error]);
    denied = false;
    await scanner.start({});
    assert.equal(typeof state.callback, 'function');
    scanner.stop();
    assert.equal(state.trackStops, 1);
});

test('closing during decoder startup stops the decoder once it resolves', async () => {
    let finish;
    const { scanner, state, controls } = setup({ createReader: async () => ({
        decodeFromStream: () => new Promise(resolve => { finish = resolve; }),
    }) });
    const pending = scanner.start({});
    await Promise.resolve();
    await Promise.resolve();
    scanner.stop();
    finish(controls);
    await pending;
    assert.equal(state.trackStops, 1);
    assert.equal(state.decoderStops, 1);
});

test('a selected webcam is requested explicitly without a rear-camera constraint', async () => {
    let requested;
    let started;
    const { scanner, stream } = setup({
        getUserMedia: async constraints => { requested = constraints; return stream; },
        onStarted: camera => { started = camera; },
    });
    await scanner.start({}, 'usb-webcam');
    assert.deepEqual(requested.video.deviceId, { exact: 'usb-webcam' });
    assert.equal(requested.video.facingMode, undefined);
    assert.equal(requested.audio, false);
    assert.equal(started, stream);
    scanner.stop();
});

test('switching webcams releases the old stream and ignores its late results', async () => {
    const { scanner, state } = setup();
    await scanner.start({}, 'built-in');
    const previousCallback = state.callback;
    await scanner.start({}, 'usb-webcam');
    assert.equal(state.trackStops, 1);
    previousCallback({ getText: () => 'old-frame' });
    assert.deepEqual(state.codes, []);
    state.callback({ getText: () => '123456789012' });
    assert.deepEqual(state.codes, ['123456789012']);
    assert.equal(state.trackStops, 2);
});
