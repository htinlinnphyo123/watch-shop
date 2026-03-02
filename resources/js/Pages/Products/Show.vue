<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

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
    serial_number: '',
    purchase_date: '',
    status: 'available',
});

const editingItemId = ref(null);
const editForm = useForm({
    serial_number: '',
    system_unique_id: '',
    status: '',
});

const startEdit = (item) => {
    editingItemId.value = item.id;
    editForm.serial_number = item.serial_number;
    editForm.system_unique_id = item.system_unique_id || '';
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
                                 <div v-if="product.watch_type" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Watch Type</span><span class="text-gray-900 font-medium">{{ product.watch_type }}</span></div>
                                 <div v-if="product.gender" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Gender</span><span class="text-gray-900 font-medium">{{ product.gender }}</span></div>
                                 <div v-if="product.movement" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Movement</span><span class="text-gray-900 font-medium">{{ product.movement }}</span></div>
                                 <div v-if="product.glass" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Glass</span><span class="text-gray-900 font-medium">{{ product.glass }}</span></div>
                                 <div v-if="product.water_resistant" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Water Res.</span><span class="text-gray-900 font-medium">{{ product.water_resistant }}</span></div>
                                 <div v-if="product.shape" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Shape</span><span class="text-gray-900 font-medium">{{ product.shape }}</span></div>
                                 <div v-if="product.dial_size" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Dial Size</span><span class="text-gray-900 font-medium">{{ product.dial_size }}</span></div>
                                 <div v-if="product.dial_color" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Dial Color</span><span class="text-gray-900 font-medium">{{ product.dial_color }}</span></div>
                                 <div v-if="product.dial_markings" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Dial Markings</span><span class="text-gray-900 font-medium">{{ product.dial_markings }}</span></div>
                                 <div v-if="product.band" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Material</span><span class="text-gray-900 font-medium">{{ product.band }}</span></div>
                                 <div v-if="product.band_color" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Color</span><span class="text-gray-900 font-medium">{{ product.band_color }}</span></div>
                                 <div v-if="product.band_size" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Size</span><span class="text-gray-900 font-medium">{{ product.band_size }}</span></div>
                                 <div v-if="product.lug_width" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Lug Width</span><span class="text-gray-900 font-medium">{{ product.lug_width }}</span></div>
                                 <div v-if="product.strap_buckle" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Strap Buckle</span><span class="text-gray-900 font-medium">{{ product.strap_buckle }}</span></div>
                                 <div v-if="product.case_material" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Material</span><span class="text-gray-900 font-medium">{{ product.case_material }}</span></div>
                                 <div v-if="product.case_color" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Color</span><span class="text-gray-900 font-medium">{{ product.case_color }}</span></div>
                                 <div v-if="product.case_thickness" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Thickness</span><span class="text-gray-900 font-medium">{{ product.case_thickness }}</span></div>
                                 <div v-if="product.case_finish" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Case Finish</span><span class="text-gray-900 font-medium">{{ product.case_finish }}</span></div>
                                 <div v-if="product.battery_type" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Battery Type</span><span class="text-gray-900 font-medium">{{ product.battery_type }}</span></div>
                                 <div v-if="product.couple" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Couple Watch</span><span class="text-gray-900 font-medium">{{ product.couple }}</span></div>
                             </div>
                         </div>
                     </div>
                 </div>
                 
                 <!-- Stock Management -->
                 <div class="md:w-2/3 space-y-6">
                     <!-- Add Stock Form -->
                     <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                         <h3 class="text-lg font-bold text-gray-900 mb-4">Add Stock (Serial Number)</h3>
                         <form @submit.prevent="submitItem" class="flex items-end gap-4">
                             <div class="flex-1">
                                 <InputLabel value="Serial Number" class="text-gray-600" />
                                 <TextInput type="text" v-model="form.serial_number" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500" required placeholder="SN-12345" />
                                 <InputError :message="form.errors.serial_number" class="mt-1" />
                             </div>
                             <div class="w-40">
                                 <InputLabel value="Purchase Date" class="text-gray-600" />
                                 <TextInput type="date" v-model="form.purchase_date" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500" />
                             </div>
                             <PrimaryButton class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold h-[42px]" :disabled="form.processing">
                                 Add Item
                             </PrimaryButton>
                         </form>
                     </div>
                     
                     <!-- Stock List -->
                     <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                            <h3 class="text-lg font-bold text-gray-900">Stock Inventory</h3>
                            <span class="text-gold-600 font-bold">{{ (items || []).filter(i => i.status === 'available').length }} Available</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial Number</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">System Code</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added On</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50 transition-colors">
                                    <template v-if="editingItemId === item.id">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <input type="text" v-model="editForm.serial_number" class="w-full min-w-[130px] text-sm border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500" />
                                            <InputError :message="editForm.errors.serial_number" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <input type="text" v-model="editForm.system_unique_id" class="w-full min-w-[120px] text-sm border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500" placeholder="System Code" />
                                            <InputError :message="editForm.errors.system_unique_id" class="mt-1" />
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
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-mono">{{ item.serial_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-xs">{{ item.system_unique_id || '-' }}</td>
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
</template>
