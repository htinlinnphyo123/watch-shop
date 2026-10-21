<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close', 'prepared']);
const search = ref('');
const result = ref({ data: [], current_page: 1, last_page: 1, total: 0 });
const selected = ref({});
const selection = computed(() => Object.values(selected.value));
const loading = ref(false);
const printing = ref(false);
const error = ref('');
let requestId = 0;
let timer;

const load = async (page = 1) => {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('products.label-items'), { params: { search: search.value, page } });
        if (id === requestId) result.value = data;
    } catch {
        if (id === requestId) {
            result.value = { data: [], current_page: 1, last_page: 1, total: 0 };
            error.value = 'Could not load stock. Try searching again or reopen this window.';
        }
    } finally { if (id === requestId) loading.value = false; }
};
watch(search, () => {
    clearTimeout(timer);
    ++requestId;
    loading.value = true;
    timer = setTimeout(() => { if (props.show) load(); }, 250);
});
watch(() => props.show, show => {
    clearTimeout(timer);
    if (show) load();
    else { ++requestId; loading.value = false; }
});
onBeforeUnmount(() => { clearTimeout(timer); ++requestId; });

const toggle = (item, checked) => {
    if (!checked) { delete selected.value[item.id]; return; }
    if (selection.value.length >= 1000) { error.value = 'Print up to 1,000 labels at a time.'; return; }
    selected.value[item.id] = item;
};
const selectPage = () => result.value.data.forEach(item => toggle(item, true));
const prepare = async () => {
    if (printing.value || !selection.value.length) return;
    printing.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('products.labels'), { item_ids: selection.value.map(item => item.id) });
        emit('prepared', data.items);
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || 'Could not prepare selected labels. Please try again.';
    } finally { printing.value = false; }
};
</script>

<template>
    <Modal :show="show" max-width="3xl" :closeable="!printing" @close="emit('close')">
        <section class="p-6" aria-labelledby="label-picker-title">
            <h2 id="label-picker-title" class="text-xl font-semibold text-gray-900">Select barcodes to print</h2>
            <p class="mt-2 text-sm text-gray-600">Choose individual available units from any watch model. Your selections stay selected when you search or change pages.</p>
            <label for="label-stock-search" class="mt-4 block text-sm font-medium text-gray-700">Search watches or barcodes</label>
            <input id="label-stock-search" v-model="search" :disabled="printing" type="search" maxlength="100" placeholder="Watch name, model, system code or serial number" class="mt-1 w-full rounded-md border-gray-300" />
            <p v-if="error" role="alert" class="mt-3 text-sm text-red-700">{{ error }}</p>
            <div class="my-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span role="status">{{ selection.length }} selected · {{ result.total }} matching units</span>
                <button type="button" :disabled="loading || printing || !result.data.length" @click="selectPage" class="font-semibold text-gray-700 underline disabled:opacity-40">Select this page</button>
            </div>
            <div class="max-h-64 overflow-auto rounded-lg border border-gray-200">
                <p v-if="loading" role="status" class="p-4 text-sm text-gray-500">Loading stock…</p>
                <template v-else>
                    <label v-for="item in result.data" :key="item.id" class="flex cursor-pointer items-start gap-3 border-b border-gray-100 p-3 hover:bg-gray-50">
                        <input type="checkbox" :checked="!!selected[item.id]" :disabled="printing || (!selected[item.id] && selection.length >= 1000)" @change="toggle(item, $event.target.checked)" class="mt-1 rounded border-gray-300" />
                        <span class="min-w-0 text-sm"><span class="block font-semibold">{{ item.product.name }}</span><span v-if="item.product.model_number" class="block text-gray-600">Model: {{ item.product.model_number }}</span><span class="block break-all font-mono text-xs text-gray-600">{{ item.system_unique_id || `Unit #${item.id} · code generated when printing` }}</span><span v-if="item.serial_number" class="block text-xs text-gray-500">Serial: {{ item.serial_number }}</span></span>
                    </label>
                    <p v-if="!result.data.length" class="p-4 text-sm text-gray-500">No available stock matches this search.</p>
                </template>
            </div>
            <div class="mt-3 flex items-center justify-between text-sm">
                <button type="button" :disabled="loading || printing || result.current_page <= 1" @click="load(result.current_page - 1)" class="px-3 py-2 underline disabled:opacity-40">Previous</button>
                <span>Page {{ result.current_page }} of {{ result.last_page }}</span>
                <button type="button" :disabled="loading || printing || result.current_page >= result.last_page" @click="load(result.current_page + 1)" class="px-3 py-2 underline disabled:opacity-40">Next</button>
            </div>
            <details v-if="selection.length" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm">
                <summary class="cursor-pointer font-medium">Review selected units ({{ selection.length }})</summary>
                <ul class="mt-2 max-h-40 overflow-auto divide-y divide-gray-200">
                    <li v-for="item in selection" :key="item.id" class="flex items-center justify-between gap-3 py-2"><span class="break-words">{{ item.product.name }} · {{ item.system_unique_id || `Unit #${item.id}` }}</span><button type="button" :disabled="printing" @click="toggle(item, false)" :aria-label="`Remove ${item.system_unique_id || item.id} from selection`" class="text-red-700 underline">Remove</button></li>
                </ul>
                <button type="button" :disabled="printing" @click="selected = {}" class="mt-2 text-red-700 underline">Clear selection</button>
            </details>
            <div class="mt-5 flex justify-end gap-3">
                <SecondaryButton :disabled="printing" @click="emit('close')">Close</SecondaryButton>
                <PrimaryButton :disabled="printing || !selection.length" @click="prepare">{{ printing ? 'Preparing labels…' : `Print selected (${selection.length})` }}</PrimaryButton>
            </div>
        </section>
    </Modal>
</template>
