<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    order: Object,
});

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const printOrder = () => {
    window.print();
};
</script>

<style scoped>
@media print {
    /* Hide layout elements during print */
    :deep(aside), :deep(header), :deep(.no-print) {
        display: none !important;
    }
    :deep(main) {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .print-container {
        padding: 20px;
        background: white;
        color: black;
    }
}
</style>

<template>
    <Head :title="'Order #' + order.id" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order Details</h2>
        </template>

        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6 no-print">
                <Link :href="route('orders.index')" class="text-gray-500 hover:text-gray-700 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Orders
                </Link>
                <div class="space-x-2">
                    <SecondaryButton @click="printOrder">
                        Print Invoice
                    </SecondaryButton>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 print-container">
                <div class="p-8">
                    <!-- Invoice Header -->
                    <div class="flex justify-between items-start border-b border-gray-200 pb-8 mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gold-500">WATCH SHOP</h1>
                            <p class="text-gray-500 mt-2">Authentic Luxury Timepieces</p>
                        </div>
                        <div class="text-right">
                            <h2 class="text-2xl font-bold text-gray-900">INVOICE</h2>
                            <p class="text-gray-500 mt-1">#{{ order.id }}</p>
                            <p class="text-gray-500 text-sm mt-1">{{ formatDate(order.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Customer & Order Info -->
                    <div class="grid grid-cols-2 gap-8 mb-8">
                        <div>
                            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-2">Bill To</h3>
                            <div v-if="order.customer">
                                <p class="text-gray-900 font-bold text-lg">{{ order.customer.name }}</p>
                                <p class="text-gray-600">{{ order.customer.email }}</p>
                                <p class="text-gray-600">{{ order.customer.phone }}</p>
                                <p class="text-gray-600">{{ order.customer.address }}</p>
                            </div>
                            <div v-else>
                                <p class="text-gray-900 font-bold italic">Walk-in Customer</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-2">Payment Details</h3>
                            <p class="text-gray-900"><span class="font-medium text-gray-500">Method:</span> <span class="uppercase">{{ order.payment_method }}</span></p>
                             <p class="text-gray-900"><span class="font-medium text-gray-500">Sold By:</span> {{ order.user ? order.user.name : 'System' }}</p>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <table class="min-w-full mb-8">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-3 text-sm font-bold text-gray-500 uppercase">Item Description</th>
                                <th class="text-right py-3 text-sm font-bold text-gray-500 uppercase">Serial Number</th>
                                <th class="text-right py-3 text-sm font-bold text-gray-500 uppercase">Warranty</th>
                                <th class="text-right py-3 text-sm font-bold text-gray-500 uppercase">Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in order.items" :key="item.id">
                                <td class="py-4">
                                    <p class="font-bold text-gray-900">
                                        {{ item.product_item?.product?.name || 'Unknown Product' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ item.product_item?.product?.model_number }}
                                    </p>
                                </td>
                                <td class="py-4 text-right font-mono text-gray-600">
                                    {{ item.serial_number || item.product_item?.serial_number }}
                                </td>
                                <td class="py-4 text-right text-sm text-gray-600">
                                    {{ item.product_item?.product?.warranty_period }} Months
                                </td>
                                <td class="py-4 text-right font-bold text-gray-900">{{ parseInt(item.price).toLocaleString() }} Ks</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Totals -->
                    <div class="flex justify-end border-t border-gray-200 pt-8">
                        <div class="w-64 space-y-3">
                            <div class="flex justify-between text-2xl font-bold text-gold-600">
                                <span>Total</span>
                                <span>{{ parseInt(order.total_amount).toLocaleString() }} Ks</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="mt-12 text-center text-sm text-gray-500 pt-8 border-t border-gray-100">
                        <p>Thank you for your business!</p>
                        <p class="mt-1 text-xs">For warranty claims, please present this invoice and the physical watch.</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
