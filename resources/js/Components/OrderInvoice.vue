<script setup>
import { computed } from 'vue';
import { orderDiscountAmount } from '@/utils/posPricing';
import { paymentMethodLabel } from '@/utils/payments';

const props = defineProps({ order: { type: Object, required: true } });
const money = value => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(value) || 0);
const lineTotal = item => Number(item.price) * Number(item.quantity);
const subtotal = computed(() => (props.order.items || []).reduce((sum, item) => sum + lineTotal(item), 0));
const discount = computed(() => Number(props.order.discount_percentage) > 0
    ? orderDiscountAmount(subtotal.value, props.order.discount_percentage)
    : 0);
const paid = computed(() => Number(props.order.amount_paid) || 0);
const balance = computed(() => Math.max(0, Number(props.order.total_amount) - paid.value));
const change = computed(() => Math.max(0, paid.value - Number(props.order.total_amount)));
const hasPayment = computed(() => props.order.amount_paid !== null && props.order.amount_paid !== undefined);
const invoiceDate = computed(() => {
    const value = props.order.order_date || props.order.created_at;
    if (!value) return '—';
    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
});
const payments = computed(() => props.order.payments?.length ? props.order.payments : []);
const paymentType = computed(() => payments.value.length
    ? payments.value.map(payment => paymentMethodLabel(payment.method)).join(' / ')
    : props.order.payment_method ? paymentMethodLabel(props.order.payment_method) : 'Not recorded');
const serials = item => (item.sold_items || []).filter(unit => unit.serial_number);
</script>

<template>
    <article class="invoice-printable" aria-label="Customer invoice">
        <header class="invoice-heading">
            <div class="invoice-brand">
                <svg class="invoice-mark" viewBox="0 0 52 82" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 2h42v13l-6 8M5 2v13l6 8M5 80h42V67l-6-8M5 80V67l6-8M26 2v15M26 65v15" />
                    <ellipse cx="26" cy="41" rx="23" ry="25" />
                    <path d="M26 21v15L16 26M26 46v15M11 58l29-33M12 24l29 34" />
                </svg>
                <div><p class="invoice-brand-name">TIME ON YOU</p><p class="invoice-tagline">Jewelry &amp; Watches Store</p></div>
            </div>
            <div class="invoice-identification">
                <h1>INVOICE</h1>
                <p class="invoice-number">#{{ order.order_number }}</p>
                <p class="invoice-date"><span>Invoice Date:</span><time>{{ invoiceDate }}</time></p>
                <p v-if="order.status !== 'completed'" class="invoice-status">{{ order.status === 'pending' ? 'Pending / COD' : order.status }}</p>
            </div>
        </header>

        <section class="invoice-customer" aria-label="Invoice recipient">
            <h2>Invoice To:</h2>
            <p class="invoice-customer-name">{{ order.customer?.name || 'Walk-in Customer' }}</p>
            <p v-if="order.customer?.phone">Phone: {{ order.customer.phone }}</p>
            <p v-if="order.customer?.email">Email: {{ order.customer.email }}</p>
            <p v-if="order.customer?.region" class="invoice-customer-address">Region: {{ order.customer.region }}</p>
            <p v-if="order.customer?.address" class="invoice-customer-address">{{ order.customer.address }}</p>
        </section>

        <div class="invoice-table-scroll">
            <table class="invoice-items">
                <colgroup><col style="width: 8%" /><col style="width: 25%" /><col style="width: 22%" /><col style="width: 17%" /><col style="width: 9%" /><col style="width: 19%" /></colgroup>
                <thead><tr><th scope="col">No</th><th scope="col" class="invoice-description">Item Description</th><th scope="col">Model Number</th><th scope="col">Price</th><th scope="col">Qty.</th><th scope="col">Total</th></tr></thead>
                <tbody>
                    <tr v-for="(item, index) in order.items" :key="item.id">
                        <td>{{ String(index + 1).padStart(2, '0') }}.</td>
                        <td class="invoice-description"><p class="invoice-item-name">{{ item.product?.name || 'Watch' }}</p><p v-if="item.product?.warranty_period" class="invoice-item-detail">{{ item.product.warranty_period }}-month warranty</p><p v-for="unit in serials(item)" :key="unit.id" class="invoice-item-detail">S/N: {{ unit.serial_number }}</p></td>
                        <td>{{ item.product?.model_number || '—' }}</td>
                        <td class="invoice-numeric">{{ money(item.price) }}</td><td>{{ item.quantity }}</td><td class="invoice-numeric">{{ money(lineTotal(item)) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="invoice-settlement" aria-label="Payment and totals">
            <div class="invoice-payment">
                <div class="invoice-payment-type"><span>Payment Type</span><span>{{ paymentType }}</span></div>
                <p v-for="(payment, index) in payments.length > 1 ? payments : []" :key="index" class="invoice-payment-detail">{{ paymentMethodLabel(payment.method) }}: {{ money(payment.amount) }} MMK</p>
                <p v-if="order.salespeople?.length" class="invoice-payment-detail">Served by: {{ order.salespeople.map(person => person.name).join(', ') }}</p>
            </div>
            <dl class="invoice-totals">
                <div><dt>Subtotal:</dt><dd>{{ money(subtotal) }}</dd></div>
                <div v-if="discount > 0"><dt>Discount<span v-if="Number(order.discount_percentage)"> ({{ Number(order.discount_percentage) }}%)</span>:</dt><dd>−{{ money(discount) }}</dd></div>
                <div><dt>Amount Paid:</dt><dd>{{ hasPayment ? money(paid) : 'Not recorded' }}</dd></div>
                <div><dt>Delivery Fee <small>(separate)</small>:</dt><dd>{{ money(order.delivery_fees) }}</dd></div>
            </dl>
        </section>

        <section class="invoice-total-block" aria-label="Grand total">
            <div class="invoice-grand-total"><span>Grand Total:</span><strong>{{ money(order.total_amount) }} MMK</strong></div>
            <div v-if="hasPayment && (balance > 0 || change > 0)" class="invoice-balance"><span>{{ balance > 0 ? 'Balance Due:' : 'Change:' }}</span><strong>{{ money(balance > 0 ? balance : change) }} MMK</strong></div>
            <p v-if="Number(order.delivery_fees) > 0" class="invoice-fee-note">Delivery fee is recorded separately and is not included in the grand total or balance above.</p>
        </section>

        <footer class="invoice-footer">
            <div class="invoice-footer-columns">
                <address class="invoice-contact">
                    <p><span class="invoice-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3 3 5c-1 7 9 17 16 16l2-4-5-3-2 3c-3-1-6-4-7-7l3-2Z" /></svg></span><span>09976199116</span></p>
                    <p><span class="invoice-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 22S4 14 4 9a8 8 0 0 1 16 0c0 5-8 13-8 13Z"/><circle cx="12" cy="9" r="2.5"/></svg></span><span>No.27, Anigar Street, Yankin Tsp, Yangon</span></p>
                    <p><span class="invoice-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="16" rx="1"/><path d="m2 5 10 8L22 5"/></svg></span><span>brandonyou.boy@gmail.com</span></p>
                    <p><span class="invoice-contact-icon invoice-facebook" aria-hidden="true">f</span><span>Time On You</span></p>
                </address>
                <div class="invoice-signature"><div></div><p>Signature</p></div>
            </div>
            <p class="invoice-thanks">Thanks for choosing us! We're thrilled to have you as part of our community.</p>
        </footer>
    </article>
</template>

<style scoped>
.invoice-printable { --invoice-ink: #282d30; box-sizing: border-box; width: 100%; min-height: 1120px; padding: 36px 44px 28px; background: #fff; color: var(--invoice-ink); font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; box-shadow: 0 4px 24px #0000000a; }
.invoice-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
.invoice-brand { display: flex; align-items: center; gap: 12px; padding-top: 8px; }
.invoice-mark { width: 36px; height: 58px; flex-shrink: 0; }
.invoice-brand-name { font-weight: 400; font-family: Georgia, 'Times New Roman', serif; font-size: 29px; line-height: 1.15; letter-spacing: -1px; white-space: nowrap; }
.invoice-tagline { font-size: 12px; margin-top: 2px; }
.invoice-identification { text-align: right; min-width: 210px; max-width: 46%; }
.invoice-identification h1 { font-size: 46px; font-weight: 800; letter-spacing: -1.5px; line-height: 1.1; margin: 0 0 10px; }
.invoice-number { font-size: 12px; overflow-wrap: anywhere; }
.invoice-date { display: flex; justify-content: space-between; gap: 14px; margin-top: 4px; font-size: 12px; }
.invoice-date span { font-weight: 600; }
.invoice-status { margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; font-size: 11px; font-weight: 700; }
.invoice-customer { margin: 94px 0 76px; max-width: 75%; overflow-wrap: anywhere; }
.invoice-customer h2 { font-size: 17px; font-weight: 600; margin: 0 0 3px; }
.invoice-customer-name { font-size: 29px; line-height: 1.25; font-weight: 700; margin-bottom: 6px; }
.invoice-customer-address { font-size: 12px; margin-top: 4px; }
.invoice-items { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
.invoice-items thead { border: 1.5px solid #222; }
.invoice-items th { padding: 15px 10px; text-transform: uppercase; font-size: 11px; font-weight: 700; }
.invoice-items td { padding: 17px 10px; vertical-align: top; overflow-wrap: anywhere; }
.invoice-items th, .invoice-items td { border: 1px solid #e6e6e6; text-align: center; }
.invoice-items .invoice-description { text-align: left; padding-left: 15px; }
.invoice-item-name { font-weight: 600; text-transform: uppercase; }
.invoice-item-detail { margin-top: 4px; font-size: 10px; color: #555; text-transform: none; }
.invoice-items .invoice-numeric { font-variant-numeric: tabular-nums; }
.invoice-settlement { display: grid; grid-template-columns: 1.15fr 1fr; align-items: end; gap: 64px; margin: 27px 16px 28px; }
.invoice-payment-type { display: flex; gap: 18px; align-items: baseline; font-size: 12px; }
.invoice-payment-type > span:first-child { white-space: nowrap; font-weight: 600; }
.invoice-payment-type > span:last-child { flex: 1; min-width: 0; border-bottom: 1px solid #ddd; padding: 0 0 4px 5px; }
.invoice-payment-detail { font-size: 10px; margin-top: 7px; overflow-wrap: anywhere; }
.invoice-totals > div { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 10px; font-size: 12px; }
.invoice-totals > div:last-child { margin-bottom: 0; }
.invoice-totals dt { font-weight: 600; }
.invoice-totals dd { margin: 0; font-variant-numeric: tabular-nums; text-align: right; }
.invoice-totals small { font-weight: 400; font-size: 10px; }
.invoice-grand-total { border: 1.5px solid #222; display: flex; justify-content: space-between; align-items: baseline; gap: 18px; padding: 13px 20px; font-size: 16px; font-weight: 700; }
.invoice-grand-total strong { text-align: right; font-variant-numeric: tabular-nums; }
.invoice-balance { display: flex; justify-content: flex-end; gap: 24px; font-size: 12px; margin: 10px 20px 0; }
.invoice-fee-note { margin-top: 8px; font-size: 10px; color: #555; }
.invoice-footer { margin-top: 42px; }
.invoice-footer-columns { display: grid; grid-template-columns: 1.5fr 1fr; gap: 70px; align-items: end; }
.invoice-contact { font-style: normal; font-size: 12px; }
.invoice-contact p { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 7px; overflow-wrap: anywhere; }
.invoice-contact-icon { display: inline-flex; flex: 0 0 17px; width: 17px; height: 17px; align-items: center; justify-content: center; background: #17191a; color: #fff; padding: 2px; }
.invoice-contact-icon svg { width: 100%; height: 100%; }
.invoice-facebook { font-family: Arial, sans-serif; font-size: 19px; font-weight: bold; line-height: 1; padding-top: 4px; }
.invoice-signature { text-align: center; font-size: 13px; padding-bottom: 4px; }
.invoice-signature div { border-top: 1px solid #ddd; margin-bottom: 9px; }
.invoice-thanks { text-align: center; margin-top: 50px; font-size: 13px; }
@media screen and (max-width: 700px) {
    .invoice-printable { padding: 24px 18px; min-height: auto; }
    .invoice-heading { flex-wrap: wrap; gap: 28px; }
    .invoice-brand-name { font-size: 26px; }
    .invoice-identification { margin-left: auto; max-width: 100%; }
    .invoice-identification h1 { font-size: 36px; }
    .invoice-customer { margin: 45px 0 35px; max-width: 100%; }
    .invoice-customer-name { font-size: 25px; }
    .invoice-table-scroll { overflow-x: auto; }
    .invoice-items { min-width: 580px; }
    .invoice-settlement { grid-template-columns: 1fr; gap: 25px; margin: 25px 0; }
    .invoice-totals { width: min(100%, 310px); justify-self: end; }
    .invoice-footer-columns { grid-template-columns: 1fr; gap: 45px; }
    .invoice-signature { width: 180px; justify-self: end; }
    .invoice-thanks { margin-top: 35px; }
}
@media print {
    .invoice-printable { min-height: 0; padding: 0; box-shadow: none; font-size: 10pt; }
    .invoice-customer { margin-top: 40px; margin-bottom: 35px; }
    .invoice-items td { padding-top: 12px; padding-bottom: 12px; }
    .invoice-footer { margin-top: 28px; }
    .invoice-thanks { margin-top: 28px; }
    .invoice-heading, .invoice-customer, .invoice-items tr, .invoice-settlement, .invoice-total-block, .invoice-footer { break-inside: avoid; }
    .invoice-items thead { display: table-header-group; }
    .invoice-table-scroll { overflow: visible; }
    .invoice-contact-icon { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>
