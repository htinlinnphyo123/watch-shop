<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    products: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    brands: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

console.log('Products Data:', props.products);

const page = usePage();
const displayCurrency = ref('MMK'); // Default view

// Helper to calculate the displayed price in the selected displayCurrency
const getDisplayPrice = (product) => {
    if (!product) return 0;
    
    // First, convert product's base price to MMK (if it isn't already)
    let productMmkRate = 1;
    if (product.currency && product.currency !== 'MMK') {
        productMmkRate = parseFloat(page.props.settings[product.currency.toLowerCase() + '_rate'] || 1);
    }
    const mmkPrice = parseFloat(product.price) * productMmkRate;

    // Second, convert from MMK to the target display currency
    if (displayCurrency.value === 'MMK') {
        return mmkPrice;
    } else {
        const targetRate = parseFloat(page.props.settings[displayCurrency.value.toLowerCase() + '_rate'] || 1);
        if (targetRate > 0) {
             return mmkPrice / targetRate;
        }
        return mmkPrice; // Fallback
    }
};

const formatPrice = (amount) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: displayCurrency.value === 'MMK' ? 0 : 2,
        maximumFractionDigits: displayCurrency.value === 'MMK' ? 0 : 2
    }).format(amount);
};

const form = useForm({
    brand_id: '',
    category_id: '',
    name: '',
    model_number: '',
    price: '',
    cost_price: '',
    warranty_period: 12, // default 12 months
    description: '',
    image: null,
    barcode: '', // Optional, will generate if empty
    currency: 'MMK',
});

const isModalOpen = ref(false);
const editingProduct = ref(null);
const imageInput = ref(null);

const openModal = (product = null) => {
    editingProduct.value = product;
    if (product) {
        form.brand_id = product.brand_id;
        form.category_id = product.category_id;
        form.name = product.name;
        form.model_number = product.model_number;
        form.price = product.price;
        form.cost_price = product.cost_price;
        form.warranty_period = product.warranty_period;
        form.description = product.description;
        form.barcode = product.barcode;
        form.currency = product.currency || 'MMK';
        form.image = null;
    } else {
        form.reset();
        form.image = null;
        form.currency = 'MMK';
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingProduct.value = null;
    if (imageInput.value) imageInput.value.value = null;
};

const submit = () => {
    if (editingProduct.value) {
        router.post(route('products.update', editingProduct.value.id), {
            _method: 'put',
            ...form.data(),
            image: form.image,
        }, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('products.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteProduct = (product) => {
    if (confirm('Are you sure you want to delete this product?')) {
        useForm({}).delete(route('products.destroy', product.id));
    }
};
</script>

<template>
    <Head title="Watches" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Watches</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Watches</h1>
            
            <div class="flex items-center gap-4">
                <!-- Currency Toggle -->
                <div class="bg-gray-200 p-1 rounded-lg flex items-center shadow-inner">
                    <button 
                        v-for="currency in ['MMK', 'USD', 'THB']" :key="currency"
                        @click="displayCurrency = currency"
                        :class="[
                            'px-4 py-1.5 rounded-md text-sm font-bold transition-all duration-200',
                            displayCurrency === currency 
                                ? 'bg-white text-gold-600 shadow-sm' 
                                : 'text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        {{ currency }}
                    </button>
                </div>

                <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                    Add Watch
                </PrimaryButton>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / Model</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Details</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img v-if="product.image" :src="'/storage/' + product.image" class="h-12 w-12 rounded object-cover border border-gray-200" />
                            <div v-else class="h-12 w-12 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs">No Img</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-gray-900 font-medium">{{ product.name }}</div>
                            <div class="text-gray-500 text-xs">{{ product.model_number }}</div>
                            <div class="text-gray-400 text-[10px]">{{ product.barcode }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ product.brand?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ product.category?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-gray-900 font-bold text-lg">
                                {{ formatPrice(getDisplayPrice(product)) }} 
                                <span class="text-sm text-gray-500">{{ displayCurrency }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                Base: {{ parseInt(product.price).toLocaleString() }} {{ product.currency }}
                            </div>
                        </td>
                         <td class="px-6 py-4 whitespace-nowrap">
                             <Link v-if="product.id" :href="route('products.show', product.id)" class="text-blue-600 hover:text-blue-800 underline text-sm">
                                 Manage Stock
                             </Link>
                             <span v-else class="text-red-500 text-xs">Invalid ID</span>
                         </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(product)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteProduct(product)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!products?.data?.length">
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No watches found.</td>
                    </tr>
                </tbody>
            </table>
            
             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="products.prev_page_url" :href="products.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="products.next_page_url" :href="products.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ products.from }}</span>
                                 to
                                 <span class="font-medium">{{ products.to }}</span>
                                 of
                                 <span class="font-medium">{{ products.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in products.links" :key="k" 
                                     :href="link.url || '#'" 
                                     v-html="link.label"
                                     :class="{'z-10 bg-gold-50 border-gold-500 text-gold-600': link.active, 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50': !link.active, 'cursor-not-allowed': !link.url}"
                                     class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                 />
                             </nav>
                         </div>
                     </div>
                 </div>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="isModalOpen" @close="closeModal">
            <div class="p-6 bg-white text-gray-900 max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ editingProduct ? 'Edit Watch' : 'Add New Watch' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Brand" class="text-gray-700" />
                            <select v-model="form.brand_id" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm">
                                <option value="" disabled>Select Brand</option>
                                <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.brand_id" />
                        </div>
                        <div>
                            <InputLabel value="Category" class="text-gray-700" />
                            <select v-model="form.category_id" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm">
                                <option value="" disabled>Select Category</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.category_id" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Name" class="text-gray-700" />
                        <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.name" required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Model Number" class="text-gray-700" />
                            <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.model_number" />
                        </div>
                        <div>
                            <InputLabel value="Barcode (Leave empty to generate)" class="text-gray-700" />
                            <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.barcode" placeholder="Auto-generate" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Currency" class="text-gray-700" />
                            <select v-model="form.currency" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm">
                                <option value="MMK">MMK (Myanmar Kyat)</option>
                                <option value="USD">USD (US Dollar)</option>
                                <option value="THB">THB (Thai Baht)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel :value="'Price (' + form.currency + ')'" class="text-gray-700" />
                            <TextInput type="number" step="0.01" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.price" required />
                        </div>
                        <div>
                            <InputLabel :value="'Cost Price (' + form.currency + ')'" class="text-gray-700" />
                            <TextInput type="number" step="0.01" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.cost_price" />
                        </div>
                    </div>

                    <div>
                         <InputLabel value="Warranty Period (Months)" class="text-gray-700" />
                         <TextInput type="number" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.warranty_period" required />
                    </div>

                    <div>
                        <InputLabel value="Description" class="text-gray-700" />
                        <textarea v-model="form.description" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm" rows="3"></textarea>
                    </div>

                    <div>
                        <InputLabel value="Image" class="text-gray-700" />
                        <input type="file" @input="form.image = $event.target.files[0]" class="mt-1 block w-full text-gray-500" accept="image/*" ref="imageInput" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingProduct ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
