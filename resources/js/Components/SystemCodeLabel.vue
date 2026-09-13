<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import JsBarcode from 'jsbarcode';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    product: { type: Object, required: true },
});
const emit = defineEmits(['close']);
const label = ref(null);
const error = ref('');
const labelRows = computed(() => {
    const rows = [];
    for (let index = 0; index < props.items.length; index += 2) {
        rows.push(props.items.slice(index, index + 2));
    }
    return rows;
});

watch([() => props.show, () => props.items], async () => {
    error.value = '';
    if (!props.show) return;
    await nextTick();
    try {
        for (const svg of label.value.querySelectorAll('svg[data-system-code]')) {
            JsBarcode(svg, svg.dataset.systemCode, {
                format: 'CODE128', width: 2, height: 65,
                displayValue: true, fontSize: 18, margin: 20,
                background: '#ffffff', lineColor: '#000000',
            });
        }
    } catch {
        error.value = 'One of the system codes cannot be printed as a barcode. Please check the saved codes.';
    }
});

const printLabel = async () => {
    if (error.value || !label.value || !props.items.length) return;
    const frame = document.createElement('iframe');
    frame.title = 'System code label';
    frame.style.cssText = 'position:fixed;width:0;height:0;border:0;';
    document.body.appendChild(frame);
    const printDocument = frame.contentDocument;
    printDocument.title = `System codes — ${props.product.name}`;
    const style = printDocument.createElement('style');
    style.textContent = `
        @page { margin: 5mm; }
        body { margin: 0; color: black; background: white; font-family: Arial, sans-serif; }
        .watch-label { width: 60mm; text-align: center; break-inside: avoid; }
        .watch-label p { margin: 1mm 0; font-size: 10pt; overflow-wrap: anywhere; }
        .watch-label svg { display: block; width: 60mm; height: auto; }
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
            <p class="mt-2 text-sm text-gray-600">{{ items.length }} label(s), one for each stock item with a system code. Attach each label to its matching watch. Scanning an available watch adds it to the POS order.</p>
            <p v-if="error" role="alert" class="mt-4 text-sm text-red-600">{{ error }}</p>
            <div v-show="!error" class="my-6 max-h-96 overflow-auto rounded-lg border border-gray-200 bg-white p-4">
                <div ref="label" style="width: 100%;">
                    <div v-for="(row, rowIndex) in labelRows" :key="row[0].id"
                        style="display: grid; grid-template-columns: repeat(2, minmax(70mm, 1fr)); break-inside: avoid; page-break-inside: avoid;"
                        :style="{ borderBottom: rowIndex < labelRows.length - 1 ? '1px dotted #666' : 'none' }">
                        <div v-for="(item, columnIndex) in row" :key="item.id"
                            style="display: flex; justify-content: center; padding: 5mm; box-sizing: border-box;"
                            :style="{ borderRight: columnIndex === 0 ? '1px dotted #666' : 'none' }">
                            <div class="watch-label" style="width: 60mm; text-align: center; color: black; background: white;">
                                <p style="margin: 1mm 0; font-size: 10pt; overflow-wrap: anywhere;">{{ product.name }}</p>
                                <p v-if="product.model_number" style="margin: 1mm 0; font-size: 10pt; overflow-wrap: anywhere;">{{ product.model_number }}</p>
                                <svg :data-system-code="String(item.system_unique_id)" style="display: block; width: 60mm; height: auto;" :aria-label="`System code ${item.system_unique_id}`" role="img"></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mb-4 text-xs text-gray-500">Print on A4 or Letter paper at 100% scale with browser headers and footers off. Labels are 60 mm wide, arranged left to right in two columns. Cut along the dotted lines between rows and columns.</p>
            <div class="flex justify-end gap-3">
                <SecondaryButton @click="emit('close')">Close</SecondaryButton>
                <PrimaryButton :disabled="!!error || !items.length" @click="printLabel">Print {{ items.length }} Labels</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
