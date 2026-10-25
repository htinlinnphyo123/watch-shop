<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import OrderAttachments from '@/Components/OrderAttachments.vue';
import OrderInvoice from '@/Components/OrderInvoice.vue';
import { canManageOrder } from '@/utils/operationalAccess';

const props = defineProps({
    order: Object,
});
const page = usePage();
const canManage = computed(() => canManageOrder(page.props.auth.user, props.order));

const amountPaid = computed(() => parseFloat(props.order.amount_paid) || 0);
const balanceDue = computed(() => Math.max(0, (parseFloat(props.order.total_amount) || 0) + (parseFloat(props.order.delivery_fees) || 0) - (parseFloat(props.order.money_transfer_amount) || 0) - amountPaid.value));

const printOrder = () => window.print();

import { router } from '@inertiajs/vue3';

const approveOrder = () => {
    if (confirm(props.order.stock_reserved_at ? 'Approve this order? Its reserved units will be marked sold.' : 'Approve this order? Available units will be allocated and marked sold.')) {
        router.post(route('orders.approve', props.order.id));
    }
};

const showCancellationModal = ref(false);
const cancellationReasonInput = ref(null);
const cancelButton = ref(null);
const cancellationForm = useForm({ cancellation_reason: '' });

const openCancellationModal = async () => {
    cancellationForm.reset();
    cancellationForm.clearErrors();
    showCancellationModal.value = true;
    await nextTick();
    cancellationReasonInput.value?.focus();
};

const closeCancellationModal = () => {
    if (cancellationForm.processing) return;
    showCancellationModal.value = false;
    cancelButton.value?.focus();
};

const trapCancellationFocus = (event) => {
    const controls = [...event.currentTarget.querySelectorAll('textarea:not(:disabled), button:not(:disabled)')];
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
};

const cancelOrder = () => {
    if (cancellationForm.processing) return;
    cancellationForm.post(route('orders.cancel', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancellationModal.value = false;
            cancellationForm.reset();
        },
        onError: () => cancellationReasonInput.value?.focus(),
    });
};
</script>

<style>
@page { size: A4; margin: 12mm; }
@media print {
    /* Keep the invoice in normal document flow so long orders paginate. */
    body:has(.invoice-printable) * { visibility: hidden; }
    body:has(.invoice-printable) .invoice-printable,
    body:has(.invoice-printable) .invoice-printable * { visibility: visible; }
    body:has(.invoice-printable) aside,
    body:has(.invoice-printable) main > header,
    body:has(.invoice-printable) .no-print { display: none !important; }
    body:has(.invoice-printable) *:has(.invoice-printable) {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        min-height: 0 !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: white !important;
    }
}
</style>

<template>
    <Head :title="'Invoice #' + order.order_number" />

    <AdminLayout>
        <p v-if="order.source_pre_order" class="mb-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">Converted from <Link :href="route('pre-orders.index', { customer_id: order.source_pre_order.customer_id })" class="font-semibold underline">Pre Order PO-{{ order.source_pre_order.id }}</Link>. Its deposit is included once in this order.</p>
        <div class="max-w-[900px] mx-auto pb-12">

            <!-- ── Admin toolbar (hidden on print) ──────────────────────── -->
            <div class="no-print flex flex-wrap gap-4 justify-between items-center mb-6">
                <Link
                    :href="route('orders.index')"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Orders
                </Link>
                <div class="flex flex-wrap gap-3">
                    <button v-if="canManage && ['completed', 'pending'].includes(order.status)" ref="cancelButton" @click="openCancellationModal" class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700">Cancel Order</button>
                    <Link :href="route('orders.history', order.id)" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">View History</Link>
                    <Link v-if="canManage && ['completed', 'pending'].includes(order.status)" :href="route('pos.index', { order_id: order.id })" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit Order</Link>
                    <button
                        v-if="canManage && order.status === 'pending'"
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
            <p v-if="$page.props.errors.error" role="alert" class="no-print mb-4 text-sm text-red-700">{{ $page.props.errors.error }}</p>
            <section class="no-print mb-6 space-y-2 rounded-xl border border-gray-200 bg-white p-5 text-sm">
                <p><span class="font-semibold">Delivery code:</span> {{ order.delivery_code || '—' }}</p>
                <p class="break-words"><span class="font-semibold">Marketing channel:</span> {{ order.marketing_channel || '—' }}</p>
                <details><summary class="cursor-pointer font-semibold">Sales &amp; delivery details</summary><dl class="mt-3 grid gap-2 sm:grid-cols-2"><div v-for="key in ['order_date', 'order_type', 'buying_type', 'delivery_type', 'delivery_status', 'delivery_fees']" :key="key"><dt class="capitalize text-gray-500">{{ key.replaceAll('_', ' ') }}</dt><dd class="break-words">{{ key === 'order_date' ? (order[key] || '').slice(0, 10) || '—' : order[key] ?? '—' }}</dd></div></dl></details>
                <p><span class="font-semibold">Money transfer amount:</span> {{ order.money_transfer_amount == null ? '—' : Number(order.money_transfer_amount).toLocaleString() + ' Ks' }}</p>
                <p class="whitespace-pre-wrap break-words"><span class="font-semibold">Remark:</span> {{ order.remark || '—' }}</p>
                <p v-if="order.status === 'pending'" class="text-amber-700">Pending / COD · {{ balanceDue.toLocaleString() }} Ks due. {{ order.stock_reserved_at ? 'Selected units are reserved and cannot be sold elsewhere or deleted. Approval marks the same units sold.' : 'No units are reserved yet. Edit this order, select the system codes and save to reserve them.' }} Update payments through Edit Order; approval does not record a payment.</p>
                <div v-if="order.status === 'pending' && order.stock_reserved_at" class="space-y-1 text-amber-800">
                    <p v-for="line in order.items" :key="line.id">{{ line.product?.name || 'Watch' }} · Reserved system codes: <span class="font-mono">{{ (line.sold_items || []).map(unit => unit.system_unique_id || `Unit #${unit.id}`).join(', ') }}</span></p>
                </div>
                <p v-if="order.status === 'cancelled'" class="text-red-700">Cancelled. Linked reserved or sold stock was returned to inventory. Payment history is retained; refunds are handled separately.</p>
            </section>
            <p v-if="order.status === 'cancelled' && order.cancellation_reason" class="no-print mb-6 whitespace-pre-wrap break-words rounded-xl border border-red-100 bg-red-50 p-5 text-sm text-red-800"><span class="font-semibold">Cancellation Reason:</span> {{ order.cancellation_reason }}</p>
            <OrderInvoice :order="order" />
            <div class="no-print">
            <OrderAttachments :order-id="order.id" :files="order.file_uploads || []" :read-only="!canManage" />
            </div>
        </div>
        <Modal :show="showCancellationModal" max-width="lg" :closeable="!cancellationForm.processing" @close="closeCancellationModal">
            <form role="dialog" aria-modal="true" aria-labelledby="cancellation-title" aria-describedby="cancellation-description" class="p-6" @keydown.tab="trapCancellationFocus" @submit.prevent="cancelOrder">
                <h2 id="cancellation-title" class="text-lg font-semibold text-gray-900">Cancel Order</h2>
                <p id="cancellation-description" class="mt-2 text-sm text-gray-600">Reserved or sold stock will return to available inventory. Order and payment history will be kept. Arrange any refund separately.</p>
                <label for="cancellation-reason" class="mt-5 block text-sm font-medium text-gray-700">Cancellation Reason <span class="text-red-600">*</span></label>
                <textarea id="cancellation-reason" ref="cancellationReasonInput" v-model="cancellationForm.cancellation_reason" required maxlength="1000" rows="4" :disabled="cancellationForm.processing" :aria-invalid="!!cancellationForm.errors.cancellation_reason" aria-describedby="cancellation-reason-error" placeholder="Enter the reason for cancelling this order" class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500" />
                <InputError id="cancellation-reason-error" :message="cancellationForm.errors.cancellation_reason" class="mt-2" />
                <InputError :message="cancellationForm.errors.error" class="mt-2" />
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" :disabled="cancellationForm.processing" @click="closeCancellationModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-50">Keep Order</button>
                    <button type="submit" :disabled="cancellationForm.processing || !cancellationForm.cancellation_reason.trim()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">{{ cancellationForm.processing ? 'Cancelling…' : 'Confirm Cancellation' }}</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
