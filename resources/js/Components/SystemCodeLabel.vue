<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import JsBarcode from 'jsbarcode';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePage } from '@inertiajs/vue3';
import { stockLabelDetails } from '@/utils/stockLabels';

const props = defineProps({
    show: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    product: { type: Object, default: null },
    autoPrint: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const label = ref(null);
const error = ref('');
const preparing = ref(false);
const page = usePage();
const labels = computed(() => props.items.map(item => stockLabelDetails(item, props.product, page.props.settings)));
const labelRows = computed(() => {
    const rows = [];
    for (let index = 0; index < labels.value.length; index += 3) {
        rows.push(labels.value.slice(index, index + 3));
    }
    return rows;
});

let renderId = 0;
watch([() => props.show, () => props.items], async () => {
    const currentRender = ++renderId;
    error.value = '';
    preparing.value = false;
    if (!props.show) return;
    preparing.value = true;
    await nextTick();
    if (!label.value || !props.show || currentRender !== renderId) return;
    try {
        let count = 0;
        for (const svg of label.value.querySelectorAll('svg[data-system-code]')) {
            JsBarcode(svg, svg.dataset.systemCode, {
                format: 'CODE128', width: 2, height: 36,
                displayValue: true, fontSize: 14, textMargin: 2,
                margin: 0, marginLeft: 12, marginRight: 12,
                background: '#ffffff', lineColor: '#000000',
            });
            if (++count % 50 === 0) {
                await new Promise(resolve => requestAnimationFrame(resolve));
                if (!props.show || currentRender !== renderId) return;
            }
        }
        preparing.value = false;
        if (props.autoPrint && props.items.length) await printLabel();
    } catch {
        error.value = 'One of the system codes cannot be printed as a barcode. Please check the saved codes.';
    } finally {
        if (currentRender === renderId) preparing.value = false;
    }
}, { immediate: true });

const printLabel = async () => {
    if (error.value || preparing.value || !label.value || !props.items.length) return;
    const frame = document.createElement('iframe');
    frame.title = 'System code label';
    frame.style.cssText = 'position:fixed;width:0;height:0;border:0;';
    document.body.appendChild(frame);
    const printDocument = frame.contentDocument;
    printDocument.title = `System codes — ${props.product?.name || 'All available watch stock'}`;
    const style = printDocument.createElement('style');
    style.textContent = `
        @page { margin: 5mm; }
        body { margin: 0; color: black; background: white; font-family: Arial, sans-serif; }
        .watch-label { break-inside: avoid; page-break-inside: avoid; }
    `;
    printDocument.head.appendChild(style);
    printDocument.body.appendChild(label.value.cloneNode(true));
    const cleanup = () => frame.remove();
    frame.contentWindow.addEventListener('afterprint', cleanup, { once: true });
    // Allow the cloned SVG to lay out before opening the print dialog.
    await new Promise(resolve => frame.contentWindow.requestAnimationFrame(resolve));
    frame.contentWindow.focus();
    frame.contentWindow.print();
    setTimeout(cleanup, 60000);
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900">Print System Codes</h2>
            <p class="mt-2 text-sm text-gray-600">{{ items.length }} label(s), one for each stock item with a system code. Attach each label to its matching stock unit. Scanning an available unit adds it to the POS order.</p>
            <p v-if="error" role="alert" class="mt-4 text-sm text-red-600">{{ error }}</p>
            <p v-if="preparing" role="status" class="mt-4 text-sm text-gray-600">Preparing barcodes…</p>
            <div v-show="!error" class="my-6 max-h-96 overflow-auto rounded-lg border border-gray-200 bg-white p-4">
                <div ref="label" style="width: 180mm;">
                    <div v-for="(row, rowIndex) in labelRows" :key="row[0].id"
                        style="display: grid; grid-template-columns: repeat(3, 60mm); break-inside: avoid; page-break-inside: avoid;">
                        <div v-for="(item, columnIndex) in row" :key="item.id" class="watch-label"
                            style="width: 60mm; height: 25mm; padding: 1mm; box-sizing: border-box; overflow: hidden; text-align: center; color: black; background: white;"
                            :style="{ borderRight: columnIndex < 2 ? '1px dotted #666' : 'none', borderBottom: rowIndex < labelRows.length - 1 ? '1px dotted #666' : 'none' }">
                            <p :title="item.name" style="margin: 0; height: 3mm; line-height: 3mm; font-size: 7pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ item.name }}</p>
                            <p :title="item.model_number" style="margin: 0; height: 3mm; line-height: 3mm; font-size: 7pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ item.model_number }}</p>
                            <svg :data-system-code="String(item.system_unique_id)" preserveAspectRatio="none" style="display: block; width: 56mm; height: 12mm; margin: 0 auto;" :aria-label="`System code ${item.system_unique_id}`" role="img"></svg>
                            <p style="margin: 0; height: 4mm; line-height: 4mm; font-size: 8pt; font-weight: bold; white-space: nowrap;">Price: {{ item.price_label }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mb-4 text-xs text-gray-500">Print on A4 or Letter paper at 100% / Actual size with browser headers and footers off. Each label is 6 cm wide × 2.5 cm high, arranged in three columns. Cut along the dotted lines.</p>
            <p class="mb-4 text-xs text-gray-500">Prices are current POS unit prices in MMK before checkout discounts.</p>
            <div class="flex justify-end gap-3">
                <SecondaryButton @click="emit('close')">Close</SecondaryButton>
                <PrimaryButton :disabled="preparing || !!error || !items.length" @click="printLabel">Print {{ items.length }} Labels</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
