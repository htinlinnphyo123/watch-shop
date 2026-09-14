<script setup>
import { paymentMethodLabel } from '@/utils/payments';
defineProps({ snapshot: Object, title: String });
const money = value => value == null ? 'Not recorded' : `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })} Ks`;
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 min-w-0">
        <h3 class="font-semibold text-lg text-gray-900 mb-4">{{ title }}</h3>
        <dl class="grid grid-cols-2 gap-2 text-sm mb-5">
            <dt class="text-gray-500">Customer</dt><dd>{{ snapshot.customer.name }}</dd>
            <dt class="text-gray-500">Status</dt><dd class="capitalize">{{ snapshot.status }}</dd>
            <dt class="text-gray-500">Discount</dt><dd>{{ snapshot.discount_percentage }}%</dd>
            <dt class="text-gray-500">Total</dt><dd class="font-semibold">{{ money(snapshot.total_amount) }}</dd>
            <dt class="text-gray-500">Amount received</dt><dd>{{ money(snapshot.amount_paid) }}</dd>
            <dt class="text-gray-500">Change</dt><dd>{{ money(snapshot.change) }}</dd>
        </dl>
        <h4 class="text-sm font-semibold mb-2">Watches</h4>
        <ul class="divide-y divide-gray-100 mb-5">
            <li v-for="(watch, key) in snapshot.watches" :key="key" class="py-3 text-sm">
                <p class="font-medium">{{ watch.name }} <span class="text-gray-500">{{ watch.model }}</span></p>
                <p class="text-gray-600">Qty: {{ watch.quantity }} · {{ money(watch.unit_price) }} each</p>
                <p v-if="watch.system_code" class="font-mono break-all text-gray-600">Code: {{ watch.system_code }}</p>
                <p v-if="watch.serial" class="text-gray-600 break-all">Serial: {{ watch.serial }}</p>
            </li>
        </ul>
        <h4 class="text-sm font-semibold mb-2">Payments</h4>
        <p v-if="!Object.keys(snapshot.payments).length" class="text-sm text-gray-500">No payments recorded.</p>
        <p v-for="(amount, method) in snapshot.payments" :key="method" class="flex justify-between gap-3 text-sm py-1"><span>{{ paymentMethodLabel(method) }}</span><span>{{ money(amount) }}</span></p>
        <h4 class="text-sm font-semibold mt-5 mb-2">Attachments</h4>
        <p v-if="!Object.keys(snapshot.attachments).length" class="text-sm text-gray-500">No attachments.</p>
        <p v-for="file in snapshot.attachments" :key="file.id" class="text-sm text-gray-600 break-all py-1">{{ file.name }} · {{ Math.ceil(file.size / 1024) }} KB</p>
    </section>
</template>
