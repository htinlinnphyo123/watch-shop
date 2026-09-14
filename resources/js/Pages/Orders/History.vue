<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import OrderAuditSnapshot from '@/Components/OrderAuditSnapshot.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { historyLabels, watchChanges } from '@/utils/orderHistory';

const props = defineProps({ order: Object, versions: Array, from: Object, to: Object, hasOriginal: Boolean, currentSnapshot: Object });
const labels = computed(() => historyLabels(props.versions));
const watches = computed(() => watchChanges(props.from?.snapshot, props.to?.snapshot || props.currentSnapshot));
const selectedIndex = computed(() => props.versions.findIndex(entry => entry.version === props.to?.version));
const date = value => new Date(value).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
const select = version => router.get(route('orders.history', props.order.id), { to: version });
const markedWatches = (snapshot, changes, action) => Object.values(snapshot?.watches || {}).map(watch => {
    const changed = changes.find(item => item.unit_id === watch.unit_id && item.product_id === watch.product_id && item.system_code === watch.system_code && item.serial === watch.serial);
    return { ...watch, badge: changed ? (changed.quantity < watch.quantity ? `${changed.quantity} ${action.toLowerCase()}` : action) : 'Kept', action: changed ? action : 'Kept' };
});
const columns = computed(() => props.from ? [
    { title: 'Before this change', items: markedWatches(props.from.snapshot, watches.value.removed, 'Removed') },
    { title: 'After this change', items: markedWatches(props.to.snapshot, watches.value.added, 'Added') },
] : [{ title: props.to ? 'Watches in this order' : 'Current watches', items: watches.value.added }]);
</script>

<template>
    <Head :title="`Watch history · ${order.order_number}`" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-5 pb-12">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div><h1 class="text-2xl font-semibold text-gray-900">Watch History</h1><p class="text-sm text-gray-500 mt-1">{{ order.order_number }}</p></div>
                <Link :href="route('orders.show', order.id)" class="text-sm text-gray-600 hover:underline">Back to Order</Link>
            </div>
            <p v-if="!hasOriginal" class="text-sm text-amber-800">Earlier changes aren’t available. History starts with the first saved record.</p>
            <section v-if="to" class="rounded-xl border border-gray-200 bg-white p-5">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex-1 min-w-0 text-sm font-medium text-gray-700">Choose a change
                        <select :value="to.version" @change="select($event.target.value)" class="mt-2 block w-full rounded-lg border-gray-300 text-sm">
                            <option v-for="entry in versions" :key="entry.version" :value="entry.version">{{ labels[entry.version] }} · {{ date(entry.created_at) }}</option>
                        </select>
                    </label>
                    <button :disabled="selectedIndex >= versions.length - 1" @click="select(versions[selectedIndex + 1].version)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-30">Older</button>
                    <button :disabled="selectedIndex <= 0" @click="select(versions[selectedIndex - 1].version)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-30">Newer</button>
                </div>
                <p class="mt-4 text-sm text-gray-500">{{ to.actor_name }} · {{ date(to.created_at) }}</p>
                <p v-if="from && to.version !== from.version + 1" class="mt-2 text-sm text-gray-500">Comparing {{ labels[from.version] }} with {{ labels[to.version] }}.</p>
            </section>
            <p v-if="from && !watches.removed.length && !watches.added.length" class="rounded-xl border border-gray-200 bg-white p-6 text-gray-600">No watches were changed in this update.</p>
            <template v-if="columns.length">
                <p v-if="from && to.event === 'approved'" class="text-sm text-gray-500">Stock assigned on approval. System codes identify the selected units.</p>
                <div class="grid gap-4" :class="from ? 'sm:grid-cols-2' : ''">
                    <section v-for="column in columns" :key="column.title" class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="font-semibold text-gray-900">{{ column.title }}</h2>
                        <p v-if="!column.items.length" class="mt-4 text-sm text-gray-500">No watches in this order.</p>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="(watch, index) in column.items" :key="index" class="py-4 space-y-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-gray-900">{{ watch.name }}</p>
                                    <span v-if="watch.badge" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="watch.action === 'Removed' ? 'bg-red-50 text-red-700' : watch.action === 'Added' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">{{ watch.badge }}</span>
                                </div>
                                <p v-if="watch.model" class="text-sm text-gray-600">{{ watch.model }}</p>
                                <p v-if="watch.system_code" class="text-sm text-gray-700 break-all">System code: <span class="font-mono font-medium">{{ watch.system_code }}</span></p>
                                <p v-if="watch.serial" class="text-sm text-gray-500 break-all">Serial: {{ watch.serial }}</p>
                                <p v-if="watch.quantity > 1" class="text-sm text-gray-600">Quantity: {{ watch.quantity }}</p>
                            </li>
                        </ul>
                    </section>
                </div>
            </template>
            <details class="text-sm text-gray-500">
                <summary class="cursor-pointer hover:text-gray-800">Other order details</summary>
                <div class="grid gap-4 mt-4" :class="from ? 'lg:grid-cols-2' : ''">
                    <OrderAuditSnapshot v-if="from" :snapshot="from.snapshot" title="Before" />
                    <OrderAuditSnapshot :snapshot="to?.snapshot || currentSnapshot" :title="from ? 'After' : 'Order details'" />
                </div>
            </details>
        </div>
    </AdminLayout>
</template>
