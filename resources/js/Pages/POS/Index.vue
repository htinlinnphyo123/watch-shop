<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Fuse from 'fuse.js';

const props = defineProps({
    products: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
});

const searchInput = ref(null);
const search = ref('');
const cart = ref([]);
const isCheckoutModalOpen = ref(false);

// ─── Add-to-Cart Modal state ─────────────────────────────────────────────────
const isAddModalOpen = ref(false);
const selectedProduct = ref(null);
// Mode: 'quantity' (default) or 'serial' (specific)
const addMode = ref('quantity');
const addQty = ref(1);
const serialSearch = ref('');
// ─────────────────────────────────────────────────────────────────────────────

const page = usePage();
const displayCurrency = ref('MMK');

// ─── Pricing helpers ──────────────────────────────────────────────────────────
const getMmkPrice = (product) => {
    if (!product) return 0;
    let rate = 1;
    if (product.currency && product.currency !== 'MMK') {
        rate = parseFloat(page.props.settings[product.currency.toLowerCase() + '_rate'] || 1);
    }
    return parseFloat(product.price) * rate;
};

const getDisplayPrice = (product) => {
    const mmkPrice = getMmkPrice(product);
    if (displayCurrency.value === 'MMK') return mmkPrice;
    const targetRate = parseFloat(page.props.settings[displayCurrency.value.toLowerCase() + '_rate'] || 1);
    return targetRate > 0 ? mmkPrice / targetRate : mmkPrice;
};

const formatPrice = (amount) => new Intl.NumberFormat('en-US', {
    minimumFractionDigits: displayCurrency.value === 'MMK' ? 0 : 2,
    maximumFractionDigits: displayCurrency.value === 'MMK' ? 0 : 2,
}).format(amount);
// ─────────────────────────────────────────────────────────────────────────────

const checkoutForm = useForm({
    customer_id: '',
    payment_method: 'cash',
    amount_paid: 0,
    cart: [],
});

// ─── Fuzzy search ─────────────────────────────────────────────────────────────
const filteredProducts = computed(() => {
    if (!props.products) return [];
    if (!search.value) return props.products;
    const fuse = new Fuse(props.products, {
        keys: ['name', 'model_number', 'barcode', 'brand.name'],
        threshold: 0.3,
        includeScore: true,
    });
    return fuse.search(search.value).map(r => r.item);
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Customer-group discount ──────────────────────────────────────────────────
const getActiveCustomerGroup = () => {
    if (!checkoutForm.customer_id) return null;
    const customer = props.customers.find(c => c.id == checkoutForm.customer_id);
    return customer ? customer.group : null;
};

const hasCustomDiscount = (product) => {
    const group = getActiveCustomerGroup();
    if (!group) return false;
    const override = product.customer_groups?.find(cg => cg.id === group.id);
    return override && override.pivot.percentage !== null && override.pivot.percentage !== '';
};

const getItemDiscountPercentage = (product) => {
    const group = getActiveCustomerGroup();
    if (!group) return 0;
    const override = product.customer_groups?.find(cg => cg.id === group.id);
    const percentage = (override && override.pivot.percentage !== null && override.pivot.percentage !== '')
        ? override.pivot.percentage : group.percentage;
    return parseFloat(percentage) || 0;
};
// ─────────────────────────────────────────────────────────────────────────────

// ─── Cart totals ──────────────────────────────────────────────────────────────
const subTotal = computed(() =>
    cart.value.reduce((sum, item) => sum + getMmkPrice(item.product) * item.qty, 0)
);

const discount = computed(() =>
    cart.value.reduce((total, item) => {
        const pct = getItemDiscountPercentage(item.product);
        return pct > 0
            ? total + getMmkPrice(item.product) * item.qty * (pct / 100)
            : total;
    }, 0)
);

const total = computed(() => subTotal.value - discount.value);
// ─────────────────────────────────────────────────────────────────────────────

// ─── How many of this product are already in the cart ─────────────────────────
const cartQtyForProduct = (productId) =>
    cart.value.filter(c => c.product.id === productId).reduce((s, c) => s + c.qty, 0);

// Available items = product items minus those already pinned (specific serial) in cart
const availableItems = computed(() => {
    if (!selectedProduct.value) return [];
    const pinnedIds = cart.value
        .filter(c => c.product.id === selectedProduct.value.id && c.item_id)
        .map(c => c.item_id);
    return (selectedProduct.value.items || []).filter(i => !pinnedIds.includes(i.id));
});

const maxQty = computed(() => availableItems.value.length);

const filteredAvailableItems = computed(() => {
    if (!serialSearch.value) return availableItems.value;
    return availableItems.value.filter(i =>
        (i.serial_number || i.system_unique_id || '')
            .toLowerCase()
            .includes(serialSearch.value.toLowerCase())
    );
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Open add modal ───────────────────────────────────────────────────────────
const addToCart = (product) => {
    if (!product.items || product.items.length === 0) {
        alert('No stock available for this product!');
        return;
    }
    selectedProduct.value = product;
    addMode.value = 'quantity';
    addQty.value = 1;
    serialSearch.value = '';
    isAddModalOpen.value = true;
};

const closeAddModal = () => {
    isAddModalOpen.value = false;
    selectedProduct.value = null;
};

// Reset qty when switching modes
watch(addMode, () => { addQty.value = 1; });
// ─────────────────────────────────────────────────────────────────────────────

// ─── Confirm add: generic quantity ────────────────────────────────────────────
const confirmAddQty = () => {
    const qty = parseInt(addQty.value) || 1;
    if (qty < 1 || qty > maxQty.value) return;

    // Merge into existing generic cart line for this product (if any)
    const existing = cart.value.find(c => c.product.id === selectedProduct.value.id && !c.item_id);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.value.push({
            product: selectedProduct.value,
            item_id: null,        // generic — backend auto-picks
            serial_number: null,
            qty,
        });
    }
    closeAddModal();
};

// ─── Confirm add: specific serial ─────────────────────────────────────────────
const confirmAddSerial = (item) => {
    if (cart.value.find(c => c.item_id === item.id)) {
        alert('This unit is already in the cart!');
        return;
    }
    cart.value.push({
        product: selectedProduct.value,
        item_id: item.id,
        serial_number: item.serial_number || item.system_unique_id,
        qty: 1,
    });
    closeAddModal();
};
// ─────────────────────────────────────────────────────────────────────────────

// ─── Barcode scan ─────────────────────────────────────────────────────────────
const handleBarcodeScan = () => {
    if (!search.value) return;
    const scanValue = search.value.trim().toLowerCase();

    for (const product of props.products) {
        if (product.items) {
            const matchedItem = product.items.find(
                i => i.serial_number && i.serial_number.toLowerCase() === scanValue
            );
            if (matchedItem) {
                if (cart.value.find(c => c.item_id === matchedItem.id)) {
                    alert('Item is already in the cart!');
                    search.value = '';
                    return;
                }
                cart.value.push({
                    product,
                    item_id: matchedItem.id,
                    serial_number: matchedItem.serial_number,
                    qty: 1,
                });
                search.value = '';
                return;
            }
        }
    }

    const matchedProduct = props.products.find(
        p => p.barcode && p.barcode.toLowerCase() === scanValue
    );
    if (matchedProduct) {
        addToCart(matchedProduct);
        search.value = '';
    }
};
// ─────────────────────────────────────────────────────────────────────────────

const removeFromCart = (index) => { cart.value.splice(index, 1); };

const openCheckout = () => {
    if (cart.value.length === 0) return;
    checkoutForm.amount_paid = total.value;
    checkoutForm.cart = cart.value.map(c => ({
        product_id: c.product.id,
        item_id: c.item_id || null,
        quantity: c.qty,
    }));
    isCheckoutModalOpen.value = true;
};

const submitCheckout = () => {
    checkoutForm.post(route('pos.checkout'), {
        onSuccess: () => {
            cart.value = [];
            isCheckoutModalOpen.value = false;
            checkoutForm.reset();
        },
    });
};
</script>

<template>
    <Head title="POS" />

    <AdminLayout>
        <div class="flex h-[calc(100vh-64px)] -m-6">

            <!-- ── Left: Product Grid ─────────────────────────────────────── -->
            <div class="w-full md:w-2/3 p-6 overflow-y-auto bg-gray-100">
                <!-- Search + currency toggle -->
                <div class="mb-6 flex gap-3">
                    <input
                        ref="searchInput"
                        v-model="search"
                        @keyup.enter="handleBarcodeScan"
                        type="text"
                        placeholder="Search by name, model, or scan barcode…"
                        class="flex-1 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-gold-500 focus:border-gold-500 p-4 shadow-sm"
                        autofocus
                    />
                    <div class="bg-gray-200 p-1 rounded-lg flex items-center shadow-inner self-stretch px-2 shrink-0">
                        <button
                            v-for="currency in ['MMK', 'USD', 'THB']"
                            :key="currency"
                            @click="displayCurrency = currency"
                            :class="[
                                'h-full px-5 rounded-md text-sm font-bold transition-all duration-200',
                                displayCurrency === currency
                                    ? 'bg-white text-gold-600 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700',
                            ]"
                        >{{ currency }}</button>
                    </div>
                </div>

                <!-- Product cards -->
                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="addToCart(product)"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden cursor-pointer hover:border-gold-500 hover:shadow-md transition-all group"
                    >
                        <div class="h-32 bg-gray-100 relative">
                            <img
                                v-if="product.images && product.images[0]"
                                :src="$page.props.storage_url + '/' + product.images[0]"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                            <div class="absolute top-2 right-2 bg-black/60 text-white text-[11px] font-semibold px-2 py-0.5 rounded-full">
                                {{ product.items?.length || 0 }} in stock
                            </div>
                        </div>
                        <div class="p-3">
                            <h3 class="text-gray-900 font-bold text-sm truncate group-hover:text-gold-600">{{ product.name }}</h3>
                            <p class="text-gray-400 text-xs truncate">{{ product.model_number }}</p>
                            <div class="mt-2 flex justify-between items-end">
                                <span class="text-gold-600 font-bold text-sm">
                                    {{ formatPrice(getDisplayPrice(product)) }}
                                    <span class="text-[10px] text-gray-400 font-normal">{{ displayCurrency }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Right: Cart ───────────────────────────────────────────── -->
            <div class="w-full md:w-1/3 bg-white border-l border-gray-200 flex flex-col h-full">
                <!-- Header -->
                <div class="p-4 border-b border-gray-200 bg-gray-50 space-y-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Current Order</h2>
                        <span class="text-gray-400 text-sm">{{ cart.length }} line(s)</span>
                    </div>
                    <select
                        v-model="checkoutForm.customer_id"
                        class="w-full bg-white border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:border-gold-500 focus:ring-gold-500"
                    >
                        <option value="">Walk-in Customer</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">
                            {{ c.name }}{{ c.group ? ` (${c.group.name} – ${c.group.percentage}%)` : '' }}
                        </option>
                    </select>
                </div>

                <!-- Cart items -->
                <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-white">
                    <TransitionGroup name="cart-item" tag="div" class="space-y-2">
                        <div
                            v-for="(item, index) in cart"
                            :key="index"
                            class="bg-gray-50 border border-gray-200 rounded-lg p-3 shadow-sm flex justify-between items-start gap-2"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="text-gray-900 font-semibold text-sm truncate">{{ item.product.name }}</div>
                                <!-- Specific unit badge -->
                                <div v-if="item.serial_number" class="mt-0.5 inline-flex items-center gap-1 bg-gold-50 border border-gold-200 text-gold-700 text-[10px] font-mono px-1.5 py-0.5 rounded">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                                    {{ item.serial_number }}
                                </div>
                                <!-- Generic qty badge -->
                                <div v-else class="mt-0.5 inline-flex items-center gap-1 bg-blue-50 border border-blue-200 text-blue-700 text-[10px] px-1.5 py-0.5 rounded">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                                    Qty: {{ item.qty }} unit{{ item.qty > 1 ? 's' : '' }}
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <template v-if="getItemDiscountPercentage(item.product) > 0">
                                    <div class="text-gray-400 line-through text-[10px]">{{ (getMmkPrice(item.product) * item.qty).toLocaleString() }} Ks</div>
                                    <div class="text-gold-600 font-bold text-sm leading-tight">
                                        {{ (getMmkPrice(item.product) * item.qty * (1 - getItemDiscountPercentage(item.product) / 100)).toLocaleString() }} Ks
                                    </div>
                                    <div class="text-[10px] text-green-600 bg-green-50 border border-green-100 px-1.5 py-0.5 rounded mt-0.5">
                                        -{{ getItemDiscountPercentage(item.product) }}%
                                        <span v-if="hasCustomDiscount(item.product)">(Custom)</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-gold-600 font-bold text-sm">{{ (getMmkPrice(item.product) * item.qty).toLocaleString() }} Ks</div>
                                </template>
                                <button @click="removeFromCart(index)" class="text-red-400 hover:text-red-600 text-[10px] hover:underline mt-1.5 block">Remove</button>
                            </div>
                        </div>
                    </TransitionGroup>
                    <div v-if="cart.length === 0" class="text-center text-gray-400 mt-16 select-none">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Cart is empty
                    </div>
                </div>

                <!-- Totals + checkout -->
                <div class="p-4 bg-gray-50 border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.06)]">
                    <div class="space-y-1.5 mb-4">
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal</span>
                            <span>{{ subTotal.toLocaleString() }} Ks</span>
                        </div>
                        <div v-if="discount > 0" class="flex justify-between text-sm text-green-600">
                            <span>Discount</span>
                            <span>-{{ discount.toLocaleString() }} Ks</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2">
                            <span class="text-gray-900 font-bold text-lg">Total</span>
                            <span class="text-2xl font-bold text-gold-600">{{ total.toLocaleString() }} Ks</span>
                        </div>
                    </div>
                    <PrimaryButton
                        @click="openCheckout"
                        class="w-full justify-center py-3 bg-gold-500 hover:bg-gold-600 text-dark-900 font-bold text-base shadow-md"
                        :disabled="cart.length === 0"
                    >
                        Checkout
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
             Add-to-Cart Modal
        ══════════════════════════════════════════════════════════════════ -->
        <Modal :show="isAddModalOpen" @close="closeAddModal" max-width="lg">
            <div class="bg-white rounded-xl overflow-hidden" v-if="selectedProduct">

                <!-- Modal header -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-100">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden shrink-0">
                            <img
                                v-if="selectedProduct.images && selectedProduct.images[0]"
                                :src="$page.props.storage_url + '/' + selectedProduct.images[0]"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-gray-900 truncate">{{ selectedProduct.name }}</h2>
                            <p class="text-sm text-gray-400">{{ selectedProduct.model_number }}</p>
                        </div>
                        <div class="ml-auto shrink-0 text-right">
                            <div class="text-gold-600 font-bold">{{ formatPrice(getDisplayPrice(selectedProduct)) }} <span class="text-xs font-normal text-gray-400">{{ displayCurrency }}</span></div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ maxQty }} available</div>
                        </div>
                    </div>
                </div>

                <!-- Mode tabs -->
                <div class="px-6 pt-5">
                    <div class="flex gap-2 p-1 bg-gray-100 rounded-lg">
                        <button
                            @click="addMode = 'quantity'"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 py-2.5 rounded-md text-sm font-semibold transition-all duration-200',
                                addMode === 'quantity'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            By Quantity
                        </button>
                        <button
                            @click="addMode = 'serial'"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 py-2.5 rounded-md text-sm font-semibold transition-all duration-200',
                                addMode === 'serial'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            By Serial / Unit
                        </button>
                    </div>
                </div>

                <!-- ── Mode: Quantity ── -->
                <div v-if="addMode === 'quantity'" class="px-6 pt-5 pb-6 space-y-5">
                    <p class="text-sm text-gray-500">
                        Choose how many units to add. The system will automatically assign available stock.
                    </p>

                    <!-- Qty stepper -->
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="addQty = Math.max(1, addQty - 1)"
                            class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:border-gold-500 hover:text-gold-600 transition-colors font-bold text-lg"
                        >−</button>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-gray-900 w-16 text-center">{{ addQty }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">unit{{ addQty > 1 ? 's' : '' }}</div>
                        </div>
                        <button
                            @click="addQty = Math.min(maxQty, addQty + 1)"
                            class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:border-gold-500 hover:text-gold-600 transition-colors font-bold text-lg"
                        >+</button>
                    </div>

                    <!-- Quick presets -->
                    <div class="flex gap-2 justify-center flex-wrap">
                        <button
                            v-for="n in [1, 2, 3, 5].filter(n => n <= maxQty)"
                            :key="n"
                            @click="addQty = n"
                            :class="[
                                'px-3 py-1 rounded-full text-sm font-medium border transition-colors',
                                addQty === n
                                    ? 'bg-gold-50 border-gold-400 text-gold-700'
                                    : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gold-300',
                            ]"
                        >{{ n }}</button>
                        <button
                            v-if="maxQty > 5"
                            @click="addQty = maxQty"
                            :class="[
                                'px-3 py-1 rounded-full text-sm font-medium border transition-colors',
                                addQty === maxQty
                                    ? 'bg-gold-50 border-gold-400 text-gold-700'
                                    : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gold-300',
                            ]"
                        >All ({{ maxQty }})</button>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <SecondaryButton @click="closeAddModal" class="flex-1 justify-center">Cancel</SecondaryButton>
                        <PrimaryButton
                            @click="confirmAddQty"
                            :disabled="addQty < 1 || addQty > maxQty"
                            class="flex-1 justify-center bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold"
                        >
                            Add {{ addQty }} to Cart
                        </PrimaryButton>
                    </div>
                </div>

                <!-- ── Mode: Serial / Unit ── -->
                <div v-if="addMode === 'serial'" class="px-6 pt-5 pb-6 space-y-4">
                    <p class="text-sm text-gray-500">
                        Select a <strong>specific unit</strong> — useful when the customer requests a particular serial number.
                    </p>

                    <!-- Search within available items -->
                    <input
                        v-model="serialSearch"
                        type="text"
                        placeholder="Filter by serial or system ID…"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gold-400 focus:border-transparent"
                    />

                    <div class="max-h-64 overflow-y-auto space-y-1.5 pr-1">
                        <!-- No items message -->
                        <div v-if="filteredAvailableItems.length === 0" class="text-center py-8 text-gray-400 text-sm">
                            No available units found.
                        </div>

                        <button
                            v-for="item in filteredAvailableItems"
                            :key="item.id"
                            @click="confirmAddSerial(item)"
                            class="w-full text-left p-3 rounded-lg border border-gray-200 hover:border-gold-400 hover:bg-gold-50 transition-all group flex items-center justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <!-- Icon: has serial vs no serial -->
                                <div :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-xs shrink-0',
                                    item.serial_number
                                        ? 'bg-gold-100 text-gold-700'
                                        : 'bg-gray-100 text-gray-400',
                                ]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                                </div>
                                <div>
                                    <div v-if="item.serial_number" class="font-mono text-sm font-semibold text-gray-800 group-hover:text-gold-700">
                                        {{ item.serial_number }}
                                    </div>
                                    <div v-else class="text-sm text-gray-400 italic">No serial number</div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">
                                        ID: {{ item.system_unique_id }}
                                        <span v-if="item.purchase_date"> · Purchased: {{ new Date(item.purchase_date).toLocaleDateString() }}</span>
                                        <span v-else> · Added: {{ new Date(item.created_at).toLocaleDateString() }}</span>
                                    </div>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-gold-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>

                    <SecondaryButton @click="closeAddModal" class="w-full justify-center">Cancel</SecondaryButton>
                </div>

            </div>
        </Modal>

        <!-- ══════════════════════════════════════════════════════════════════
             Checkout Modal
        ══════════════════════════════════════════════════════════════════ -->
        <Modal :show="isCheckoutModalOpen" @close="isCheckoutModalOpen = false">
            <div class="p-6 bg-white text-gray-900">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Checkout</h2>

                <form @submit.prevent="submitCheckout" class="space-y-4">
                    <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                        <span class="font-bold">Customer:</span>
                        {{ customers.find(c => c.id === checkoutForm.customer_id)?.name || 'Walk-in Customer' }}
                        <span v-if="discount > 0" class="text-green-600 ml-2 font-bold">
                            (Discount: -{{ discount.toLocaleString() }} Ks)
                        </span>
                    </div>

                    <!-- Cart summary -->
                    <div class="bg-gray-50 rounded-lg divide-y divide-gray-100 text-sm max-h-40 overflow-y-auto">
                        <div v-for="(item, i) in cart" :key="i" class="flex justify-between px-3 py-2">
                            <div>
                                <span class="font-medium">{{ item.product.name }}</span>
                                <span v-if="item.serial_number" class="ml-1 text-gold-600 font-mono text-xs">#{{ item.serial_number }}</span>
                                <span v-else class="ml-1 text-blue-500 text-xs">×{{ item.qty }}</span>
                            </div>
                            <span class="text-gray-700">{{ (getMmkPrice(item.product) * item.qty).toLocaleString() }} Ks</span>
                        </div>
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

                    <div class="pt-3 border-t border-gray-200 space-y-1">
                        <div class="flex justify-between text-lg font-bold">
                            <span class="text-gray-900">Total Due</span>
                            <span class="text-gold-600">{{ total.toLocaleString() }} Ks</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Change</span>
                            <span :class="checkoutForm.amount_paid < total ? 'text-red-500' : 'text-green-600'">
                                {{ (checkoutForm.amount_paid - total).toLocaleString() }} Ks
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="isCheckoutModalOpen = false">Cancel</SecondaryButton>
                        <PrimaryButton
                            class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold"
                            :class="{ 'opacity-25': checkoutForm.processing }"
                            :disabled="checkoutForm.processing"
                        >Complete Sale</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>

<style scoped>
.cart-item-enter-active,
.cart-item-leave-active {
    transition: all 0.2s ease;
}
.cart-item-enter-from {
    opacity: 0;
    transform: translateX(-10px);
}
.cart-item-leave-to {
    opacity: 0;
    transform: translateX(10px);
}
</style>
