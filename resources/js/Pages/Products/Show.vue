<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, nextTick } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import JsBarcode from 'jsbarcode';

const props = defineProps({
    product: {
        type: Object,
        default: () => ({}),
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    quantity: 1,
    purchase_date: '',
    status: 'available',
});

const editingItemId = ref(null);
const editForm = useForm({
    serial_number: '',
    system_unique_id: '',
    purchase_date: '',
    status: '',
});

const startEdit = (item) => {
    editingItemId.value = item.id;
    editForm.serial_number = item.serial_number || '';
    editForm.system_unique_id = item.system_unique_id || '';
    editForm.purchase_date = item.purchase_date ? item.purchase_date.substring(0, 10) : '';
    editForm.status = item.status;
};

const cancelEdit = () => {
    editingItemId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = (item) => {
    editForm.put(route('items.update', item.id), {
        onSuccess: () => {
            editingItemId.value = null;
            editForm.reset();
        },
    });
};

const submitItem = () => {
    form.post(route('products.items.store', props.product.id), {
        onSuccess: () => form.reset(),
    });
};

const deleteItem = (item) => {
    if (confirm('Are you sure you want to remove this item from stock?')) {
        useForm({}).delete(route('items.destroy', item.id));
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
};

// ─── Barcode Label Generation ─────────────────────────────────────────────────
const page = usePage();
const isPrintModalOpen = ref(false);
const selectedLabels = ref([]);
const labelRefs = ref({});

const openPrintModal = () => {
    selectedLabels.value = [...props.items];
    isPrintModalOpen.value = true;
    // Render barcodes after modal is visible
    nextTick(() => renderAllBarcodes());
};

const renderAllBarcodes = () => {
    props.items.forEach(item => {
        const svgEl = document.getElementById('barcode-svg-' + item.id);
        if (svgEl && item.system_unique_id) {
            try {
                JsBarcode(svgEl, item.system_unique_id, {
                    format: 'CODE128',
                    width: 2.2,
                    height: 60,
                    displayValue: true,
                    fontSize: 12,
                    fontOptions: 'bold',
                    margin: 6,
                    marginTop: 4,
                    marginBottom: 4,
                    background: '#ffffff',
                    lineColor: '#000000',
                    textMargin: 4,
                });
            } catch (e) {
                console.warn('Barcode error for', item.system_unique_id, e);
            }
        }
    });
};

const printLabels = () => {
    const printWindow = window.open('', '_blank');
    const labelEls = document.querySelectorAll('.barcode-label-card');
    let labelsHtml = '';
    labelEls.forEach(el => {
        labelsHtml += el.outerHTML;
    });
    printWindow.document.write(`
        <!DOCTYPE html><html><head>
        <title>Barcode Labels - ${props.product.name}</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #fff; }
            .labels-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; padding: 8px; }
            .barcode-label-card {
                border: 1px solid #e5e7eb;
                border-radius: 4px;
                padding: 6px 8px;
                text-align: center;
                page-break-inside: avoid;
                background: #fff;
            }
            .label-barcode svg { display: block; margin: 0 auto; width: 100%; height: auto; }
            @media print { body { margin: 0; } .no-print { display: none !important; } }
        </style>
        </head><body>
        <div class="labels-grid">${labelsHtml}</div>
        </body></html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 500);
};
// ─────────────────────────────────────────────────────────────────────────────
</script>

<template>
    <Head :title="product.name" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ product.name }}
            </h2>
        </template>

        <div class="mb-6">
             <Link :href="route('products.index')" class="text-gray-500 hover:text-gray-900 mb-4 inline-block transition-colors">&larr; Back to Watches</Link>
             
             <div class="flex flex-col md:flex-row gap-8">
                 <!-- Product Info -->
                 <div class="md:w-1/3 bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                     <div class="flex flex-col items-center">
                         <div v-if="product.images && product.images.length > 0" class="w-full grid gap-4 mb-4">
                             <img v-for="image in product.images" :key="image" :src="$page.props.storage_url + '/' + image" class="w-full h-64 object-cover rounded shadow-sm" />
                         </div>
                         <div v-else-if="product.image" class="w-full mb-4">
                             <img :src="$page.props.storage_url + '/' + product.image" class="w-full h-64 object-cover rounded shadow-sm" />
                         </div>
                         <div v-else class="w-full h-64 bg-gray-100 rounded mb-4 flex items-center justify-center text-gray-400">No Image</div>
                         
                         <h1 class="text-2xl font-bold text-gray-900 text-center">{{ product.name }}</h1>
                         <p class="text-gold-600 font-bold text-xl mt-2">{{ parseInt(product.price).toLocaleString() }} {{ product.currency }}</p>
                         <p v-if="product.currency !== 'MMK'" class="text-sm text-gray-500 font-bold mt-1">
                             ≈ {{ (parseFloat(product.price) * parseFloat($page.props.settings[product.currency.toLowerCase() + '_rate'] || 1)).toLocaleString() }} MMK
                         </p>
                         
                         <div class="w-full mt-6 space-y-3 text-sm">
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Brand</span>
                                 <span class="text-gray-900 font-medium">{{ product.brand?.name }}</span>
                             </div>
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Categories</span>
                                 <span class="text-gray-900 font-medium text-right ml-2">{{ product.categories?.map(c => c.name).join(', ') || '-' }}</span>
                             </div>
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Model</span>
                                 <span class="text-gray-900 font-medium">{{ product.model_number }}</span>
                             </div>
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Barcode</span>
                                 <span class="text-gray-900 font-medium">{{ product.barcode }}</span>
                             </div>
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Warranty</span>
                                 <span class="text-gray-900 font-medium">{{ product.warranty_period }} Months</span>
                             </div>
                         </div>

                         <!-- Specifications Grid -->
                         <div class="w-full mt-6 pt-6 border-t border-gray-100">
                             <h3 class="text-md font-bold text-gray-900 mb-4">Specifications</h3>
                             <div class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Watch Type</span><span class="text-gray-900 font-medium">{{ product.watch_type || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Gender</span><span class="text-gray-900 font-medium">{{ product.gender || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Movement</span><span class="text-gray-900 font-medium">{{ product.movement || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Glass / Crystal</span><span class="text-gray-900 font-medium">{{ product.glass || product.crystal || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Water Resistant</span><span class="text-gray-900 font-medium">{{ product.water_resistant || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Shape</span><span class="text-gray-900 font-medium">{{ product.shape || product.case_shape || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Dial Size</span><span class="text-gray-900 font-medium">{{ product.dial_size || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Dial Color</span><span class="text-gray-900 font-medium">{{ product.dial_color || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Dial Markings</span><span class="text-gray-900 font-medium">{{ product.dial_markings || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Material</span><span class="text-gray-900 font-medium">{{ product.band || product.strap_material || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Color</span><span class="text-gray-900 font-medium">{{ product.band_color || product.strap_color || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Size</span><span class="text-gray-900 font-medium">{{ product.band_size || product.strap_size || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Lug Width</span><span class="text-gray-900 font-medium">{{ product.lug_width || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Clasp Type</span><span class="text-gray-900 font-medium">{{ product.strap_buckle || product.clasp_type || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Material</span><span class="text-gray-900 font-medium">{{ product.case_material || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Color</span><span class="text-gray-900 font-medium">{{ product.case_color || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Thickness</span><span class="text-gray-900 font-medium">{{ product.case_thickness || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Finish</span><span class="text-gray-900 font-medium">{{ product.case_finish || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Battery Type</span><span class="text-gray-900 font-medium">{{ product.battery_type || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Couple Watch</span><span class="text-gray-900 font-medium">{{ product.couple || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Origin</span><span class="text-gray-900 font-medium">{{ product.origin || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Quick Release</span><span class="text-gray-900 font-medium">{{ product.quick_release || '-' }}</span></div>
                             </div>
                         </div>
                     </div>
                 </div>
                 
                 <!-- Stock Management -->
                 <div class="md:w-2/3 space-y-6">
                      <!-- Add Stock Form -->
                      <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                          <h3 class="text-lg font-bold text-gray-900 mb-1">Add Stock</h3>
                          <p class="text-xs text-gray-500 mb-4">Enter quantity — each unit gets an auto system code. Optionally paste serial numbers (one per line).</p>
                          <form @submit.prevent="submitItem" class="space-y-4">
                              <div class="flex items-end gap-4">
                                  <div class="w-28">
                                      <InputLabel value="Quantity *" class="text-gray-600" />
                                      <TextInput type="number" v-model="form.quantity" min="1" max="500" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500" required />
                                      <InputError :message="form.errors.quantity" class="mt-1" />
                                  </div>
                                  <div class="w-44">
                                      <InputLabel value="Purchase Date" class="text-gray-600" />
                                      <TextInput type="date" v-model="form.purchase_date" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500" />
                                  </div>
                                  <div class="flex-1">
                                      <InputLabel value="Status" class="text-gray-600" />
                                      <select v-model="form.status" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm text-sm">
                                          <option value="available">Available</option>
                                          <option value="reserved">Reserved</option>
                                      </select>
                                  </div>
                                  <PrimaryButton class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :disabled="form.processing">
                                      Add {{ form.quantity }} Item{{ form.quantity > 1 ? 's' : '' }}
                                  </PrimaryButton>
                              </div>
                          </form>
                      </div>
                     
                     <!-- Stock List -->
                     <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                         <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                             <h3 class="text-lg font-bold text-gray-900">Stock Inventory</h3>
                             <div class="flex items-center gap-3">
                                 <button
                                     @click="openPrintModal"
                                     class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gold-50 hover:border-gold-400 hover:text-gold-700 transition-colors shadow-sm"
                                 >
                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                     Print Labels
                                 </button>
                                 <span class="text-gold-600 font-bold">{{ (items || []).filter(i => i.status === 'available').length }} Available</span>
                             </div>
                         </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial Number</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">System Code</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added On</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50 transition-colors">
                                    <template v-if="editingItemId === item.id">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <input type="text" v-model="editForm.serial_number" class="w-full min-w-[130px] text-sm border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500" placeholder="Optional" />
                                            <InputError :message="editForm.errors.serial_number" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <input type="text" v-model="editForm.system_unique_id" class="w-full min-w-[120px] text-sm border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500" placeholder="System Code" />
                                            <InputError :message="editForm.errors.system_unique_id" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <input type="date" v-model="editForm.purchase_date" class="w-full min-w-[140px] text-sm border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500" />
                                            <InputError :message="editForm.errors.purchase_date" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <select v-model="editForm.status" class="w-full min-w-[110px] text-sm border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500">
                                                <option value="available">Available</option>
                                                <option value="sold">Sold</option>
                                                <option value="reserved">Reserved</option>
                                                <option value="returned">Returned</option>
                                                <option value="lost">Lost</option>
                                                <option value="damaged">Damaged</option>
                                            </select>
                                            <InputError :message="editForm.errors.status" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-gray-500 text-sm">-</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button @click="saveEdit(item)" class="inline-flex items-center px-3 py-1.5 bg-green-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 focus:bg-green-600 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150" :disabled="editForm.processing">Save</button>
                                            <button @click="cancelEdit()" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">Cancel</button>
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono">
                                            <span v-if="item.serial_number" class="text-gray-900">{{ item.serial_number }}</span>
                                            <span v-else class="text-gray-400 italic text-xs">No serial</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-xs">{{ item.system_unique_id || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ item.purchase_date ? formatDate(item.purchase_date) : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="{
                                                    'bg-green-100 text-green-800': item.status === 'available',
                                                    'bg-red-100 text-red-800': item.status === 'sold',
                                                    'bg-yellow-100 text-yellow-800': item.status === 'reserved' || item.status === 'returned',
                                                    'bg-gray-100 text-gray-800': item.status === 'lost',
                                                    'bg-orange-100 text-orange-800': item.status === 'damaged'
                                                }">
                                                {{ item.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ formatDate(item.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                            <button @click="startEdit(item)" class="text-gold-600 hover:text-gold-800">Edit</button>
                                            <button v-if="item.status === 'available'" @click="deleteItem(item)" class="text-red-600 hover:text-red-800">Remove</button>
                                        </td>
                                    </template>
                                </tr>
                                <tr v-if="items.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No stock items added yet.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                 </div>
             </div>
        </div>
    </AdminLayout>

    <!-- ── Print Barcode Labels Modal ───────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="isPrintModalOpen" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gold-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Barcode Labels
                        </h2>
                        <p class="text-sm text-gray-400 mt-0.5">{{ props.items.length }} label{{ props.items.length !== 1 ? 's' : '' }} for {{ product.name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            @click="printLabels"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gold-500 hover:bg-gold-600 text-dark-900 font-bold rounded-lg text-sm transition-colors shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print All
                        </button>
                        <button @click="isPrintModalOpen = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Labels Grid -->
                <div class="p-6">
                    <div v-if="props.items.length === 0" class="text-center py-16 text-gray-400">
                        No stock items to generate labels for.
                    </div>
                    <!-- 2-column grid of wide horizontal labels -->
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                            v-for="item in props.items"
                            :key="item.id"
                            class="barcode-label-card border border-gray-200 rounded-lg px-4 py-3 bg-white shadow-sm hover:shadow-md transition-shadow"
                        >
                            <!-- Barcode SVG only — full width, wide and short -->
                            <svg :id="'barcode-svg-' + item.id" class="w-full h-auto block"></svg>
                        </div>
                    </div>
                </div>

                <div class="px-6 pb-4 flex justify-end border-t border-gray-100 pt-4">
                    <button @click="isPrintModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Close</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
