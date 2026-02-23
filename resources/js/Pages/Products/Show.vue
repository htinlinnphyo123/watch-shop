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
                         <img v-if="product.image" :src="'/storage/' + product.image" class="w-full h-64 object-cover rounded mb-4 shadow-sm" />
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
                                 <span class="text-gray-500">Category</span>
                                 <span class="text-gray-900 font-medium">{{ product.category?.name }}</span>
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
                                 <div v-if="product.band" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Material</span><span class="text-gray-900 font-medium">{{ product.band }}</span></div>
                                 <div v-if="product.band_color" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Color</span><span class="text-gray-900 font-medium">{{ product.band_color }}</span></div>
                                 <div v-if="product.band_size" class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Band Size</span><span class="text-gray-900 font-medium">{{ product.band_size }}</span></div>
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
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial Number</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added On</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-mono">{{ item.serial_number }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-800': item.status === 'available',
                                                'bg-red-100 text-red-800': item.status === 'sold',
                                                'bg-yellow-100 text-yellow-800': item.status === 'returned',
                                                 'bg-gray-100 text-gray-800': item.status === 'lost'
                                            }">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ formatDate(item.created_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button v-if="item.status === 'available'" @click="deleteItem(item)" class="text-red-600 hover:text-red-800">Remove</button>
                                        <span v-else class="text-gray-400 cursor-not-allowed">Sold</span>
                                    </td>
                                </tr>
                                <tr v-if="items.length === 0">
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No stock items added yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                 </div>
             </div>
        </div>
    </AdminLayout>
</template>
