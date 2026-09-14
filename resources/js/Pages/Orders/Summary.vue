<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import OrderFilters from '@/Components/OrderFilters.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { paymentMethods } from '@/utils/payments';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
});
const formatMoney = amount => Number(amount || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Order Summary" />
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order Summary</h2>
        </template>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-bold text-gray-900">Order Summary</h1>
            <Link :href="route('orders.index', props.filters)"><SecondaryButton>View Orders</SecondaryButton></Link>
        </div>
        <OrderFilters :filters="filters" route-name="orders.summary" />
        <section class="mb-6" aria-labelledby="order-summary-title">
            <h2 id="order-summary-title" class="text-lg font-bold text-gray-900">Payment Totals</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Money retained from {{ summary.completed_orders }} completed orders matching these filters, in the selected period. Cash change is deducted. Each split is counted under its payment methods.</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-gold-200 bg-gold-50 p-4">
                    <p class="text-sm font-medium text-gray-700">Total Received</p>
                    <p class="mt-2 text-xl font-bold text-gold-700">{{ formatMoney(summary.total_received) }} <span class="text-sm">Ks</span></p>
                </div>
                <div v-for="method in paymentMethods" :key="method.value" class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="text-sm font-medium text-gray-600">{{ method.label }}</p>
                    <p class="mt-2 text-xl font-bold text-gray-900">{{ formatMoney(summary.by_method[method.value]) }} <span class="text-sm">Ks</span></p>
                </div>
            </div>
            <p v-if="summary.by_method.other" class="mt-3 text-sm text-gray-600">Other / unclassified: {{ formatMoney(summary.by_method.other) }} Ks (included in Total Received).</p>
            <p v-if="summary.unrecorded_orders" class="mt-3 text-sm text-amber-700">{{ summary.unrecorded_orders }} completed order(s) have no recorded payment amounts and are excluded from money totals.</p>
        </section>

    </AdminLayout>
</template>
