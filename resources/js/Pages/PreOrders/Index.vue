<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/vue';
import OrderAttachments from '@/Components/OrderAttachments.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({ preOrders: Object, brands: Array, customers: Array, products: Array, availableItems: { type: Array, default: () => [] }, filters: { type: Object, default: () => ({}) } });
const attachmentId = ref(null);
const attachmentBusy = ref(false);
const attachmentOrder = computed(() => props.preOrders.data.find(order => order.id === attachmentId.value));
const filters = ref({ type: '', status: '', brand_id: '', customer_id: '', product_id: '', ...props.filters });
watch(() => props.filters, value => { filters.value = { type: '', status: '', brand_id: '', customer_id: '', product_id: '', ...value }; });
const filterSearch = ref('');
const filterProductOptions = computed(() => props.products.filter(product =>
    (!filters.value.brand_id || String(product.brand_id) === String(filters.value.brand_id))
    && productLabel(product).toLowerCase().includes(filterSearch.value.trim().toLowerCase())
));
const filterProductLabel = id => {
    const product = props.products.find(product => String(product.id) === String(id));
    return product ? productLabel(product) : '';
};
const filtering = ref(false);
const applyFilters = () => {
    filtering.value = true;
    router.get(route('pre-orders.index'), Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value != null)), {
        preserveState: true, preserveScroll: true, onFinish: () => { filtering.value = false; },
    });
};
const clearFilters = () => {
    filters.value = { type: '', status: '', brand_id: '', customer_id: '', product_id: '' };
    filterSearch.value = '';
    applyFilters();
};
const isOpen = ref(false);
const editing = ref(null);
const form = useForm({ customer_id: '', brand_id: '', watch_details: '', amount_paid: '0.00', remark: '', product_id: '', status: 'pending', type: 'pre_order', product_item_id: '' });
const statuses = { pending: 'Pending', ordered: 'Ordered', sold_out: 'Sold Out', completed: 'Completed', cancelled: 'Cancelled' };
const formStatuses = computed(() => form.type === 'reservation'
    ? { pending: 'Reserved', completed: 'Completed (mark watch sold)', cancelled: 'Cancelled (release watch)' }
    : { pending: 'Pending', ordered: 'Ordered', sold_out: 'Sold Out', cancelled: 'Cancelled' });
const reservationItems = computed(() => {
    const items = props.availableItems.filter(item => String(item.product_id) === String(form.product_id));
    const held = editing.value?.reserved_item;
    if (held && String(held.product_id) === String(form.product_id) && !items.some(item => item.id === held.id)) items.push(held);
    return items;
});
const changeType = () => { form.status = 'pending'; form.product_item_id = ''; };

const statusClasses = { pending: 'bg-yellow-100 text-yellow-800', ordered: 'bg-blue-100 text-blue-800', sold_out: 'bg-red-100 text-red-800', completed: 'bg-green-100 text-green-800', cancelled: 'bg-gray-100 text-gray-700' };
const productSearch = ref('');
const productLabel = (product) => `#${product.id} — ${product.name}${product.model_number ? ` — ${product.model_number}` : ''}`;
const productOptions = computed(() => {
    const options = props.products.filter(product => String(product.brand_id) === String(form.brand_id));
    if (editing.value?.product && String(editing.value.product.brand_id) === String(form.brand_id)
        && !options.some(product => product.id === editing.value.product_id)) {
        options.push(editing.value.product);
    }
    return options;
});
const filteredProducts = computed(() => {
    const terms = productSearch.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    return productOptions.value.filter(product => terms.every(term => productLabel(product).toLowerCase().includes(term)));
});
const selectedProductLabel = (id) => {
    const product = productOptions.value.find(product => String(product.id) === String(id));
    return product ? productLabel(product) : '';
};
const refreshing = ref(false);
const refreshStock = () => {
    if (refreshing.value || isOpen.value || attachmentId.value || filtering.value) return;
    refreshing.value = true;
    router.reload({ only: ['preOrders', 'products', 'availableItems'], onFinish: () => { refreshing.value = false; } });
};
let stockTimer;
onMounted(() => { stockTimer = window.setInterval(() => { if (!document.hidden) refreshStock(); }, 30000); });
onUnmounted(() => window.clearInterval(stockTimer));
const customerOptions = computed(() => {
    const options = [...props.customers];
    if (editing.value?.customer && !options.some(customer => customer.id === editing.value.customer_id)) {
        options.push(editing.value.customer);
    }
    return options;
});
const brandOptions = computed(() => {
    const options = [...props.brands];
    if (editing.value?.brand && !options.some(brand => brand.id === editing.value.brand_id)) {
        options.push(editing.value.brand);
    }
    return options;
});
const open = (preOrder = null) => {
    productSearch.value = '';
    editing.value = preOrder;
    form.reset();
    form.clearErrors();
    if (preOrder) {
        for (const key of ['customer_id', 'brand_id', 'watch_details', 'amount_paid', 'remark', 'product_id', 'status', 'type', 'product_item_id']) {
            form[key] = preOrder[key] ?? '';
        }
    }
    isOpen.value = true;
};
const close = () => {
    if (!form.processing) isOpen.value = false;
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { isOpen.value = false; } };
    if (editing.value) form.put(route('pre-orders.update', editing.value.id), options);
    else form.post(route('pre-orders.store'), options);
};
const formatAmount = (amount) => Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Pre Orders & Reservations" />
    <AdminLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Pre Orders &amp; Reservations</h1>
                <p class="mt-2 text-sm text-gray-600">Track advance watch requests or reserve a specific in-stock watch for a customer.</p>
            </div>
            <div class="flex items-center gap-4">
                <Link :href="route('pos.index')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Back to POS</Link>
                <SecondaryButton :disabled="refreshing" @click="refreshStock">{{ refreshing ? 'Refreshing…' : 'Refresh Stock' }}</SecondaryButton>
                <PrimaryButton @click="open()">Add Pre Order / Reservation</PrimaryButton>
            </div>
        </div>

        <form @submit.prevent="applyFilters" class="mb-4 grid gap-4 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <InputLabel for="filter-type" value="Type" />
                <select id="filter-type" v-model="filters.type" class="mt-1 w-full rounded-md border-gray-300">
                    <option value="">All types</option><option value="pre_order">Pre-order</option><option value="reservation">Reservation</option>
                </select>
            </div>
            <div>
                <InputLabel for="filter-status" value="Status" />
                <select id="filter-status" v-model="filters.status" class="mt-1 w-full rounded-md border-gray-300">
                    <option value="">All statuses</option>
                    <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="filter-brand" value="Brand" />
                <select id="filter-brand" v-model="filters.brand_id" @change="filters.product_id = ''; filterSearch = ''" class="mt-1 w-full rounded-md border-gray-300">
                    <option value="">All brands</option>
                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="filter-customer" value="Customer" />
                <select id="filter-customer" v-model="filters.customer_id" class="mt-1 w-full rounded-md border-gray-300">
                    <option value="">All customers</option>
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` — ${customer.phone}` : '' }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="filter-product" value="Product" />
                <Combobox :key="filters.brand_id" v-model="filters.product_id" as="div" class="relative mt-1" @update:model-value="filterSearch = ''">
                    <div class="relative">
                        <ComboboxInput id="filter-product" :display-value="filterProductLabel" @change="filterSearch = $event.target.value" placeholder="Search products…" autocomplete="off" class="w-full rounded-md border-gray-300 pr-10" />
                        <ComboboxButton class="absolute inset-y-0 right-0 px-3 text-gray-500" aria-label="Show product filters" @click="filterSearch = ''">▾</ComboboxButton>
                    </div>
                    <ComboboxOptions class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/10">
                        <ComboboxOption v-slot="{ active }" value="" as="template"><li class="cursor-pointer px-3 py-2" :class="active ? 'bg-gold-50' : ''">All products</li></ComboboxOption>
                        <ComboboxOption v-for="product in filterProductOptions" :key="product.id" :value="product.id" v-slot="{ active, selected }" as="template">
                            <li class="cursor-pointer px-3 py-2" :class="[active ? 'bg-gold-50' : '', selected ? 'font-semibold' : '']">{{ productLabel(product) }}</li>
                        </ComboboxOption>
                        <li v-if="!filterProductOptions.length" class="px-3 py-2 text-gray-500" role="presentation">No matching products found.</li>
                    </ComboboxOptions>
                </Combobox>
            </div>
            <div class="flex gap-3 sm:col-span-2 xl:col-span-4">
                <PrimaryButton :disabled="filtering">{{ filtering ? 'Applying…' : 'Apply Filters' }}</PrimaryButton>
                <SecondaryButton :disabled="filtering" @click="clearFilters">Clear Filters</SecondaryButton>
            </div>
        </form>

        <p class="mb-3 text-xs text-gray-500">Stock availability refreshes every 30 seconds while this page is visible. Reserved and sold watches are excluded.</p>
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Pre Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Brand / Watch</th>
                            <th class="px-4 py-3 text-right">Amount Paid (MMK)</th>
                            <th class="px-4 py-3">Remark</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Stock</th>
                            <th class="px-4 py-3">Created By</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="preOrder in preOrders.data" :key="preOrder.id" class="align-top hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-4">
                                <div class="font-semibold text-gray-900">PO-{{ preOrder.id }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ preOrder.created_at.slice(0, 10) }}</div>
                                <div class="mt-1 text-xs font-semibold text-gray-600">{{ preOrder.type === 'reservation' ? 'Reservation' : 'Pre-order' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900">{{ preOrder.customer?.name }}</div>
                                <div class="mt-1 text-gray-500">{{ preOrder.customer?.phone }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900">{{ preOrder.brand?.name }}</div>
                                <div class="mt-1 max-w-xs whitespace-pre-wrap break-words text-gray-500">{{ preOrder.watch_details || '—' }}</div>
                                <div v-if="preOrder.reserved_item" class="mt-1 text-xs text-gray-600">System code: {{ preOrder.reserved_item.system_unique_id }}</div>
                                <div v-if="preOrder.product" class="mt-2 text-xs text-gray-600">
                                    Product #{{ preOrder.product_id }}: {{ preOrder.product.name }}
                                    <span v-if="preOrder.product.model_number"> — {{ preOrder.product.model_number }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gold-600">{{ formatAmount(preOrder.amount_paid) }}</td>
                            <td class="min-w-48 max-w-sm whitespace-pre-wrap break-words px-4 py-4 text-gray-600">{{ preOrder.remark || '—' }}</td>
                            <td class="px-4 py-4"><span class="inline-block whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold" :class="statusClasses[preOrder.status]">{{ preOrder.type === 'reservation' && preOrder.status === 'pending' ? 'Reserved' : statuses[preOrder.status] }}</span></td>
                            <td class="px-4 py-4">
                                <span v-if="preOrder.type === 'reservation' && preOrder.status === 'pending'" class="font-semibold text-blue-700">Held for customer<br /><span class="text-xs">{{ preOrder.reserved_item?.system_unique_id }}</span></span>
                                <span v-else-if="!preOrder.product" class="text-gray-400">No product linked</span>
                                <span v-else-if="preOrder.product.deleted_at" class="text-gray-500">Product archived</span>
                                <span v-else-if="preOrder.product.available_stock > 0" class="whitespace-nowrap font-semibold text-green-700">In stock ({{ preOrder.product.available_stock }})</span>
                                <span v-else class="whitespace-nowrap text-gray-500">Out of stock</span>
                            </td>
                            <td class="px-4 py-4 text-gray-600">
                                <div>{{ preOrder.user?.name || '—' }}</div>
                                <div class="mt-1 whitespace-nowrap text-xs text-gray-400">{{ new Date(preOrder.created_at).toLocaleString() }}</div>
                            </td>
                            <td class="px-4 py-4 text-right"><button @click="open(preOrder)" class="font-semibold text-gold-600 hover:text-gold-800">Edit</button>
                                <button @click="attachmentId = preOrder.id" class="mt-2 block w-full whitespace-nowrap font-semibold text-gray-600 hover:text-gray-900">Files ({{ preOrder.file_uploads.length }})</button>
                            </td>
                        </tr>
                        <tr v-if="!preOrders.data.length"><td colspan="9" class="px-4 py-12 text-center text-gray-500">No pre-orders found. Adjust the filters or add a new pre-order.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 text-sm">
                <span class="text-gray-500">{{ preOrders.total }} pre-orders</span>
                <nav class="flex gap-4" aria-label="Pagination">
                    <Link v-if="preOrders.prev_page_url" :href="preOrders.prev_page_url" class="font-semibold text-gray-700">Previous</Link>
                    <span class="text-gray-500">Page {{ preOrders.current_page }} of {{ preOrders.last_page }}</span>
                    <Link v-if="preOrders.next_page_url" :href="preOrders.next_page_url" class="font-semibold text-gray-700">Next</Link>
                </nav>
            </div>
        </div>

        <Modal :show="!!attachmentOrder" :closeable="!attachmentBusy" @close="attachmentId = null">
            <div v-if="attachmentOrder" class="p-4">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold">Files for PO-{{ attachmentOrder.id }}</h2>
                    <SecondaryButton :disabled="attachmentBusy" @click="attachmentId = null">Close</SecondaryButton>
                </div>
                <OrderAttachments :key="attachmentOrder.id" :order-id="attachmentOrder.id" :files="attachmentOrder.file_uploads" pre-order @busy="attachmentBusy = $event" />
            </div>
        </Modal>

        <Modal :show="isOpen" :closeable="!form.processing" @close="close">
            <form @submit.prevent="submit" class="space-y-4 p-6">
                <h2 class="text-lg font-semibold text-gray-900">{{ editing ? `Edit Pre Order PO-${editing.id}` : 'Add Pre Order / Reservation' }}</h2>
                <div>
                    <InputLabel for="record-type" value="Type" />
                    <select id="record-type" v-model="form.type" @change="changeType" class="mt-1 block w-full rounded-md border-gray-300"><option value="pre_order">Pre-order — waiting for stock</option><option value="reservation">Reservation — hold an in-stock watch</option></select>
                    <InputError :message="form.errors.type" />
                </div>
                <div>
                    <InputLabel for="pre-order-customer" value="Customer" />
                    <select id="pre-order-customer" v-model="form.customer_id" required class="mt-1 block w-full rounded-md border-gray-300 focus:border-gold-500 focus:ring-gold-500">
                        <option disabled value="">Choose customer</option>
                        <option v-for="customer in customerOptions" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` — ${customer.phone}` : '' }}</option>
                    </select>
                    <p v-if="!customers.length" class="mt-1 text-sm text-gray-600">Add a customer from the Customers page first.</p>
                    <InputError :message="form.errors.customer_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="pre-order-brand" value="Watch Brand" />
                    <select id="pre-order-brand" v-model="form.brand_id" @change="form.product_id = ''; form.product_item_id = ''; productSearch = ''" required class="mt-1 block w-full rounded-md border-gray-300 focus:border-gold-500 focus:ring-gold-500">
                        <option disabled value="">Choose brand</option>
                        <option v-for="brand in brandOptions" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">No system code required. Add new brands from the Brands page.</p>
                    <InputError :message="form.errors.brand_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="pre-order-product" :value="form.type === 'reservation' ? 'Product (required)' : 'Product (optional)'" />
                    <Combobox :key="form.brand_id" v-model="form.product_id" :disabled="!form.brand_id" as="div" class="relative mt-1" @update:model-value="productSearch = ''; form.product_item_id = ''">
                        <div class="relative">
                            <ComboboxInput id="pre-order-product" :display-value="selectedProductLabel"
                                @change="productSearch = $event.target.value"
                                placeholder="Search by product name, model, or ID…" autocomplete="off"
                                class="block w-full rounded-md border-gray-300 pr-10 focus:border-gold-500 focus:ring-gold-500 disabled:bg-gray-100 disabled:text-gray-400" />
                            <ComboboxButton class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500" aria-label="Show products" @click="productSearch = ''">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 011.06 0L10 10.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 8.28a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>
                            </ComboboxButton>
                        </div>
                        <ComboboxOptions class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/10 focus:outline-none">
                            <ComboboxOption v-slot="{ active }" value="" as="template">
                                <li class="cursor-pointer px-3 py-2" :class="active ? 'bg-gold-50 text-gold-800' : 'text-gray-500'">No product linked</li>
                            </ComboboxOption>
                            <ComboboxOption v-for="product in filteredProducts" :key="product.id" :value="product.id" v-slot="{ active, selected }" as="template">
                                <li class="cursor-pointer px-3 py-2" :class="[active ? 'bg-gold-50 text-gold-800' : 'text-gray-900', selected ? 'font-semibold' : '']">{{ productLabel(product) }}</li>
                            </ComboboxOption>
                            <li v-if="!filteredProducts.length" class="px-3 py-2 text-gray-500" role="presentation">No matching products found.</li>
                        </ComboboxOptions>
                    </Combobox>
                    <p class="mt-1 text-xs text-gray-500">Choose a brand, then type to search its products. Products with no stock can also be linked.</p>
                    <InputError :message="form.errors.product_id" class="mt-1" />
                </div>
                <div v-if="form.type === 'reservation'">
                    <InputLabel for="reservation-item" value="Watch / System Code (required)" />
                    <select id="reservation-item" v-model="form.product_item_id" required :disabled="!form.product_id" class="mt-1 block w-full rounded-md border-gray-300">
                        <option disabled value="">Choose an available watch</option>
                        <option v-for="item in reservationItems" :key="item.id" :value="item.id">{{ item.system_unique_id }}{{ item.serial_number ? ` — ${item.serial_number}` : '' }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Saving holds this exact watch and removes it from POS availability. Cancel to release it. Complete to mark it sold; this does not create a POS invoice.</p>
                    <InputError :message="form.errors.product_item_id" />
                </div>
                <div>
                    <InputLabel for="pre-order-status" value="Status" />
                    <select id="pre-order-status" v-model="form.status" required class="mt-1 block w-full rounded-md border-gray-300 focus:border-gold-500 focus:ring-gold-500">
                        <option v-for="(label, value) in formStatuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <InputError :message="form.errors.status" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="pre-order-watch" value="Watch / Model Details (optional)" />
                    <TextInput id="pre-order-watch" v-model="form.watch_details" maxlength="255" class="mt-1 block w-full" placeholder="Watch name, model, or reference number" />
                    <InputError :message="form.errors.watch_details" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="pre-order-paid" value="Total Amount Paid (MMK)" />
                    <TextInput id="pre-order-paid" v-model="form.amount_paid" type="number" min="0" max="9999999999999.99" step="0.01" required class="mt-1 block w-full" />
                    <p class="mt-1 text-xs text-gray-500">Enter the total paid so far, including any earlier payments.</p>
                    <InputError :message="form.errors.amount_paid" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="pre-order-remark" value="Remark (optional)" />
                    <textarea id="pre-order-remark" v-model="form.remark" rows="4" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300 focus:border-gold-500 focus:ring-gold-500" placeholder="Customer requests, payment notes, or expected arrival…"></textarea>
                    <InputError :message="form.errors.remark" class="mt-1" />
                </div>
                <p class="text-xs text-gray-500">After saving, use Files in the table to upload payment slips and supporting documents.</p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton :disabled="form.processing" @click="close">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save Pre Order' }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
