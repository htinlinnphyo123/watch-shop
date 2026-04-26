<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: Object,
});

const formatDate = (d) => new Date(d).toLocaleDateString('en-US', {
    year: 'numeric', month: 'long', day: 'numeric',
});

const formatTime = (d) => new Date(d).toLocaleTimeString('en-US', {
    hour: '2-digit', minute: '2-digit',
});

const lineTotal = (item) => parseInt(item.price) * item.quantity;

const orderSubtotal = computed(() => {
    return props.order.items?.reduce((sum, item) => sum + lineTotal(item), 0) || 0;
});

const discountAmount = computed(() => {
    const total = parseInt(props.order.total_amount) || 0;
    return orderSubtotal.value > total ? orderSubtotal.value - total : 0;
});

// Only show serial numbers that exist — hide internal system IDs from customer view
const customerUnits = (soldItems) =>
    (soldItems || []).filter(u => u.serial_number);

const printOrder = () => window.print();

import { router } from '@inertiajs/vue3';

const approveOrder = () => {
    if (confirm('Are you sure you want to approve this order? Stock will be deducted automatically.')) {
        router.post(route('orders.approve', props.order.id));
    }
};
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap');

@media print {
    :root { --gold: #b8960c; }

    /* Hide everything except the invoice card */
    body > *,
    #app > * { visibility: hidden; }

    .invoice-printable,
    .invoice-printable * { visibility: visible !important; }

    .invoice-printable {
        position: fixed !important;
        top: 0; left: 0;
        width: 100%; height: auto;
        margin: 0; padding: 32px 48px;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        background: white !important;
    }

    .no-print { display: none !important; }

    /* Use printer-friendly serif for print */
    .invoice-printable { font-family: 'Inter', sans-serif !important; }
    .invoice-brand-name { font-family: 'Cormorant Garamond', serif !important; }
}
</style>

<template>
    <Head :title="'Invoice #' + order.order_number" />

    <AdminLayout>
        <div class="max-w-3xl mx-auto pb-12">

            <!-- ── Admin toolbar (hidden on print) ──────────────────────── -->
            <div class="no-print flex justify-between items-center mb-6">
                <Link
                    :href="route('orders.index')"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Orders
                </Link>
                <div class="flex gap-3">
                    <button
                        v-if="order.status === 'pending'"
                        @click="approveOrder"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gold-500 hover:bg-gold-600 text-dark-900 text-sm font-bold rounded-lg transition-colors shadow-sm"
                    >
                        Approve Order
                    </button>
                    <button
                        @click="printOrder"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-900 hover:bg-black text-white text-sm font-medium rounded-lg transition-colors shadow-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print Invoice
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 INVOICE CARD
            ══════════════════════════════════════════════════════════ -->
            <div class="invoice-printable bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden" style="font-family: 'Inter', sans-serif;">

                <!-- Gold top accent bar -->
                <div class="h-1.5" style="background: linear-gradient(90deg, #c9a96e, #e8c97e, #b8860b, #c9a96e);"></div>

                <div class="px-10 pt-10 pb-12">

                    <!-- ── Header: Brand + Invoice label ───────────────────── -->
                    <div class="flex justify-between items-start mb-10">
                        <!-- Brand -->
                        <div>
                            <div
                                class="invoice-brand-name text-4xl font-bold tracking-widest uppercase"
                                style="font-family: 'Cormorant Garamond', serif; color: #1a1a1a; letter-spacing: 0.15em;"
                            >Time On You</div>
                            <div class="text-xs tracking-[0.3em] uppercase text-gray-400 mt-1">Authentic Luxury Timepieces</div>
                        </div>
                        <!-- Invoice number -->
                        <div class="text-right">
                            <div class="text-xs font-semibold tracking-[0.2em] uppercase text-gray-400 mb-1">Invoice</div>
                            <div class="text-2xl font-bold text-gray-900">#{{ order.order_number }}</div>
                            <div class="text-sm text-gray-400 mt-1">{{ formatDate(order.created_at) }}</div>
                            <div class="text-xs text-gray-400">{{ formatTime(order.created_at) }}</div>
                        </div>
                    </div>

                    <!-- Thin gold divider -->
                    <div class="h-px mb-8" style="background: linear-gradient(90deg, #c9a96e33, #c9a96e, #c9a96e33);"></div>

                    <!-- ── Bill To / Payment Details ───────────────────────── -->
                    <div class="grid grid-cols-2 gap-8 mb-10">
                        <!-- Bill To -->
                        <div>
                            <div class="text-[10px] font-bold tracking-[0.2em] uppercase text-gray-400 mb-3">Bill To</div>
                            <template v-if="order.customer">
                                <p class="text-gray-900 font-semibold text-base leading-snug">{{ order.customer.name }}</p>
                                <p v-if="order.customer.email" class="text-gray-500 text-sm mt-0.5">{{ order.customer.email }}</p>
                                <p v-if="order.customer.phone" class="text-gray-500 text-sm">{{ order.customer.phone }}</p>
                                <p v-if="order.customer.address" class="text-gray-500 text-sm">{{ order.customer.address }}</p>
                            </template>
                            <template v-else>
                                <p class="text-gray-700 font-semibold">Walk-in Customer</p>
                            </template>
                        </div>
                        <!-- Payment info -->
                        <div class="text-right">
                            <div class="text-[10px] font-bold tracking-[0.2em] uppercase text-gray-400 mb-3">Payment Details</div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-end gap-2">
                                    <span class="text-gray-400">Method</span>
                                    <span class="font-semibold text-gray-800 capitalize">{{ order.payment_method || 'Cash' }}</span>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <span class="text-gray-400">Status</span>
                                    <span v-if="order.status === 'completed'" class="inline-flex items-center gap-1 font-semibold" style="color: #7c9d6f;">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Completed
                                    </span>
                                    <span v-else-if="order.status === 'pending'" class="inline-flex items-center gap-1 font-semibold text-yellow-500">
                                        Pending Approval
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 font-semibold capitalize text-gray-500">
                                        {{ order.status }}
                                    </span>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <span class="text-gray-400">Served by</span>
                                    <span class="font-semibold text-gray-800">{{ order.user?.name || 'Staff' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Items Table ────────────────────────────────────── -->
                    <table class="w-full mb-8" style="border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #c9a96e;">
                                <th class="text-left pb-3 text-[10px] font-bold tracking-[0.15em] uppercase text-gray-400">Description</th>
                                <th class="text-center pb-3 text-[10px] font-bold tracking-[0.15em] uppercase text-gray-400 w-16">Qty</th>
                                <th class="text-right pb-3 text-[10px] font-bold tracking-[0.15em] uppercase text-gray-400 w-32">Unit Price</th>
                                <th class="text-right pb-3 text-[10px] font-bold tracking-[0.15em] uppercase text-gray-400 w-32">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in order.items"
                                :key="item.id"
                                style="border-bottom: 1px solid #f0f0f0;"
                            >
                                <!-- Description column -->
                                <td class="py-4 pr-6 align-top">
                                    <p class="font-semibold text-gray-900 text-sm leading-snug">
                                        {{ item.product?.name || 'Watch' }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        <span v-if="item.product?.model_number">{{ item.product.model_number }}</span>
                                        <span v-if="item.product?.warranty_period" class="ml-2">
                                            · {{ item.product.warranty_period }}-month warranty
                                        </span>
                                    </p>

                                    <!-- Serial numbers (customer-visible units) -->
                                    <div
                                        v-if="customerUnits(item.sold_items).length > 0"
                                        class="mt-2 space-y-0.5"
                                    >
                                        <div
                                            v-for="unit in customerUnits(item.sold_items)"
                                            :key="unit.id"
                                            class="text-[11px] text-gray-400"
                                        >
                                            <span class="font-medium text-gray-500">S/N:</span>
                                            <span class="font-mono ml-1 text-gray-600">{{ unit.serial_number }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Qty -->
                                <td class="py-4 text-center align-top">
                                    <span class="text-sm font-medium text-gray-700">{{ item.quantity }}</span>
                                </td>

                                <!-- Unit Price -->
                                <td class="py-4 text-right align-top">
                                    <span class="text-sm text-gray-600">{{ parseInt(item.price).toLocaleString() }} Ks</span>
                                </td>

                                <!-- Line total -->
                                <td class="py-4 text-right align-top">
                                    <span class="text-sm font-bold text-gray-900">{{ lineTotal(item).toLocaleString() }} Ks</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- ── Totals ──────────────────────────────────────────── -->
                    <div class="flex justify-end mb-10">
                        <div class="w-64">
                            <!-- Subtotal rows (extend here if you add tax/discount later) -->
                            <div class="flex justify-between text-sm text-gray-500 mb-2">
                                <span>Subtotal</span>
                                <span>{{ orderSubtotal.toLocaleString() }} Ks</span>
                            </div>
                            <div v-if="discountAmount > 0" class="flex justify-between text-sm text-red-500 mb-2">
                                <span>Discount</span>
                                <span>-{{ discountAmount.toLocaleString() }} Ks</span>
                            </div>
                            <!-- Divider -->
                            <div class="h-px my-3" style="background: #c9a96e;"></div>
                            <!-- Grand total -->
                            <div class="flex justify-between items-baseline">
                                <span class="text-sm font-bold uppercase tracking-wider text-gray-900">Total</span>
                                <span class="text-2xl font-bold" style="color: #b8860b;">
                                    {{ parseInt(order.total_amount).toLocaleString() }} Ks
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Thin gold divider -->
                    <div class="h-px mb-8" style="background: linear-gradient(90deg, #c9a96e33, #c9a96e, #c9a96e33);"></div>

                    <!-- ── Footer ──────────────────────────────────────────── -->
                    <div class="text-center space-y-2">
                        <p
                            class="invoice-brand-name text-lg font-semibold text-gray-700"
                            style="font-family: 'Cormorant Garamond', serif; letter-spacing: 0.08em;"
                        >Thank you for your purchase.</p>
                        <p class="text-xs text-gray-400 leading-relaxed max-w-sm mx-auto">
                            For warranty service, please retain this invoice and present it along with your timepiece at any authorised service centre.
                        </p>
                        <div class="flex justify-center gap-1 pt-2">
                            <div class="w-1 h-1 rounded-full bg-gray-200"></div>
                            <div class="w-4 h-1 rounded-full" style="background: #c9a96e;"></div>
                            <div class="w-1 h-1 rounded-full bg-gray-200"></div>
                        </div>
                    </div>
                </div>

                <!-- Gold bottom accent bar -->
                <div class="h-1.5" style="background: linear-gradient(90deg, #c9a96e, #e8c97e, #b8860b, #c9a96e);"></div>
            </div>
        </div>
    </AdminLayout>
</template>
