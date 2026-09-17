<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeftIcon, CubeIcon, MagnifyingGlassIcon, PlusIcon, PrinterIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AccessoryDialog from '@/Components/Accessories/AccessoryDialog.vue';
import SystemCodeLabel from '@/Components/SystemCodeLabel.vue';

const props = defineProps({ accessory: Object, items: Object, counts: Object, filters: Object });
const statuses = ['available', 'sold', 'reserved', 'returned', 'lost', 'damaged'];
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const total = computed(() => Object.values(props.counts).reduce((sum, count) => sum + Number(count), 0));
const filter = () => router.get(route('accessories.show', props.accessory.id), { search: search.value, status: status.value }, { preserveState: true, preserveScroll: true, replace: true });
const clear = () => { search.value = ''; status.value = ''; filter(); };
const statusClass = value => ({ available: 'bg-emerald-50 text-emerald-700', sold: 'bg-blue-50 text-blue-700', reserved: 'bg-amber-50 text-amber-700', returned: 'bg-purple-50 text-purple-700', lost: 'bg-red-50 text-red-700', damaged: 'bg-red-50 text-red-700' }[value] || 'bg-gray-100 text-gray-600');
const date = value => value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const stockOpen = ref(false);
const stock = useForm({ quantity: 1, operation: 'add' });
const after = computed(() => Number(props.counts.available || 0) + (stock.operation === 'add' ? 1 : -1) * Number(stock.quantity || 0));
const openStock = () => { stock.reset(); stock.clearErrors(); stockOpen.value = true; };
const saveStock = () => stock.post(route('accessories.stock', props.accessory.id), { preserveScroll: true, onSuccess: () => stockOpen.value = false });
const labelItems = ref([]);
const labelsOpen = ref(false);
const loading = ref(false);
const error = ref('');
const printItem = item => { labelItems.value = [item]; labelsOpen.value = true; };
const printAvailable = async () => {
    error.value = ''; loading.value = true;
    try {
        const { data } = await axios.post(route('accessories.labels', props.accessory.id));
        labelItems.value = data.items;
        if (data.items.length) labelsOpen.value = true;
        else error.value = 'No available units to print.';
        router.reload({ only: ['items', 'counts'] });
    } catch { error.value = 'Could not prepare labels. Please try again.'; }
    finally { loading.value = false; }
};
</script>

<template>
    <Head :title="`${accessory.name} · Stock`" />
    <AdminLayout>
        <div class="accessory-stock mx-auto max-w-7xl space-y-6">
            <Link :href="route('accessories.index')" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900"><ArrowLeftIcon class="h-4 w-4" />Back to accessories</Link>
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-gold-700">{{ accessory.accessory_type?.name || 'Accessory' }} · Stock management</p><h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ accessory.name }}</h1><p class="mt-2 text-sm text-gray-500">SKU {{ accessory.barcode || '—' }} · {{ Number(accessory.price).toLocaleString() }} {{ accessory.currency }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="attribute in accessory.accessory_attributes" :key="attribute.name" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600">{{ attribute.name }}: {{ attribute.value }}</span></div></div>
                <div class="flex flex-wrap gap-2"><button class="secondary" :disabled="loading || !counts.available" @click="printAvailable"><PrinterIcon class="h-4 w-4" />{{ loading ? 'Preparing…' : 'Print available labels' }}</button><button class="primary" @click="openStock"><PlusIcon class="h-4 w-4" />Adjust stock</button></div>
            </header>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <button v-for="card in [{ label: 'Total units', value: '', count: total }, { label: 'Available', value: 'available', count: counts.available || 0 }, { label: 'Sold', value: 'sold', count: counts.sold || 0 }, { label: 'Reserved', value: 'reserved', count: counts.reserved || 0 }]" :key="card.label" @click="status = card.value; filter()" :aria-pressed="status === card.value" class="rounded-xl border bg-white p-5 text-left shadow-sm transition hover:border-gold-400" :class="status === card.value ? 'border-gold-400 ring-1 ring-gold-100' : 'border-gray-200'"><p class="text-sm text-gray-500">{{ card.label }}</p><p class="mt-2 text-3xl font-semibold text-gray-900">{{ card.count }}</p></button>
            </div>
            <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-5"><h2 class="font-semibold text-gray-900">Individual stock units</h2><p class="mt-1 text-sm text-gray-500">Each barcode identifies one unit. Scanning it in POS sells that specific unit.</p></div>
                <form @submit.prevent="filter" class="flex flex-wrap gap-3 border-b border-gray-100 p-4 sm:px-6"><div class="relative min-w-48 flex-1"><MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-gray-400" /><input v-model="search" class="field search-input" placeholder="Scan or search a unit barcode…" aria-label="Search stock barcode" maxlength="100" /></div><select v-model="status" @change="filter" class="field status-filter" aria-label="Stock status"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value.charAt(0).toUpperCase() + value.slice(1) }}</option></select><button class="secondary">Search</button><button v-if="search || status" type="button" @click="clear" class="text-sm text-gray-500 hover:text-gray-900">Clear</button></form>
                <div v-if="items.data.length" class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th>Unit barcode</th><th>Status</th><th>Added</th><th>Sale order</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody class="divide-y divide-gray-100"><tr v-for="item in items.data" :key="item.id" class="hover:bg-gray-50/60"><td><p class="font-mono font-medium text-gray-900">{{ item.system_unique_id || 'Not generated yet' }}</p><p class="mt-1 text-xs text-gray-400">Unit #{{ item.id }}<span v-if="item.serial_number"> · Serial {{ item.serial_number }}</span></p></td><td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(item.status)">{{ item.status }}</span></td><td class="whitespace-nowrap text-gray-500">{{ date(item.created_at) }}</td><td><Link v-if="item.order_item?.order_id" :href="route('orders.show', item.order_item.order_id)" class="font-medium text-gold-700 hover:underline">Order #{{ item.order_item.order_id }}</Link><span v-else class="text-gray-400">—</span></td><td class="text-right"><button v-if="item.status === 'available' && item.system_unique_id" class="secondary ml-auto whitespace-nowrap" @click="printItem(item)" :aria-label="`Print barcode ${item.system_unique_id}`"><PrinterIcon class="h-4 w-4" />Print label</button></td></tr></tbody></table></div>
                <div v-else class="px-6 py-14 text-center"><CubeIcon class="mx-auto h-9 w-9 text-gray-300" /><h3 class="mt-4 font-semibold text-gray-900">{{ total ? 'No matching units' : 'No stock yet' }}</h3><p class="mt-2 text-sm text-gray-500">{{ total ? 'Try another barcode or status filter.' : 'Add stock to generate a unique barcode for each unit.' }}</p><button v-if="search || status" class="secondary mx-auto mt-5" @click="clear">Clear filters</button><button v-else class="primary mx-auto mt-5" @click="openStock">Add stock</button></div>
                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 text-xs text-gray-500"><p>{{ items.total ? `${items.from}–${items.to} of ${items.total} units` : '0 units' }}</p><div class="flex items-center gap-3"><Link v-if="items.prev_page_url" :href="items.prev_page_url" preserve-scroll class="secondary">Previous</Link><span>Page {{ items.current_page }} of {{ items.last_page }}</span><Link v-if="items.next_page_url" :href="items.next_page_url" preserve-scroll class="secondary">Next</Link></div></footer>
            </section>
            <p class="text-xs text-gray-500">Stock status updates automatically after POS sales and order edits. Sold and reserved units remain in your records.</p>
        </div>
        <AccessoryDialog :show="stockOpen" title="Adjust stock" :description="accessory.name" :busy="stock.processing" @close="stockOpen = false">
            <form class="accessory-stock" @submit.prevent="saveStock"><div class="space-y-5 p-6"><div class="grid grid-cols-2 gap-4 rounded-xl bg-gray-50 p-4"><div><p class="text-xs text-gray-500">Available now</p><p class="mt-1 text-2xl font-semibold">{{ counts.available || 0 }}</p></div><div><p class="text-xs text-gray-500">After adjustment</p><p class="mt-1 text-2xl font-semibold" :class="after < 0 ? 'text-red-600' : 'text-gold-700'">{{ after }}</p></div></div><div class="grid grid-cols-2 gap-4"><label class="text-sm font-medium">Action<select v-model="stock.operation" class="field mt-2"><option value="add">Add stock</option><option value="remove">Remove stock</option></select></label><label class="text-sm font-medium">Quantity<input v-model="stock.quantity" class="field mt-2" type="number" required min="1" :max="stock.operation === 'remove' ? Math.min(500, counts.available || 0) : 500" /></label></div><p class="text-xs leading-relaxed text-gray-500">New units receive unique barcodes automatically. Removing stock removes available units by quantity; sold and reserved units are kept.</p><p v-for="message in stock.errors" :key="message" role="alert" class="text-sm text-red-600">{{ message }}</p></div><footer class="flex justify-end gap-3 border-t border-gray-100 p-4"><button type="button" class="secondary" :disabled="stock.processing" @click="stockOpen = false">Cancel</button><button class="primary" :disabled="stock.processing || after < 0">{{ stock.processing ? 'Updating…' : 'Update stock' }}</button></footer></form>
        </AccessoryDialog>
        <SystemCodeLabel :show="labelsOpen" :items="labelItems" :product="accessory" @close="labelsOpen = false" />
    </AdminLayout>
</template>

<style scoped>
.primary { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-gold-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-gold-700 disabled:opacity-50; }
.secondary { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50; }
.field { @apply block w-full rounded-lg border-gray-200 text-sm focus:border-gold-500 focus:ring-gold-500; }
.field.search-input { @apply pl-9; }
.field.status-filter { @apply w-auto; }
th { @apply px-6 py-3 font-medium; }
td { @apply px-6 py-4; }
</style>
