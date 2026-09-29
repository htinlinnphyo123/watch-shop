<script setup>
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
const props = defineProps({ form: Object, preOrder: Boolean });
const page = usePage();
const deliveryStatusOptions = computed(() => page.props.deliveryStatusOptions || {});
const deliveryTypeOptions = computed(() => page.props.deliveryTypeOptions || {});
const marketingChannelOptions = computed(() => page.props.marketingChannelOptions || {});
const hasLegacyValue = (field) => ['delivery_status', 'delivery_type', 'marketing_channel'].includes(field.key)
    && props.form[field.key] && !Object.hasOwn(field.options, props.form[field.key]);
const fields = computed(() => [
    { key: 'order_date', label: 'Order date', type: 'date' },
    ...(props.preOrder ? [{ key: 'model_number', label: 'Model number (if not linked to a product)' }, { key: 'price', label: 'Quoted price (MMK)', type: 'number' }, { key: 'discount_amount', label: 'Discount amount (MMK)', type: 'number' }] : [{ key: 'order_type', label: 'Order type', options: { instock: 'Instock', preorder: 'Preorder' } }]),
    { key: 'buying_type', label: 'Buying type', options: { online: 'Online', in_person: 'In person' } },
    { key: 'marketing_channel', label: 'Marketing channel', options: marketingChannelOptions.value },
    ...(props.preOrder ? [
        { key: 'paid_by', label: 'Paid by / bank or account', placeholder: 'e.g. Kpay, KBZ Special' },
        { key: 'payment_type', label: 'Payment type', options: { cash: 'Cash', mbanking: 'Mbanking', cod: 'COD', foc: 'FOC', other: 'Other' } },
        { key: 'deposit_amount', label: 'Deposit (MMK)', type: 'number' },
    ] : []),
    { key: 'delivery_type', label: 'Delivery type', options: deliveryTypeOptions.value },
    ...(props.preOrder ? [{ key: 'delivery_code', label: 'Delivery ID' }] : []),
    { key: 'delivery_status', label: 'Delivery status', options: deliveryStatusOptions.value },
    { key: 'delivery_fees', label: 'Delivery fees (MMK)', type: 'number' },
    ...(props.preOrder ? [{ key: 'money_transfer_amount', label: 'Received money from delivery (MMK)', type: 'number' }] : []),
]);
const quotedTotal = computed(() => props.form.price === '' || props.form.price == null ? null : Math.max(0, Number(props.form.price) - Number(props.form.discount_amount || 0)));
</script>

<template>
    <details class="rounded-lg border border-gray-200 bg-gray-50 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Sales &amp; delivery details (optional)</summary>
        <p v-if="preOrder" class="mt-3 text-xs text-gray-500">Include the deposit in the total paid so far. Delivery fees and received money are tracked separately. FOC is a label; set the quoted price or discount for a free order.</p>
        <p v-else class="mt-3 text-xs text-gray-500">Payment details come from the payment entries. Select the marketing channel for this order independently of the customer source. Delivery fees are recorded separately from the invoice total.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div v-for="field in fields" :key="field.key">
                <label :for="`record-${field.key}`" class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                <select v-if="field.options" :id="`record-${field.key}`" v-model="form[field.key]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">Not specified</option>
                    <option v-if="hasLegacyValue(field)" :value="form[field.key]" disabled>{{ form[field.key] }} (previous value — select an option)</option>
                    <option v-for="(label, value) in field.options" :key="value" :value="value">{{ label }}</option>
                </select>
                <input v-else :id="`record-${field.key}`" v-model="form[field.key]" :type="field.type || 'text'" :min="field.type === 'number' ? 0 : undefined" :step="field.type === 'number' ? '0.01' : undefined" :max="field.type === 'number' ? '9999999999999.99' : undefined" maxlength="255" :placeholder="field.placeholder" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                <InputError :message="form.errors[field.key]" />
                <p v-if="field.key === 'order_type'" class="mt-1 text-xs text-gray-500">Reporting label only. Choose Pending / Reserve stock / COD below to reserve selected units. Use the Pre Order menu for watches still waiting for stock.</p>
                <p v-if="hasLegacyValue(field)" class="mt-1 text-xs text-amber-700">Choose a listed option or Not specified before saving.</p>
            </div>
        </div>
        <p v-if="preOrder && quotedTotal !== null" class="mt-4 text-sm text-gray-700">Total Payment (price after discount): {{ quotedTotal.toLocaleString() }} MMK · Opening (after deposit): {{ Math.max(0, quotedTotal - Number(form.deposit_amount || 0)).toLocaleString() }} MMK</p>
    </details>
</template>
