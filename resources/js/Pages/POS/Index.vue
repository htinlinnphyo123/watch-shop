<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Fuse from 'fuse.js';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    customers: {
        type: Array,
        default: () => [],
    },
});

const searchInput = ref(null);
const search = ref('');
const cart = ref([]);
const isCheckoutModalOpen = ref(false);
const isSerialModalOpen = ref(false);
const selectedProduct = ref(null);

const page = usePage();
const displayCurrency = ref('MMK'); // Default view

const getMmkPrice = (product) => {
    if (!product) return 0;
    let rate = 1;
    if (product.currency && product.currency !== 'MMK') {
        rate = parseFloat(page.props.settings[product.currency.toLowerCase() + '_rate'] || 1);
    }
    return parseFloat(product.price) * rate;
};

// Helper to calculate the displayed price in the selected displayCurrency
const getDisplayPrice = (product) => {
    const mmkPrice = getMmkPrice(product);

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

const checkoutForm = useForm({
    customer_id: '',
    payment_method: 'cash',
    amount_paid: 0,
    cart: [],
});

// Filter products based on search using Fuse.js
const filteredProducts = computed(() => {
    if (!props.products) return [];
    if (!search.value) return props.products;
    
    const fuse = new Fuse(props.products, {
        keys: ['name', 'model_number', 'barcode', 'brand.name', 'search_keywords'],
        threshold: 0.3, 
        includeScore: true
    });

    return fuse.search(search.value).map(result => result.item);
});

// Calculate Subtotal
const subTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + getMmkPrice(item.product), 0);
});

// Barcode Scanning Logic
const handleBarcodeScan = () => {
    if (!search.value) return;
    const scanValue = search.value.trim().toLowerCase();
    
    // 1. Check for exact Serial Number match
    for (const product of props.products) {
        if (product.items) {
            const matchedItem = product.items.find(i => i.serial_number.toLowerCase() === scanValue);
            if (matchedItem) {
                // Check if already in cart
                if (cart.value.find(c => c.serial_number === matchedItem.serial_number)) {
                    alert('Item is already in the cart!');
                    search.value = '';
                    return;
                }
                // Add exact item directly
                cart.value.push({
                    product: product,
                    serial_number: matchedItem.serial_number,
                    item_id: matchedItem.id
                });
                search.value = '';
                return;
            }
        }
    }

    // 2. Check for exact Product Barcode match
    const matchedProduct = props.products.find(p => p.barcode && p.barcode.toLowerCase() === scanValue);
    if (matchedProduct) {
        addToCart(matchedProduct); // This will open the modal to select the serial
        search.value = '';
        return;
    }
};

// Calculate Discount
const discount = computed(() => {
    if (!checkoutForm.customer_id) return 0;
    const customer = props.customers.find(c => c.id == checkoutForm.customer_id);
    if (customer && customer.group) {
        return subTotal.value * (parseFloat(customer.group.percentage) / 100);
    }
    return 0;
});

// Calculate Total
const total = computed(() => {
    return subTotal.value - discount.value;
});

const addToCart = (product) => {
    if (product.items && product.items.length > 0) {
        selectedProduct.value = product;
        isSerialModalOpen.value = true;
    } else {
        alert('No stock available for this product!');
    }
};

const selectSerial = (item) => {
    if (cart.value.find(c => c.serial_number === item.serial_number)) {
        alert('Item already in cart!');
        return;
    }

    cart.value.push({
        product: selectedProduct.value,
        serial_number: item.serial_number,
        item_id: item.id
    });
    
    closeSerialModal();
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const openCheckout = () => {
    if (cart.value.length === 0) return;
    checkoutForm.amount_paid = total.value;
    checkoutForm.cart = cart.value.map(c => ({
        product_id: c.product.id,
        serial_number: c.serial_number
    }));
    isCheckoutModalOpen.value = true;
};

const closeSerialModal = () => {
    isSerialModalOpen.value = false;
    selectedProduct.value = null;
};

const submitCheckout = () => {
    checkoutForm.post(route('pos.checkout'), {
        onSuccess: () => {
            cart.value = []; 
            isCheckoutModalOpen.value = false;
            checkoutForm.reset();
        }
    });
};
</script>

<template>
    <Head title="POS" />

    <AdminLayout>
        <div class="flex h-[calc(100vh-64px)] -m-6"> 
            <!-- Left: Product Grid -->
            <div class="w-full md:w-2/3 p-6 overflow-y-auto bg-gray-100 transition-colors">
                <div class="mb-6 flex gap-4">
                    <input 
                        ref="searchInput"
                        v-model="search" 
                        @keyup.enter="handleBarcodeScan"
                        type="text" 
                        placeholder="Search usage: Name, Model, or SCAN BARCODE..." 
                        class="flex-1 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-gold-500 focus:border-gold-500 p-4 shadow-sm"
                        autofocus
                    />

                    <!-- Currency Toggle -->
                    <div class="bg-gray-200 p-1 rounded-lg flex items-center shadow-inner self-stretch px-2 shrink-0">
                        <button 
                            v-for="currency in ['MMK', 'USD', 'THB']" :key="currency"
                            @click="displayCurrency = currency"
                            :class="[
                                'h-full px-5 rounded-md text-sm font-bold transition-all duration-200',
                                displayCurrency === currency 
                                    ? 'bg-white text-gold-600 shadow-sm' 
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            {{ currency }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div 
                        v-for="product in filteredProducts" 
                        :key="product.id" 
                        @click="addToCart(product)"
                        class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden cursor-pointer hover:border-gold-500 transition-all group"
                    >
                        <div class="h-32 bg-gray-200 relative">
                             <img v-if="product.image" :src="'/storage/' + product.image" class="w-full h-full object-cover" />
                             <div v-else class="w-full h-full flex items-center justify-center text-gray-500 text-xs">No Image</div>
                             
                             <div class="absolute top-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                 {{ product.items?.length || 0 }} Stock
                             </div>
                        </div>
                        <div class="p-4">
                            <h3 class="text-gray-900 font-bold truncate group-hover:text-gold-600">{{ product.name }}</h3>
                            <p class="text-gray-500 text-xs">{{ product.model_number }}</p>
                            <div class="mt-2 flex justify-between items-end">
                                <span class="text-gold-600 font-bold">
                                    {{ formatPrice(getDisplayPrice(product)) }} 
                                    <span class="text-xs text-gray-500 font-normal">{{ displayCurrency }}</span>
                                </span>
                                <span class="text-[10px] text-gray-500">{{ product.category?.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Cart -->
            <div class="w-full md:w-1/3 bg-white border-l border-gray-200 flex flex-col h-full transition-colors">
                <div class="p-4 border-b border-gray-200 bg-gray-50 space-y-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Current Order</h2>
                        <span class="text-gray-500 text-sm">{{ cart.length }} items</span>
                    </div>
                    
                    <select v-model="checkoutForm.customer_id" class="w-full bg-white border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:border-gold-500 focus:ring-gold-500">
                        <option value="">Walk-in Customer</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">
                            {{ c.name }} {{ c.group ? `(${c.group.name} - ${c.group.percentage}%)` : '' }}
                        </option>
                    </select>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white">
                    <div v-for="(item, index) in cart" :key="index" class="bg-gray-50 p-3 rounded border border-gray-200 flex justify-between shadow-sm">
                        <div>
                            <div class="text-gray-900 font-medium">{{ item.product.name }}</div>
                            <div class="text-xs text-gray-500">SN: <span class="font-mono text-gold-600">{{ item.serial_number }}</span></div>
                        </div>
                        <div class="text-right">
                            <div class="text-gold-600 font-bold">{{ getMmkPrice(item.product).toLocaleString() }} Ks</div>
                            <button @click="removeFromCart(index)" class="text-red-500 hover:text-red-700 text-xs hover:underline mt-1">Remove</button>
                        </div>
                    </div>
                    <div v-if="cart.length === 0" class="text-center text-gray-500 mt-10">Cart is empty</div>
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal</span>
                            <span>{{ subTotal.toLocaleString() }} Ks</span>
                        </div>
                        <div class="flex justify-between text-sm text-green-600" v-if="discount > 0">
                            <span>Discount</span>
                            <span>-{{ discount.toLocaleString() }} Ks</span>
                        </div>
                        


                        <div class="flex justify-between border-t border-gray-200 pt-2">
                            <span class="text-gray-900 font-bold text-lg">Total</span>
                            <span class="text-2xl font-bold text-gold-600">{{ total.toLocaleString() }} Ks</span>
                        </div>
                    </div>
                    <PrimaryButton @click="openCheckout" class="w-full justify-center py-3 bg-gold-500 hover:bg-gold-600 text-dark-900 font-bold text-lg shadow-md" :disabled="cart.length === 0">
                        Checkout
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <Modal :show="isSerialModalOpen" @close="closeSerialModal">
            <div class="p-6 bg-white text-gray-900">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Select Serial Number</h2>
                <div v-if="selectedProduct" class="space-y-2">
                    <p class="text-sm text-gray-600 mb-4">Which specific unit of <strong>{{ selectedProduct.name }}</strong> is being sold?</p>
                    <div class="grid grid-cols-1 gap-2 max-h-60 overflow-y-auto">
                        <button 
                            v-for="item in selectedProduct.items" 
                            :key="item.id"
                            @click="selectSerial(item)"
                            class="p-3 text-left border border-gray-200 rounded hover:bg-gray-50 hover:border-gold-500 flex justify-between transition-colors"
                        >
                            <span class="font-mono text-gold-600">{{ item.serial_number }}</span>
                            <span class="text-xs text-gray-500">Added: {{ new Date(item.created_at).toLocaleDateString() }}</span>
                        </button>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <SecondaryButton @click="closeSerialModal" class="bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200">Cancel</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="isCheckoutModalOpen" @close="isCheckoutModalOpen = false">
            <div class="p-6 bg-white text-gray-900">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Checkout</h2>
                
                <form @submit.prevent="submitCheckout" class="space-y-4">
                    <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded">
                        <span class="font-bold">Customer:</span> 
                        {{ customers.find(c => c.id === checkoutForm.customer_id)?.name || 'Walk-in Customer' }}
                        <span v-if="discount > 0" class="text-green-600 ml-2 font-bold">
                            (Discount Applied: -{{ discount.toLocaleString() }} Ks)
                        </span>
                    </div>

                    <div>
                         <InputLabel value="Payment Method" class="text-gray-700" />
                         <select v-model="checkoutForm.payment_method" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="transfer">Bank Transfer</option>
                         </select>
                    </div>

                    <div>
                        <InputLabel value="Amount Paid" class="text-gray-700" />
                        <TextInput type="number" step="0.01" v-model="checkoutForm.amount_paid" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" />
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex justify-between text-lg font-bold">
                            <span class="text-gray-900">Total Due</span>
                            <span class="text-gold-600">{{ total.toLocaleString() }} Ks</span>
                        </div>
                         <div class="flex justify-between text-sm mt-2">
                            <span class="text-gray-600">Change</span>
                            <span :class="{'text-red-500': checkoutForm.amount_paid < total, 'text-green-600': checkoutForm.amount_paid >= total}">
                                {{ (checkoutForm.amount_paid - total).toLocaleString() }} Ks
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="isCheckoutModalOpen = false" class="bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': checkoutForm.processing }" :disabled="checkoutForm.processing">
                            Complete Sale
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
