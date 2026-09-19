<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ report: Object, filters: Object, sourceOptions: Object, routeName: String });
const form = useForm({ from: props.filters.from, to: props.filters.to, rank_by: props.filters.rank_by });
const apply = () => form.get(route(props.routeName), { preserveScroll: true, preserveState: true });
const money = value => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(value || 0));
const maxSource = computed(() => Math.max(1, ...props.report.sources.map(row => Number(row.purchase_value))));
const maxTrend = computed(() => Math.max(1, ...props.report.trend.map(row => Number(row.purchase_value))));
const point = (row, index) => ({ x: 40 + index * 820 / Math.max(1, props.report.trend.length - 1), y: 180 - Number(row.purchase_value) / maxTrend.value * 140 });
const points = computed(() => props.report.trend.map((row, index) => { const p = point(row, index); return `${p.x},${p.y}`; }).join(' '));
const profileUrl = value => {
    try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : null; }
    catch { return null; }
};
const share = value => Number(props.report.summary.purchase_value) ? (Number(value) / Number(props.report.summary.purchase_value) * 100).toFixed(1) : '0.0';
</script>

<template>
    <div class="space-y-6">
        <form @submit.prevent="apply" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end gap-4">
                <div><label for="insights-from" class="block text-sm font-medium text-gray-700">From</label><input id="insights-from" v-model="form.from" type="date" required class="mt-1 rounded-lg border-gray-300 focus:border-gold-500 focus:ring-gold-500"><InputError :message="form.errors.from" /></div>
                <div><label for="insights-to" class="block text-sm font-medium text-gray-700">To</label><input id="insights-to" v-model="form.to" type="date" required class="mt-1 rounded-lg border-gray-300 focus:border-gold-500 focus:ring-gold-500"><InputError :message="form.errors.to" /></div>
                <div><label for="insights-ranking" class="block text-sm font-medium text-gray-700">Rank customers by</label><select id="insights-ranking" v-model="form.rank_by" class="mt-1 rounded-lg border-gray-300 focus:border-gold-500 focus:ring-gold-500"><option value="value">Purchase value</option><option value="orders">Completed orders</option></select><InputError :message="form.errors.rank_by" /></div>
                <button :disabled="form.processing" class="rounded-lg bg-gray-900 px-5 py-2.5 font-semibold text-white hover:bg-gray-700 disabled:opacity-50">{{ form.processing ? 'Loading…' : 'Apply filters' }}</button>
            </div>
            <p class="mt-3 text-xs leading-5 text-gray-500">Completed orders only, using order creation dates ({{ filters.from }} – {{ filters.to }}). Values are final order totals after discounts, in MMK. Source attribution follows the customer's current source. Ties use the other ranking metric, then customer ID.</p>
        </form>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-gray-900 p-6 text-white shadow-sm"><p class="text-sm text-gray-300">Completed purchase value</p><p class="mt-2 text-2xl font-semibold">{{ money(report.summary.purchase_value) }} <span class="text-sm text-gray-300">MMK</span></p><p class="mt-2 text-xs text-gray-400">Includes guest purchases</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Completed orders</p><p class="mt-2 text-3xl font-semibold text-gray-900">{{ money(report.summary.completed_orders) }}</p><p class="mt-2 text-xs text-gray-500">During the selected period</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Purchasing customers</p><p class="mt-2 text-3xl font-semibold text-gray-900">{{ money(report.summary.purchasing_customers) }}</p><p class="mt-2 text-xs text-gray-500">Distinct customers linked to orders</p></div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Purchase value over time</h2><p class="mt-1 text-sm text-gray-500">Daily completed sales · MMK</p>
                <p v-if="!Number(report.summary.completed_orders)" class="py-16 text-center text-sm text-gray-500">No completed purchases in this period.</p>
                <template v-else>
                    <svg viewBox="0 0 900 220" class="mt-5 w-full" role="img" aria-label="Daily purchase value chart; exact amounts are available in the daily totals table below">
                        <line x1="40" y1="180" x2="860" y2="180" stroke="#e5e7eb" />
                        <line x1="40" y1="40" x2="860" y2="40" stroke="#e5e7eb" stroke-dasharray="4 4" />
                        <text x="40" y="24" fill="#6b7280" font-size="18">{{ money(maxTrend) }} MMK</text>
                        <polyline :points="points" fill="none" stroke="#b58a31" stroke-width="3" stroke-linejoin="round" />
                        <circle v-for="(row, index) in report.trend" :key="row.date" :cx="point(row, index).x" :cy="point(row, index).y" r="3" fill="#b58a31"><title>{{ row.date }}: {{ money(row.purchase_value) }} MMK, {{ row.completed_orders }} orders</title></circle>
                        <text x="40" y="212" fill="#6b7280" font-size="18">{{ filters.from }}</text><text x="860" y="212" text-anchor="end" fill="#6b7280" font-size="18">{{ filters.to }}</text>
                    </svg>
                    <details class="mt-3 text-sm"><summary class="cursor-pointer text-gold-700">View daily totals</summary><div class="mt-3 max-h-56 overflow-auto"><table class="w-full text-left"><thead><tr class="text-gray-500"><th class="py-2">Date</th><th>Orders</th><th class="text-right">MMK</th></tr></thead><tbody><tr v-for="row in report.trend" :key="row.date" class="border-t border-gray-100"><td class="py-2">{{ row.date }}</td><td>{{ row.completed_orders }}</td><td class="text-right tabular-nums">{{ money(row.purchase_value) }}</td></tr></tbody></table></div></details>
                </template>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Which source brings the most sales?</h2><p class="mt-1 text-sm text-gray-500">Ranked by completed purchase value</p>
                <p v-if="!report.sources.length" class="py-16 text-center text-sm text-gray-500">Source results will appear after completed purchases.</p>
                <div v-else class="mt-5 space-y-4">
                    <div v-for="source in report.sources" :key="source.source">
                        <div class="flex justify-between gap-3 text-sm"><span class="font-medium text-gray-800">{{ source.label }}</span><span class="tabular-nums text-gray-700">{{ money(source.purchase_value) }} MMK</span></div>
                        <div class="my-1.5 h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true"><div class="h-full rounded-full bg-gold-500" :style="{ width: `${Number(source.purchase_value) / maxSource * 100}%` }" /></div>
                        <p class="text-xs text-gray-500">{{ source.completed_orders }} orders · {{ source.customers }} linked customers · {{ share(source.purchase_value) }}% of purchase value</p>
                    </div>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-6"><h2 class="text-lg font-semibold text-gray-900">Customer leaderboard</h2><p class="mt-1 text-sm text-gray-500">{{ filters.rank_by === 'orders' ? 'Most completed orders first' : 'Highest purchase value first' }} · Guest purchases are excluded from customer rankings.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-6 py-3">Rank</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Source</th><th class="px-6 py-3">Profile / referral</th><th class="px-6 py-3 text-right">Completed orders</th><th class="px-6 py-3 text-right">Purchase value (MMK)</th></tr></thead>
                <tbody class="divide-y divide-gray-100"><tr v-for="(customer, index) in report.leaderboard.data" :key="customer.id" class="hover:bg-gray-50"><td class="px-6 py-4 font-semibold text-gold-700">#{{ report.leaderboard.from + index }}</td><td class="px-6 py-4 font-medium text-gray-900">{{ customer.name }}<span v-if="customer.deleted_at" class="block text-xs font-normal text-gray-500">Archived customer</span></td><td class="px-6 py-4">{{ sourceOptions[customer.source] || 'Not specified' }}</td><td class="max-w-xs break-words px-6 py-4"><a v-if="profileUrl(customer.source_details)" :href="profileUrl(customer.source_details)" target="_blank" rel="noopener noreferrer" class="text-gold-700 underline">{{ customer.source_details }}</a><span v-else class="text-gray-500">{{ customer.source_details || '—' }}</span></td><td class="px-6 py-4 text-right tabular-nums">{{ customer.completed_orders }}</td><td class="px-6 py-4 text-right font-semibold tabular-nums">{{ money(customer.purchase_value) }}</td></tr>
                    <tr v-if="!report.leaderboard.data.length"><td colspan="6" class="p-10 text-center text-gray-500">No linked customers with completed purchases in this period.</td></tr>
                </tbody></table></div>
            <div v-if="report.leaderboard.last_page > 1" class="flex items-center justify-between border-t border-gray-100 p-4 text-sm"><Link v-if="report.leaderboard.prev_page_url" :href="report.leaderboard.prev_page_url" preserve-scroll class="rounded border px-3 py-2">Previous</Link><span v-else /><span class="text-gray-500">Page {{ report.leaderboard.current_page }} of {{ report.leaderboard.last_page }}</span><Link v-if="report.leaderboard.next_page_url" :href="report.leaderboard.next_page_url" preserve-scroll class="rounded border px-3 py-2">Next</Link><span v-else /></div>
        </section>
    </div>
</template>
