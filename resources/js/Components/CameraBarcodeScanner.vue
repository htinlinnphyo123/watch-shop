<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { createCameraScanner } from '@/utils/cameraBarcodeScanner';

const props = defineProps({ show: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'scan']);
const video = ref(null);
const error = ref('');
const starting = ref(false);
const cameras = ref([]);
const selectedCamera = ref('');
let startRequest = 0;

const refreshCameras = async () => {
    if (!props.show || !navigator.mediaDevices?.enumerateDevices) return;
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        if (props.show) cameras.value = devices.filter(device => device.kind === 'videoinput' && device.deviceId);
    } catch {
        // Camera discovery is optional; the current video stream can still scan.
    }
};

const scanner = createCameraScanner({
    getUserMedia: constraints => navigator.mediaDevices.getUserMedia(constraints),
    createReader: async () => {
        const [{ BrowserMultiFormatOneDReader }, { DecodeHintType, BarcodeFormat }] = await Promise.all([
            import('@zxing/browser'), import('@zxing/library'),
        ]);
        return new BrowserMultiFormatOneDReader(new Map([
            [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128]],
            [DecodeHintType.TRY_HARDER, true],
        ]), { delayBetweenScanAttempts: 150 });
    },
    onDetected: code => emit('scan', code),
    onStarted: camera => {
        selectedCamera.value = camera.getVideoTracks()[0]?.getSettings().deviceId || '';
        refreshCameras();
    },
    onError: cause => {
        starting.value = false;
        const messages = {
            NotAllowedError: 'Camera access was denied. Allow camera access in your browser settings, then retry.',
            NotFoundError: 'The camera was not found. Connect a webcam or choose another camera.',
            NotReadableError: 'The camera is busy or unavailable. Close other apps using it, then retry.',
            OverconstrainedError: 'The selected camera is unavailable. Choose Automatic or another camera.',
        };
        error.value = messages[cause.name] || 'Could not start scanning. Please retry or enter the system code in POS.';
    },
});

const start = async () => {
    const request = ++startRequest;
    error.value = '';
    if (!window.isSecureContext) {
        error.value = 'Use HTTPS, or localhost on this computer, to access the camera. An HTTP local-network address cannot use the camera.';
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        error.value = 'Camera access is unavailable in this browser. Try opening POS in Safari or Chrome.';
        return;
    }
    starting.value = true;
    await nextTick();
    if (request !== startRequest || !props.show || !video.value) return;
    await scanner.start(video.value, selectedCamera.value);
    if (request === startRequest) starting.value = false;
};

const close = () => {
    startRequest++;
    scanner.stop();
    emit('close');
};
const onVisibilityChange = () => {
    if (document.hidden && props.show) close();
};
watch(() => props.show, show => {
    if (show) start();
    else {
        startRequest++;
        scanner.stop();
    }
});
onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
    navigator.mediaDevices?.addEventListener?.('devicechange', refreshCameras);
});
onBeforeUnmount(() => {
    startRequest++;
    scanner.stop();
    document.removeEventListener('visibilitychange', onVisibilityChange);
    navigator.mediaDevices?.removeEventListener?.('devicechange', refreshCameras);
});
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900">Scan with Camera</h2>
            <p class="mt-2 text-sm text-gray-600">Use a phone camera, built-in webcam, or USB webcam. Show just one barcode, keep all its bars and white edges visible, and hold steady.</p>
            <div v-if="cameras.length || error" class="mt-4">
                <label for="barcode-camera" class="block text-sm font-medium text-gray-700">Camera</label>
                <select id="barcode-camera" v-model="selectedCamera" :disabled="starting" @change="start" class="mt-1 w-full rounded-md border-gray-300 text-sm text-gray-900">
                    <option value="">Automatic</option>
                    <option v-for="(camera, index) in cameras" :key="camera.deviceId" :value="camera.deviceId">{{ camera.label || `Camera ${index + 1}` }}</option>
                </select>
            </div>
            <div v-show="!error" class="relative mt-4 overflow-hidden rounded-lg bg-black">
                <video ref="video" autoplay muted playsinline class="aspect-video w-full object-contain" aria-label="Barcode camera preview"></video>
                <div aria-hidden="true" class="pointer-events-none absolute inset-x-6 top-1/3 h-1/3 rounded border-2 border-white/70"></div>
            </div>
            <p v-if="error" role="alert" class="mt-4 text-sm text-red-600">{{ error }}</p>
            <p v-else role="status" class="mt-3 text-sm text-gray-600">{{ starting ? 'Starting camera…' : 'Looking for a barcode…' }}</p>
            <p class="mt-3 text-sm text-gray-600">Scanning a phone screen with a webcam? Zoom in on one label, avoid reflections, and move the phone slowly nearer or farther until the bars look sharp.</p>
            <p class="mt-3 text-xs text-gray-500">A successful scan closes the camera and looks up the watch in this POS order.</p>
            <div class="mt-5 flex justify-end gap-3">
                <SecondaryButton v-if="error" @click="start">Retry</SecondaryButton>
                <SecondaryButton @click="close">Cancel</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
