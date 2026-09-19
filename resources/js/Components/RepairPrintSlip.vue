<script setup>
import { computed } from 'vue';
import { chargeLabel, money } from '@/utils/serviceTimeline';
const props = defineProps({ service: Object, statuses: Object, decisions: Object, faults: Object, includeExpenses: Boolean, expenseTotal: [String, Number], printedOn: String });
const watch = computed(() => props.service.warranty_snapshot || {});
const expenses = computed(() => (props.service.expenses || []).filter(expense => !expense.voided_at));
const date = value => value ? new Date(value.substring(0, 10) + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not recorded';
</script>
<template>
    <article class="repair-slip">
        <header class="slip-header">
            <div><h1>Time On You</h1><p>Watch repair &amp; service</p></div>
            <div><h2>Repair #{{ service.id }}</h2><p>{{ includeExpenses ? 'Shop copy · includes internal costs' : 'Customer copy' }}</p><p>Printed: {{ printedOn }}</p></div>
        </header>
        <section class="slip-grid">
            <div><h3>Customer</h3><p><strong>{{ service.contact_name }}</strong></p><p>Phone: {{ service.contact_phone || 'Not recorded' }}</p></div>
            <div><h3>Repair information</h3><p>Date received: {{ date(service.issue_date) }}</p><p>Service: {{ service.service_type === 'warranty' ? 'Warranty service' : 'General repair / maintenance' }}</p><p>Status: <strong>{{ statuses[service.status] }}</strong></p></div>
        </section>
        <section>
            <h3>Watch details</h3><p><strong>{{ watch.product_name }}</strong></p>
            <p v-if="watch.brand || watch.model">{{ [watch.brand, watch.model].filter(Boolean).join(' · ') }}</p>
            <div class="slip-grid"><p>Serial / identifying marks: {{ watch.serial_number || 'Not recorded' }}</p><p>Barcode: {{ watch.barcode || 'Not recorded' }}</p></div>
            <p>Watch source: {{ service.watch_origin === 'external' ? 'Brought from elsewhere' : 'Time On You inventory' }}</p>
        </section>
        <section><h3>Problem reported by the customer</h3><p class="slip-text">{{ service.issue_description }}</p></section>
        <section><h3>Inspection &amp; repair details</h3><p>Cause: {{ faults[service.fault_type] || 'Not determined yet' }}</p><p class="slip-text">{{ service.diagnosis || 'Inspection findings have not been recorded yet.' }}</p><p>Repair location: {{ service.repair_provider === 'external' ? service.external_shop_name : 'Time On You' }}</p></section>
        <section v-if="service.service_type === 'warranty'">
            <h3>Warranty</h3><p>Decision: {{ decisions[service.coverage_decision] }}</p><p>Purchase date: {{ date(watch.purchase_date) }} · Warranty end: {{ date(watch.expires_on) }}</p><p v-if="watch.order_number">Purchase invoice: {{ watch.order_number }}</p><p v-if="service.decision_notes" class="slip-text">Reason: {{ service.decision_notes }}</p>
        </section>
        <section class="slip-charge"><div class="slip-header"><h3>Customer charge</h3><strong>{{ chargeLabel(service) }}</strong></div><p class="slip-text">{{ service.charge_notes || (service.billing_status === 'pending' ? 'The repair price has not been decided yet.' : 'No charge explanation recorded.') }}</p><p class="slip-small">This slip records the repair charge. It is not proof of payment.</p></section>
        <section v-if="includeExpenses">
            <h3>Shop expenses · internal use only</h3>
            <table v-if="expenses.length"><thead><tr><th>Date / paid to</th><th>Reason</th><th class="slip-amount">MMK</th></tr></thead><tbody><tr v-for="expense in expenses" :key="expense.id"><td>{{ date(expense.expense_date) }}<br>{{ expense.paid_to || 'Not recorded' }}</td><td class="slip-text">{{ expense.description }}</td><td class="slip-amount">{{ money(expense.amount) }}</td></tr></tbody></table>
            <p v-else>No expenses recorded.</p><p class="slip-total">Total shop expenses: {{ money(expenseTotal) }} MMK</p><p class="slip-small">Cancelled expenses are excluded. Shop expenses are separate from the customer charge.</p>
            <p v-if="service.repair_provider === 'external'">Shop phone: {{ service.external_shop_phone || 'Not recorded' }} · Reference: {{ service.external_reference || 'Not recorded' }}</p><p v-if="service.repair_provider === 'external' && service.provider_notes" class="slip-text">Instructions for the shop: {{ service.provider_notes }}</p>
        </section>
        <section class="slip-collection"><h3>Collection record · complete when the watch is returned</h3><p>Collected by: __________________________ &nbsp; Date: __________________</p><div class="slip-signatures"><p>Customer signature: __________________</p><p>Staff signature: __________________</p></div></section>
        <footer>Please keep this slip and quote Repair #{{ service.id }} when contacting Time On You.</footer>
    </article>
</template>
<style scoped>
.repair-slip { background: white; color: #111827; padding: 28px; font: 13px/1.5 Arial, sans-serif; overflow-wrap: anywhere; }
.slip-header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; }
h1 { font-size: 24px; font-weight: 700; } h2 { font-size: 19px; font-weight: 700; } h3 { font-size: 13px; font-weight: 700; margin-bottom: 6px; }
p { margin: 4px 0; } section { border-top: 1px solid #d1d5db; margin-top: 16px; padding-top: 14px; }
.slip-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.slip-text { white-space: pre-wrap; } .slip-small, footer { font-size: 11px; color: #4b5563; }
.slip-charge { border: 1px solid #9ca3af; padding: 14px; } .slip-charge strong { font-size: 18px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; } th, td { padding: 8px 6px; border-bottom: 1px solid #d1d5db; text-align: left; vertical-align: top; } th:last-child { width: 24%; }
.slip-amount, .slip-total { text-align: right; } .slip-total { font-weight: 700; }
.slip-signatures { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; margin-top: 28px; } footer { margin-top: 22px; border-top: 1px solid #d1d5db; padding-top: 10px; }
@media screen and (max-width: 600px) { .repair-slip { padding: 16px; } .slip-grid { grid-template-columns: 1fr; gap: 8px; } }
@media print { .repair-slip { padding: 0; font-size: 10pt; } h3 { break-after: avoid; } tr, .slip-collection { break-inside: avoid; } p { orphans: 3; widows: 3; } thead { display: table-header-group; } }
</style>
