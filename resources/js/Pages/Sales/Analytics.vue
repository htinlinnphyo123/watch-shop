<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ report: Object, filters: Object, presets: Array, today: String });
const form = useForm({ from: props.filters.from, to: props.filters.to });
watch(() => props.filters, filters => { form.from = filters.from; form.to = filters.to; });
const apply = () => form.get(route('sales.analytics'), { preserveScroll: true, preserveState: true });
const choosePeriod = period => { form.from = period.from; form.to = period.to; apply(); };
const money = value => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(value || 0));
const dateLabel = value => new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const typeLabel = type => ({ watch: 'Watches', accessory: 'Accessories', unknown: 'Product no longer available' }[type] || type);
const highestSale = computed(() => Math.max(1, ...props.report.trend.map(day => Number(day.sales))));
const chartPoint = (day, i) => ({ x: props.report.trend.length === 1 ? 350 : 12 + i / (props.report.trend.length - 1) * 676, y: 186 - Number(day.sales) / highestSale.value * 160 });
const chartPoints = computed(() => props.report.trend.map((day, i) => { const point = chartPoint(day, i); return `${point.x},${point.y}`; }).join(' '));
const maxProductUnits = computed(() => Math.max(1, ...props.report.topProducts.map(product => Number(product.units))));
const totalOrders = computed(() => props.report.statuses.reduce((sum, item) => sum + item.count, 0));
const comparison = computed(() => {
    const change = props.report.summary.change_percent;
    if (change === null) return 'No sales in the previous period';
    if (change === 0) return 'Same sales as the previous period';
    return `${Math.abs(change)}% ${change > 0 ? 'more' : 'less'} than the previous period`;
});
const ordersLink = computed(() => route('orders.index', { date_from: props.filters.from, date_to: props.filters.to }));
</script>

<template>
    <Head title="Sales Analytics" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-semibold uppercase tracking-widest text-gold-700">Sales &amp; operations</p><h1 class="mt-2 text-3xl font-bold text-gray-900">Sales Analytics</h1><p class="mt-2 text-sm text-gray-500">See how much you sold, what sold best, and how sales are changing.</p></div>
                <Link :href="ordersLink" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">View orders →</Link>
            </header>

            <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5" aria-label="Date filters">
                <div class="flex flex-wrap gap-2">
                    <button v-for="period in presets" :key="period.label" type="button" :disabled="form.processing" :aria-pressed="filters.from === period.from && filters.to === period.to" @click="choosePeriod(period)" class="rounded-full px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50" :class="filters.from === period.from && filters.to === period.to ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">{{ period.label }}</button>
                </div>
                <form @submit.prevent="apply" class="mt-4 grid gap-3 sm:flex sm:flex-wrap sm:items-end">
                    <div class="min-w-0"><label for="sales-from" class="text-xs font-semibold text-gray-600">Start date</label><input id="sales-from" v-model="form.from" type="date" required :max="today" class="mt-1 block w-full min-w-0 rounded-lg border-gray-300 text-sm focus:border-gold-500 focus:ring-gold-500"><InputError class="mt-1" :message="form.errors.from" /></div>
                    <div class="min-w-0"><label for="sales-to" class="text-xs font-semibold text-gray-600">End date</label><input id="sales-to" v-model="form.to" type="date" required :max="today" class="mt-1 block w-full min-w-0 rounded-lg border-gray-300 text-sm focus:border-gold-500 focus:ring-gold-500"><InputError class="mt-1" :message="form.errors.to" /></div>
                    <button :disabled="form.processing" class="rounded-lg bg-gold-500 px-5 py-2.5 text-sm font-semibold text-gray-900 hover:bg-gold-400 disabled:opacity-50">{{ form.processing ? 'Updating…' : 'Apply dates' }}</button>
                </form>
                <p class="mt-4 text-xs leading-5 text-gray-500">Showing {{ dateLabel(filters.from) }} – {{ dateLabel(filters.to) }}. All amounts are in MMK. <span v-if="filters.to === today">Today's sales are still in progress.</span></p>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-live="polite" :aria-busy="form.processing">
                <section class="rounded-2xl bg-gray-900 p-5 text-white shadow-sm">
                    <h2 class="text-sm text-gray-300">Total sales</h2><p class="mt-3 break-words text-2xl font-bold tabular-nums">{{ money(report.summary.total_sales) }} <span class="text-xs font-normal text-gray-400">MMK</span></p>
                    <p class="mt-3 text-xs" :class="report.summary.change_percent > 0 ? 'text-emerald-300' : report.summary.change_percent < 0 ? 'text-amber-300' : 'text-gray-300'">{{ comparison }}</p>
                </section>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><h2 class="text-sm text-gray-500">Completed orders</h2><p class="mt-3 text-2xl font-bold tabular-nums text-gray-900">{{ money(report.summary.completed_orders) }}</p><p class="mt-3 text-xs text-gray-500">Orders counted in total sales</p></section>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><h2 class="text-sm text-gray-500">Items sold</h2><p class="mt-3 text-2xl font-bold tabular-nums text-gray-900">{{ money(report.summary.items_sold) }}</p><p class="mt-3 text-xs text-gray-500">Watch and accessory units</p></section>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><h2 class="text-sm text-gray-500">Average order value</h2><p class="mt-3 break-words text-2xl font-bold tabular-nums text-gray-900">{{ money(report.summary.average_sale) }} <span class="text-xs font-normal text-gray-500">MMK</span></p><p class="mt-3 text-xs text-gray-500">Total sales ÷ completed orders</p></section>
            </div>

            <div class="grid items-start gap-6 lg:grid-cols-3">
                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Sales over time</h2><p class="mt-1 text-sm text-gray-500">Completed sales each day</p>
                    <div v-if="!report.summary.completed_orders" class="py-16 text-center"><p class="font-medium text-gray-700">No completed sales for these dates</p><p class="mt-2 text-sm text-gray-500">Try a longer date range to see your sales trend.</p></div>
                    <template v-else>
                        <div class="mt-5 flex justify-between text-xs text-gray-500"><span>MMK</span><span>Highest day: {{ money(Math.max(...report.trend.map(day => Number(day.sales)))) }}</span></div>
                        <svg viewBox="0 0 700 200" preserveAspectRatio="none" class="mt-2 h-44 w-full sm:h-56" role="img" aria-label="Daily sales chart. Exact daily values are in the table below.">
                            <defs><linearGradient id="sales-chart-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#c59b40" stop-opacity="0.25" /><stop offset="100%" stop-color="#c59b40" stop-opacity="0.02" /></linearGradient></defs>
                            <line v-for="y in [26, 106, 186]" :key="y" x1="12" :y1="y" x2="688" :y2="y" stroke="#e5e7eb" stroke-dasharray="4 4" />
                            <polygon v-if="report.trend.length > 1" :points="`12,186 ${chartPoints} 688,186`" fill="url(#sales-chart-fill)" />
                            <polyline :points="chartPoints" fill="none" stroke="#b1852e" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                            <circle v-for="(day, index) in report.trend" :key="day.date" :cx="chartPoint(day, index).x" :cy="chartPoint(day, index).y" :r="report.trend.length > 90 ? 1.5 : 3" fill="#b1852e"><title>{{ dateLabel(day.date) }}: {{ money(day.sales) }} MMK · {{ day.orders }} orders</title></circle>
                        </svg>
                        <div class="flex justify-between gap-3 text-xs text-gray-500"><span>{{ dateLabel(filters.from) }}</span><span>{{ dateLabel(filters.to) }}</span></div>
                        <details class="mt-5 rounded-lg border border-gray-100 p-3 text-sm"><summary class="cursor-pointer font-medium text-gray-700">View exact daily figures</summary><div class="mt-3 max-h-64 overflow-auto"><table class="w-full text-left"><thead class="text-xs text-gray-500"><tr><th class="py-2">Date</th><th class="text-right">Orders</th><th class="text-right">Sales (MMK)</th></tr></thead><tbody><tr v-for="day in report.trend" :key="day.date" class="border-t border-gray-100"><td class="py-2">{{ dateLabel(day.date) }}</td><td class="text-right tabular-nums">{{ day.orders }}</td><td class="text-right tabular-nums">{{ money(day.sales) }}</td></tr></tbody></table></div></details>
                    </template>
                    <div class="mt-5 rounded-xl bg-gray-50 p-4 text-xs leading-5 text-gray-500">Previous period: {{ dateLabel(report.summary.previous_from) }} – {{ dateLabel(report.summary.previous_to) }}<br><span class="font-medium text-gray-700">{{ money(report.summary.previous_sales) }} MMK</span> in completed sales over the same number of days.</div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Order status</h2><p class="mt-1 text-sm text-gray-500">{{ money(totalOrders) }} orders in this period</p>
                    <div class="mt-5 space-y-4"><div v-for="item in report.statuses" :key="item.status"><div class="flex justify-between text-sm"><span class="text-gray-600">{{ item.label }}</span><span class="font-semibold tabular-nums text-gray-900">{{ money(item.count) }}</span></div><div class="mt-2 h-1.5 rounded-full bg-gray-100" aria-hidden="true"><div class="h-full rounded-full" :class="item.status === 'completed' ? 'bg-emerald-500' : item.status === 'cancelled' ? 'bg-red-300' : 'bg-amber-400'" :style="{ width: `${totalOrders ? item.count / totalOrders * 100 : 0}%` }" /></div></div></div>
                    <p class="mt-5 rounded-lg bg-emerald-50 p-3 text-xs leading-5 text-emerald-800">Only completed orders count toward sales. Pending, processing, and cancelled orders are excluded.</p>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Best-selling products</h2><p class="mt-1 text-sm text-gray-500">Top 5 products by quantity sold</p>
                    <p v-if="!report.topProducts.length" class="py-12 text-center text-sm text-gray-500">No product sales recorded for these dates.</p>
                    <ol v-else class="mt-5 space-y-5"><li v-for="(product, index) in report.topProducts" :key="product.product_id ?? 'deleted'" class="flex gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gold-50 text-sm font-bold text-gold-700">{{ index + 1 }}</span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><span class="break-words text-sm font-medium text-gray-800">{{ product.name || 'Product no longer available' }}</span><span class="shrink-0 text-sm font-semibold text-gray-900">{{ money(product.units) }} sold</span></div><div class="mt-2 h-1.5 rounded-full bg-gray-100" aria-hidden="true"><div class="h-full rounded-full bg-gold-500" :style="{ width: `${Number(product.units) / maxProductUnits * 100}%` }" /></div><p class="mt-2 text-xs text-gray-500">{{ typeLabel(product.kind || 'unknown') }} · {{ money(product.orders) }} completed orders</p></div></li></ol>
                </section>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-semibold text-gray-900">What sold?</h2><p class="mt-1 text-sm text-gray-500">Units sold by product type</p><p v-if="!report.productTypes.length" class="py-12 text-center text-sm text-gray-500">No items sold in this period.</p><div v-else class="mt-5 space-y-5"><div v-for="item in report.productTypes" :key="item.type"><div class="flex justify-between gap-3 text-sm"><span class="text-gray-600">{{ typeLabel(item.type) }}</span><span class="font-semibold text-gray-900">{{ money(item.units) }}</span></div><div class="mt-2 h-2 rounded-full bg-gray-100" aria-hidden="true"><div class="h-full rounded-full bg-gray-700" :style="{ width: `${report.summary.items_sold ? Number(item.units) / report.summary.items_sold * 100 : 0}%` }" /></div></div></div></section>
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6"><div><h2 class="text-lg font-semibold text-gray-900">Latest completed sales</h2><p class="mt-1 text-sm text-gray-500">The latest 5 completed orders within your date range</p></div><Link :href="ordersLink" class="text-sm font-medium text-gold-700 hover:underline">View orders →</Link></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Date</th><th class="px-5 py-3 text-right">Sales (MMK)</th></tr></thead><tbody class="divide-y divide-gray-100"><tr v-for="order in report.recent" :key="order.id"><td class="whitespace-nowrap px-5 py-4"><Link :href="route('orders.show', order.id)" class="font-medium text-gold-700 hover:underline">{{ order.order_number }}</Link></td><td class="px-5 py-4 text-gray-700">{{ order.customer_name || 'Guest / unlinked' }}</td><td class="whitespace-nowrap px-5 py-4 text-gray-500">{{ dateLabel(order.created_at) }}</td><td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-gray-900">{{ money(order.total_amount) }}</td></tr><tr v-if="!report.recent.length"><td colspan="4" class="p-10 text-center text-gray-500">No completed orders in this period.</td></tr></tbody></table></div>
            </section>
            <details class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-500"><summary class="cursor-pointer font-medium text-gray-700">How these numbers are calculated</summary><ul class="mt-3 list-disc space-y-2 pl-5 leading-6"><li>Sales use the final completed order total after discounts. Amounts are in MMK, including guest orders and both watches and accessories.</li><li>Date filters use the order's creation date in the shop's timezone, including both selected dates. They do not use the date an order was approved.</li><li>Items sold use the quantities saved on completed order lines. Product names and types use the current product records.</li><li>Sales are different from payment receipts and profit. For cash, cards, and transfers, <Link :href="route('orders.summary', { date_from: filters.from, date_to: filters.to })" class="text-gold-700 underline">view the payment summary</Link>.</li></ul></details>
        </div>
    </AdminLayout>
</template>
