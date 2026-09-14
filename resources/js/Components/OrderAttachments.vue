<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({ orderId: { type: Number, required: true }, files: { type: Array, default: () => [] } });
const queue = ref([]);
const busy = ref(false);
const message = ref('');
const error = ref('');
let uploadController;
let disposed = false;
let nextFileId = 0;
const accepted = '.jpg,.jpeg,.png,.gif,.webp,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip';
const readableSize = size => size >= 1048576 ? `${(size / 1048576).toFixed(1)} MB` : `${Math.ceil(size / 1024)} KB`;
const isImage = file => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.mime_type);
const fileUrl = (file, preview = false) => route('orders.files.download', { order: props.orderId, attachment: file.id, ...(preview ? { preview: 1 } : {}) });

const selectFiles = event => {
    error.value = '';
    message.value = '';
    for (const file of Array.from(event.target.files || [])) {
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        if (!accepted.split(',').includes(extension)) {
            error.value = `${file.name}: unsupported file type.`;
        } else if (!file.size || file.size > 20 * 1048576) {
            error.value = `${file.name}: choose a non-empty file up to 20 MB.`;
        } else if (props.files.length + queue.value.length >= 20) {
            error.value = 'An order can have up to 20 attachments.';
            break;
        } else {
            queue.value.push({ id: ++nextFileId, file, status: 'Ready', error: '', signed: null, uploaded: false });
        }
    }
    event.target.value = '';
};

const upload = async () => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    message.value = '';
    let uploaded = 0;
    uploadController = new AbortController();
    try {
        for (const entry of queue.value) {
            if (disposed) break;
            entry.error = '';
            try {
                entry.status = 'Preparing';
                if (!entry.signed || Date.parse(entry.signed.expires_at) <= Date.now()) {
                    const response = await axios.post(route('orders.files.presign', props.orderId), {
                        name: entry.file.name, size: entry.file.size,
                    }, { signal: uploadController.signal });
                    entry.signed = response.data;
                    entry.uploaded = false;
                }
                if (!entry.uploaded) {
                    entry.status = 'Uploading';
                    const headers = { 'Content-Type': entry.signed.content_type };
                    for (const [name, value] of Object.entries(entry.signed.headers || {})) {
                        // The browser supplies these signed headers from the URL and file body.
                        if (!['host', 'content-length'].includes(name.toLowerCase())) headers[name] = Array.isArray(value) ? value.join(', ') : value;
                    }
                    const response = await fetch(entry.signed.url, {
                        method: 'PUT', headers, body: entry.file, credentials: 'omit', signal: uploadController.signal,
                    });
                    if (!response.ok) throw new Error('Storage rejected the upload. Please retry.');
                    entry.uploaded = true;
                }
                entry.status = 'Verifying';
                await axios.post(route('orders.files.complete', { order: props.orderId, attachment: entry.signed.id }), {}, { signal: uploadController.signal });
                entry.status = 'Uploaded';
                uploaded++;
            } catch (cause) {
                if (disposed) break;
                entry.status = 'Failed';
                entry.error = Object.values(cause.response?.data?.errors || {}).flat()[0]
                    || cause.response?.data?.message || cause.message || 'Upload failed. Please retry.';
            }
        }
    } finally {
        if (!disposed) {
            queue.value = queue.value.filter(entry => entry.status !== 'Uploaded');
            if (uploaded) {
                message.value = `${uploaded} file(s) uploaded.`;
                router.reload({ only: ['order'], onFinish: () => { busy.value = false; } });
            } else busy.value = false;
        }
    }
};
onBeforeUnmount(() => { disposed = true; uploadController?.abort(); });
</script>

<template>
    <section class="no-print mt-6 rounded-xl border border-gray-200 bg-white p-6" aria-labelledby="order-attachments-title">
        <h2 id="order-attachments-title" class="text-lg font-bold text-gray-900">Attachments</h2>
        <p class="mt-1 text-sm text-gray-500">Upload payment slips, photos, or supporting documents for this order. Up to 20 files, 20 MB each.</p>
        <label :for="`order-files-${orderId}`" class="mt-4 block text-sm font-medium text-gray-700">Choose files</label>
        <input :id="`order-files-${orderId}`" name="file_upload[]" type="file" multiple :accept="accepted" :disabled="busy" @change="selectFiles" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2" />
        <p class="mt-2 text-xs text-gray-500">Images, PDF, Word, Excel, CSV, text, and ZIP files.</p>
        <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
        <p v-if="message" role="status" class="mt-3 text-sm text-green-700">{{ message }}</p>
        <ul v-if="queue.length" class="mt-4 divide-y divide-gray-100">
            <li v-for="entry in queue" :key="entry.id" class="py-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="min-w-0 break-words text-gray-800">{{ entry.file.name }} · {{ readableSize(entry.file.size) }}</span>
                    <button v-if="!busy" type="button" class="text-red-600" @click="queue = queue.filter(item => item.id !== entry.id)">Remove</button>
                </div>
                <p role="status" class="mt-1 text-gray-500">{{ entry.status }}</p>
                <p v-if="entry.error" role="alert" class="mt-1 text-red-600">{{ entry.error }}</p>
            </li>
        </ul>
        <PrimaryButton v-if="queue.length" :disabled="busy" class="mt-3" @click="upload">{{ busy ? 'Uploading…' : 'Upload Files' }}</PrimaryButton>
        <div v-if="files.length" class="mt-6 grid gap-3 sm:grid-cols-2">
            <a v-for="file in files" :key="file.id" :href="fileUrl(file)" target="_blank" rel="noopener noreferrer" class="flex min-w-0 items-center gap-3 rounded-lg border border-gray-200 p-3 hover:border-gold-500">
                <img v-if="isImage(file)" :src="fileUrl(file, true)" :alt="file.name" loading="lazy" class="h-16 w-16 shrink-0 rounded object-cover" />
                <span v-else aria-hidden="true" class="flex h-16 w-16 shrink-0 items-center justify-center rounded bg-gray-100 text-xs font-semibold text-gray-600">FILE</span>
                <span class="min-w-0"><span class="block break-words text-sm font-medium text-gray-900">{{ file.name }}</span><span class="mt-1 block text-xs text-gray-500">{{ readableSize(file.size) }} · Download</span></span>
            </a>
        </div>
        <p v-else class="mt-5 text-sm text-gray-400">No attachments yet.</p>
    </section>
</template>
