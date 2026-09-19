<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, ChevronDownIcon, CubeIcon, MagnifyingGlassIcon, PencilSquareIcon, PhotoIcon, PlusIcon, PrinterIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AccessoryDialog from '@/Components/Accessories/AccessoryDialog.vue';
import SystemCodeLabel from '@/Components/SystemCodeLabel.vue';

const props = defineProps({ product: Object, items: Object, counts: Object, filters: Object });
const page = usePage();
const statuses = ['available', 'sold', 'reserved', 'returned', 'lost', 'damaged'];
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const total = computed(() => Object.values(props.counts).reduce((sum, value) => sum + Number(value), 0));
const filter = () => router.get(route('products.show', props.product.id), { search: search.value, status: status.value }, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => { search.value = ''; status.value = ''; filter(); };
const setStatus = value => { status.value = value; filter(); };
const date = value => value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const money = value => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const mainImage = computed(() => props.product.images?.[0] || props.product.image || null);
const imageUrl = path => `${page.props.storage_url}/${path}`;
const finalPrice = computed(() => Number(props.product.price || 0) * (1 - Number(props.product.discount || 0) / 100));
const youtubeEmbedUrl = computed(() => {
    if (!props.product.youtube_link) return null;
    try {
        const url = new URL(props.product.youtube_link);
        const host = url.hostname.replace(/^www\./, '').toLowerCase();
        let videoId = host === 'youtu.be' ? url.pathname.split('/').filter(Boolean)[0] : url.searchParams.get('v');
        if (!videoId && ['youtube.com', 'm.youtube.com'].includes(host)) videoId = url.pathname.split('/').filter(Boolean)[1];
        return /^[A-Za-z0-9_-]{11}$/.test(videoId || '') ? `https://www.youtube-nocookie.com/embed/${videoId}` : null;
    } catch { return null; }
});
const statusClass = value => ({ available: 'bg-emerald-50 text-emerald-700', sold: 'bg-blue-50 text-blue-700', reserved: 'bg-amber-50 text-amber-700', returned: 'bg-purple-50 text-purple-700', lost: 'bg-red-50 text-red-700', damaged: 'bg-red-50 text-red-700' }[value] || 'bg-gray-100 text-gray-600');
const details = computed(() => [
    ['Brand', props.product.brand?.name], ['Categories', props.product.categories?.map(category => category.name).join(', ')], ['Model', props.product.model_number], ['Product barcode', props.product.barcode],
    ['Warranty', props.product.warranty_period ? `${props.product.warranty_period} months` : null], ['Warranty type', props.product.warranty_type?.replaceAll('_', ' ')],
    ['Web price', props.product.web_price ? `${money(props.product.web_price)} ${props.product.currency}` : null], ['Cost price', props.product.cost_price ? `${money(props.product.cost_price)} ${props.product.currency}` : null],
].filter(([, value]) => value));
const specifications = computed(() => [
    ['Watch type', props.product.watch_type], ['Gender', props.product.gender], ['Movement', props.product.movement], ['Glass / crystal', props.product.glass || props.product.crystal],
    ['Water resistance', props.product.water_resistant], ['Case shape', props.product.shape || props.product.case_shape], ['Dial size', props.product.dial_size], ['Dial color', props.product.dial_color],
    ['Dial markings', props.product.dial_markings], ['Band material', props.product.band || props.product.strap_material], ['Band color', props.product.band_color || props.product.strap_color],
    ['Band size', props.product.band_size || props.product.strap_size], ['Lug width', props.product.lug_width], ['Clasp type', props.product.strap_buckle || props.product.clasp_type],
    ['Case material', props.product.case_material], ['Case color', props.product.case_color], ['Case thickness', props.product.case_thickness], ['Case finish', props.product.case_finish],
    ['Battery type', props.product.battery_type], ['Couple watch', props.product.couple], ['Origin', props.product.origin], ['Quick release', props.product.quick_release],
    ['Caliber code', props.product.caliber_code], ['Caseback', props.product.caseback_design],
].filter(([, value]) => value));

const stockOpen = ref(false);
const stockForm = useForm({ quantity: 1, purchase_date: '', status: 'available' });
const openStock = () => { stockForm.reset(); stockForm.clearErrors(); stockOpen.value = true; };
const addStock = () => stockForm.post(route('products.items.store', props.product.id), { preserveScroll: true, onSuccess: () => stockOpen.value = false });
const editItem = ref(null);
const editForm = useForm({ serial_number: '', system_unique_id: '', purchase_date: '', status: '' });
const openEdit = item => { editItem.value = item; editForm.clearErrors(); editForm.serial_number = item.serial_number || ''; editForm.system_unique_id = item.system_unique_id || ''; editForm.purchase_date = item.purchase_date?.substring(0, 10) || ''; editForm.status = item.status; };
const saveEdit = () => editForm.put(route('items.update', editItem.value.id), { preserveScroll: true, onSuccess: () => editItem.value = null });
const deleteItem = item => { if (confirm(`Remove unit ${item.system_unique_id || `#${item.id}`} from stock?`)) router.delete(route('items.destroy', item.id), { preserveScroll: true }); };
const labelsOpen = ref(false);
const labelItems = ref([]);
const printItem = item => { labelItems.value = [item]; labelsOpen.value = true; };
const printPage = () => { labelItems.value = props.items.data.filter(item => item.system_unique_id); labelsOpen.value = true; };
</script>

<template>
    <Head :title="`${product.name} · Stock`" />
    <AdminLayout>
        <div class="watch-stock mx-auto max-w-7xl space-y-6">
            <Link :href="route('products.index')" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900"><ArrowLeftIcon class="h-4 w-4" />Back to watches</Link>
            <header class="flex flex-wrap items-start justify-between gap-5">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"><img v-if="mainImage" :src="imageUrl(mainImage)" :alt="product.name" class="h-full w-full object-contain" /><PhotoIcon v-else class="h-7 w-7 text-gray-300" /></div>
                    <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-gold-700">{{ product.brand?.name || 'Watch' }} · Stock management</p><h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ product.name }}</h1><p class="mt-2 text-sm text-gray-500">{{ product.model_number || 'No model number' }} · {{ money(finalPrice) }} {{ product.currency }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-if="product.barcode" class="tag">SKU {{ product.barcode }}</span><span class="tag">{{ product.is_active ? 'Active' : 'Inactive' }}</span><span v-if="product.discount" class="tag">{{ product.discount }}% discount</span></div></div>
                </div>
                <div class="flex flex-wrap gap-2"><Link :href="route('products.edit', product.id)" class="secondary"><PencilSquareIcon class="h-4 w-4" />Edit watch</Link><button class="secondary" :disabled="!items.data.some(item => item.system_unique_id)" @click="printPage"><PrinterIcon class="h-4 w-4" />Print page labels</button><button class="primary" @click="openStock"><PlusIcon class="h-4 w-4" />Add stock</button></div>
            </header>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4"><button v-for="card in [{ label: 'Total units', value: '', count: total }, { label: 'Available', value: 'available', count: counts.available || 0 }, { label: 'Sold', value: 'sold', count: counts.sold || 0 }, { label: 'Reserved', value: 'reserved', count: counts.reserved || 0 }]" :key="card.label" @click="setStatus(card.value)" :aria-pressed="status === card.value" class="summary-card" :class="status === card.value ? 'border-gold-400 ring-1 ring-gold-100' : 'border-gray-200'"><p class="text-sm text-gray-500">{{ card.label }}</p><p class="mt-2 text-3xl font-semibold text-gray-900">{{ card.count }}</p></button></div>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-5"><h2 class="font-semibold text-gray-900">Individual watch units</h2><p class="mt-1 text-sm text-gray-500">Search by system barcode or manufacturer serial number. POS sales update the exact unit automatically.</p></div>
                <form @submit.prevent="filter" class="flex flex-wrap gap-3 border-b border-gray-100 p-4 sm:px-6"><div class="relative min-w-56 flex-1"><MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-gray-400" /><input v-model="search" class="field search-input" placeholder="Scan or search barcode / serial…" aria-label="Search watch stock" maxlength="100" /></div><select v-model="status" @change="filter" class="field status-filter" aria-label="Stock status"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value.charAt(0).toUpperCase() + value.slice(1) }}</option></select><button class="secondary">Search</button><button v-if="search || status" type="button" class="text-sm text-gray-500 hover:text-gray-900" @click="clearFilters">Clear</button></form>
                <div v-if="items.data.length" class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th>System barcode</th><th>Serial number</th><th>Status</th><th>Purchase date</th><th>Sale order</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody class="divide-y divide-gray-100"><tr v-for="item in items.data" :key="item.id" class="hover:bg-gray-50/60"><td><p class="font-mono font-medium text-gray-900">{{ item.system_unique_id || 'Not generated' }}</p><p class="mt-1 text-xs text-gray-400">Unit #{{ item.id }} · Added {{ date(item.created_at) }}</p></td><td class="font-mono text-gray-600">{{ item.serial_number || '—' }}</td><td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(item.status)">{{ item.status }}</span></td><td class="whitespace-nowrap text-gray-500">{{ date(item.purchase_date) }}</td><td><Link v-if="item.order_item?.order_id" :href="route('orders.show', item.order_item.order_id)" class="font-medium text-gold-700 hover:underline">Order #{{ item.order_item.order_id }}</Link><span v-else class="text-gray-400">—</span></td><td><div class="flex justify-end gap-2"><button v-if="item.system_unique_id" class="icon-button" :aria-label="`Print barcode ${item.system_unique_id}`" title="Print label" @click="printItem(item)"><PrinterIcon class="h-4 w-4" /></button><button class="icon-button" :aria-label="`Edit unit ${item.id}`" title="Edit unit" @click="openEdit(item)"><PencilSquareIcon class="h-4 w-4" /></button><button v-if="item.status === 'available'" class="icon-button text-red-600" :aria-label="`Remove unit ${item.id}`" title="Remove available unit" @click="deleteItem(item)"><TrashIcon class="h-4 w-4" /></button></div></td></tr></tbody></table></div>
                <div v-else class="px-6 py-14 text-center"><CubeIcon class="mx-auto h-9 w-9 text-gray-300" /><h3 class="mt-4 font-semibold text-gray-900">{{ total ? 'No matching watches' : 'No stock yet' }}</h3><p class="mt-2 text-sm text-gray-500">{{ total ? 'Try another barcode, serial number, or status.' : 'Add stock to generate a unique barcode for each watch.' }}</p><button v-if="search || status" class="secondary mx-auto mt-5" @click="clearFilters">Clear filters</button><button v-else class="primary mx-auto mt-5" @click="openStock">Add stock</button></div>
                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 text-xs text-gray-500"><p>{{ items.total ? `${items.from}–${items.to} of ${items.total} units` : '0 units' }}</p><div class="flex items-center gap-3"><Link v-if="items.prev_page_url" :href="items.prev_page_url" preserve-scroll class="secondary">Previous</Link><span>Page {{ items.current_page }} of {{ items.last_page }}</span><Link v-if="items.next_page_url" :href="items.next_page_url" preserve-scroll class="secondary">Next</Link></div></footer>
            </section>

            <details class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><summary class="flex cursor-pointer list-none items-center justify-between px-6 py-5"><div><h2 class="font-semibold text-gray-900">Watch information</h2><p class="mt-1 text-sm text-gray-500">Pricing, product details, specifications, images, and description</p></div><ChevronDownIcon class="h-5 w-5 text-gray-400 transition group-open:rotate-180" /></summary><div class="grid gap-8 border-t border-gray-100 p-6 lg:grid-cols-[280px_1fr]"><div><div class="overflow-hidden rounded-xl border border-gray-100 bg-gray-50"><img v-if="mainImage" :src="imageUrl(mainImage)" :alt="product.name" class="aspect-square w-full object-contain" /><div v-else class="flex aspect-square items-center justify-center"><PhotoIcon class="h-10 w-10 text-gray-300" /></div></div><div v-if="product.images?.length > 1" class="mt-3 grid grid-cols-4 gap-2"><img v-for="image in product.images.slice(1)" :key="image" :src="imageUrl(image)" :alt="product.name" class="aspect-square rounded-lg border border-gray-100 object-cover" /></div><iframe v-if="youtubeEmbedUrl" :src="youtubeEmbedUrl" class="mt-4 aspect-video w-full rounded-xl border-0" title="Watch video" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen /></div><div class="space-y-7"><div><h3 class="section-title">Product details</h3><dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2"><div v-for="([label, value]) in details" :key="label" class="border-b border-gray-100 pb-3"><dt class="text-xs text-gray-400">{{ label }}</dt><dd class="mt-1 text-sm font-medium capitalize text-gray-800">{{ value }}</dd></div></dl></div><div v-if="specifications.length"><h3 class="section-title">Specifications</h3><dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3"><div v-for="([label, value]) in specifications" :key="label"><dt class="text-xs text-gray-400">{{ label }}</dt><dd class="mt-1 text-sm font-medium text-gray-800">{{ value }}</dd></div></dl></div><div v-if="product.description"><h3 class="section-title">Description</h3><p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600">{{ product.description }}</p></div></div></div></details>
            <p class="text-xs text-gray-500">Sold and reserved watches stay in inventory history. Use the sale order link to trace a sold barcode.</p>
        </div>

        <AccessoryDialog :show="stockOpen" title="Add watch stock" :description="product.name" :busy="stockForm.processing" @close="stockOpen = false"><form class="watch-stock" @submit.prevent="addStock"><div class="space-y-5 p-6"><div class="grid gap-4 sm:grid-cols-3"><label class="label">Quantity <span class="text-red-500">*</span><input v-model="stockForm.quantity" class="field mt-2" type="number" min="1" max="500" required /></label><label class="label">Purchase date<input v-model="stockForm.purchase_date" class="field mt-2" type="date" /></label><label class="label">Initial status<select v-model="stockForm.status" class="field mt-2"><option value="available">Available</option><option value="reserved">Reserved</option></select></label></div><p class="hint">Every new watch receives its own 12-digit system barcode. You can add its manufacturer serial number afterward.</p><p v-for="message in stockForm.errors" :key="message" role="alert" class="text-sm text-red-600">{{ message }}</p></div><footer class="dialog-footer"><button type="button" class="secondary" :disabled="stockForm.processing" @click="stockOpen = false">Cancel</button><button class="primary" :disabled="stockForm.processing">{{ stockForm.processing ? 'Adding…' : `Add ${stockForm.quantity} watch${Number(stockForm.quantity) === 1 ? '' : 'es'}` }}</button></footer></form></AccessoryDialog>
        <AccessoryDialog :show="!!editItem" title="Edit stock unit" :description="editItem?.system_unique_id || `Unit #${editItem?.id}`" :busy="editForm.processing" @close="editItem = null"><form class="watch-stock" @submit.prevent="saveEdit"><div class="space-y-5 p-6"><div class="grid gap-4 sm:grid-cols-2"><label class="label">Manufacturer serial<input v-model="editForm.serial_number" class="field mt-2" maxlength="255" placeholder="Optional" /></label><label class="label">System barcode<input v-model="editForm.system_unique_id" class="field mt-2 font-mono" maxlength="12" inputmode="numeric" placeholder="12 digits" /></label><label class="label">Purchase date<input v-model="editForm.purchase_date" class="field mt-2" type="date" /></label><label class="label">Status<select v-model="editForm.status" class="field mt-2"><option v-for="value in statuses" :key="value" :value="value">{{ value.charAt(0).toUpperCase() + value.slice(1) }}</option></select></label></div><p v-for="message in editForm.errors" :key="message" role="alert" class="text-sm text-red-600">{{ message }}</p></div><footer class="dialog-footer"><button type="button" class="secondary" :disabled="editForm.processing" @click="editItem = null">Cancel</button><button class="primary" :disabled="editForm.processing">{{ editForm.processing ? 'Saving…' : 'Save unit' }}</button></footer></form></AccessoryDialog>
        <SystemCodeLabel :show="labelsOpen" :items="labelItems" :product="product" @close="labelsOpen = false" />
    </AdminLayout>
</template>

<style scoped>
.primary { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-gold-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-gold-700 disabled:opacity-50; }
.secondary { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50; }
.icon-button { @apply inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-50; }
.summary-card { @apply rounded-xl border bg-white p-5 text-left shadow-sm transition hover:border-gold-400; }
.field { @apply block w-full rounded-lg border-gray-200 text-sm focus:border-gold-500 focus:ring-gold-500; }
.field.search-input { @apply pl-9; }
.field.status-filter { @apply w-auto; }
.label { @apply text-sm font-medium text-gray-700; }
.hint { @apply text-xs leading-relaxed text-gray-500; }
.tag { @apply rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600; }
.section-title { @apply text-sm font-semibold uppercase tracking-wide text-gray-800; }
.dialog-footer { @apply flex justify-end gap-3 border-t border-gray-100 p-4; }
th { @apply px-6 py-3 font-medium; }
td { @apply px-6 py-4; }
</style>
