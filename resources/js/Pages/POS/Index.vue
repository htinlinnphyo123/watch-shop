<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SalesRecordFields from '@/Components/SalesRecordFields.vue';
import PosGuestCustomer from '@/Components/PosGuestCustomer.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { customerLabel, customerOptions } from '@/utils/customerPicker';
import { salesDefaults, recordValues } from '@/utils/salesRecordFields';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import Modal from '@/Components/Modal.vue';
import CameraBarcodeScanner from '@/Components/CameraBarcodeScanner.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { paymentMethods, paymentCents } from '@/utils/payments';
import { posPriceMmk, posDiscountPercentage, orderDiscountAmount, posOrderTotal } from '@/utils/posPricing';
import { createCheckoutRequestId } from '@/utils/checkoutRequest';

const props = defineProps({
    salespeople: { type: Array, default: () => [] },
    editingOrder: { type: Object, default: null },
    products: { type: Array, default: () => [] },
    productsPagination: { type: Object, default: () => ({ current_page: 1, last_page: 1 }) },
    customers: { type: Array, default: () => [] },
    customerRegions: { type: Array, default: () => [] },
    customerSources: { type: Object, default: () => ({}) },
});

const searchInput = ref(null);
const posCustomers = ref([...props.customers]);
watch(() => props.customers, customers => { posCustomers.value = [...customers]; });
const customerPickerOptions = computed(() => customerOptions(posCustomers.value));
const selectedCustomer = computed(() => posCustomers.value.find(customer => String(customer.id) === String(checkoutForm.customer_id)));
const promotingCustomer = ref(false);
const customerMessage = ref('');
const selectCustomer = customer => {
    const index = posCustomers.value.findIndex(existing => existing.id === customer.id);
    if (index === -1) posCustomers.value.push(customer);
    else posCustomers.value[index] = customer;
    checkoutForm.customer_id = customer.id;
    customerMessage.value = '';
};
const promoteCustomer = async () => {
    if (!selectedCustomer.value?.is_guest || promotingCustomer.value) return;
    promotingCustomer.value = true; customerMessage.value = '';
    try {
        const response = await axios.patch(route('pos.customers.promote', selectedCustomer.value.id));
        selectCustomer(response.data.customer);
        customerMessage.value = 'Customer is now regular. Their previous orders remain linked.';
    } catch { customerMessage.value = 'Could not promote this customer. Please try again.'; }
    finally { promotingCustomer.value = false; }
};
const isCameraOpen = ref(false);
const orderPanel = ref(null);
const compactPane = ref('products');
const showCompactPane = pane => {
    compactPane.value = pane;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const search = ref('');
const productKind = ref('');
const scanMessage = ref('');
const scanFailed = ref(false);
const pendingScans = ref(0);
let scanQueue = Promise.resolve();
const productResults = ref([...props.products]);
const productPage = ref(props.productsPagination.current_page || 1);
const lastProductPage = ref(props.productsPagination.last_page || 1);
const productsLoading = ref(false);
const productLoadError = ref('');
let searchTimer = null;
let productRequestId = 0;
const cart = ref(props.editingOrder?.cart.map(line => ({ ...line, product: { ...line.product } })) || []);
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
const getMmkPrice = (product) => posPriceMmk(product, page.props.settings);

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
    salesperson_ids: props.editingOrder ? [...props.editingOrder.salesperson_ids] : [page.props.auth.user.id],
    checkout_request_id: createCheckoutRequestId(),
    ...recordValues(props.editingOrder, salesDefaults),
    status: props.editingOrder?.status || 'completed',
    delivery_code: props.editingOrder?.delivery_code || '',
    remark: props.editingOrder?.remark || '',
    money_transfer_amount: props.editingOrder?.money_transfer_amount ?? '',
    customer_id: props.editingOrder?.customer_id || '',
    discount_percentage: props.editingOrder?.discount_percentage || 0,
    payments: props.editingOrder?.payments.map(payment => ({ ...payment })) || [{ method: 'cash', amount: 0 }],
    edit_version: props.editingOrder?.edit_version ?? null,
    cart: [],
});

// ─── Paginated server-side product search ────────────────────────────────────
const filteredProducts = computed(() => productResults.value);

const loadProducts = async (append = false) => {
    const requestId = ++productRequestId;
    const page = append ? productPage.value + 1 : 1;
    productsLoading.value = true;
    productLoadError.value = '';

    try {
        const response = await axios.get(route('pos.products'), {
            params: { q: search.value.trim() || undefined, kind: productKind.value || undefined, page, order_id: props.editingOrder?.id },
        });
        if (requestId !== productRequestId) return;

        productResults.value = append
            ? [...productResults.value, ...response.data.data]
            : response.data.data;
        productPage.value = response.data.current_page;
        lastProductPage.value = response.data.last_page;
    } catch (error) {
        if (requestId === productRequestId) {
            productLoadError.value = 'Could not load products. Please try again.';
        }
    } finally {
        if (requestId === productRequestId) productsLoading.value = false;
    }
};

watch([search, productKind], () => {
    clearTimeout(searchTimer);
    // Invalidate old results immediately, including during the debounce delay.
    productRequestId++;
    productResults.value = [];
    productPage.value = 1;
    lastProductPage.value = 1;
    productsLoading.value = true;
    searchTimer = setTimeout(() => loadProducts(false), 300);
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Order discount ───────────────────────────────────────────────────────────
const discountManuallyAdjusted = ref(!!props.editingOrder);

const getActiveCustomerGroup = () => {
    if (!checkoutForm.customer_id) return null;
    const customer = posCustomers.value.find(c => c.id == checkoutForm.customer_id);
    return customer ? customer.group : null;
};

const defaultDiscountPercentage = computed(() => {
    const group = getActiveCustomerGroup();
    if (subTotal.value <= 0) return 0;
    const watchDiscount = cart.value.reduce((sum, item) => {
        const percentage = posDiscountPercentage(item.product, group);
        return sum + (getMmkPrice(item.product) * item.qty * percentage / 100);
    }, 0);

    return Number(((watchDiscount / subTotal.value) * 100).toFixed(2));
});

const defaultDiscountSource = computed(() => {
    const group = getActiveCustomerGroup();
    return group ? `${group.name}: watch overrides, then group default` : 'product record';
});

const applyDefaultDiscount = () => {
    checkoutForm.discount_percentage = defaultDiscountPercentage.value;
};
// ─────────────────────────────────────────────────────────────────────────────

// ─── Cart totals ──────────────────────────────────────────────────────────────
const subTotal = computed(() =>
    cart.value.reduce((sum, item) => sum + getMmkPrice(item.product) * item.qty, 0)
);

const appliedDiscountPercentage = computed(() => {
    const percentage = parseFloat(checkoutForm.discount_percentage) || 0;
    return Math.round(Math.max(0, Math.min(100, percentage)) * 100) / 100;
});

const discount = computed(() => orderDiscountAmount(subTotal.value, appliedDiscountPercentage.value));

const merchandiseTotal = computed(() => posOrderTotal(subTotal.value, appliedDiscountPercentage.value));
const deliveryFee = computed(() => Math.max(0, Number(checkoutForm.delivery_fees) || 0));
const total = computed(() => merchandiseTotal.value + deliveryFee.value);
const roundingAdjustment = computed(() => (paymentCents(merchandiseTotal.value) - paymentCents(subTotal.value - discount.value)) / 100);
const amountPaid = computed(() => checkoutForm.payments.reduce((sum, payment) => sum + paymentCents(payment.amount), 0) / 100);
const amountShort = computed(() => Math.max(0, paymentCents(total.value) - paymentCents(amountPaid.value)) / 100);
const changeDue = computed(() => Math.max(0, paymentCents(amountPaid.value) - paymentCents(total.value)) / 100);
const nonCashOverpaid = computed(() => checkoutForm.payments
    .filter(payment => payment.method !== 'cash')
    .reduce((sum, payment) => sum + paymentCents(payment.amount), 0) > paymentCents(total.value));
const invalidPayment = computed(() => checkoutForm.payments.some(payment =>
    payment.amount === '' || !Number.isFinite(Number(payment.amount)) ||
    Number(payment.amount) < 0 ||
    (paymentCents(payment.amount) === 0 && ((total.value > 0 && checkoutForm.status !== 'pending') || checkoutForm.payments.length > 1))
));
const checkoutPaymentMethods = computed(() => paymentMethods.some(method => method.value === 'transfer') ? paymentMethods : [...paymentMethods, { value: 'transfer', label: 'Bank Transfer' }]);
const addPayment = () => {
    const method = paymentMethods.find(option => !checkoutForm.payments.some(payment => payment.method === option.value));
    if (method) checkoutForm.payments.push({ method: method.value, amount: amountShort.value });
    checkoutForm.clearErrors();
};
const removePayment = index => {
    checkoutForm.payments.splice(index, 1);
    checkoutForm.clearErrors();
};

watch(() => checkoutForm.customer_id, () => {
    discountManuallyAdjusted.value = false;
    applyDefaultDiscount();
});

watch(cart, () => {
    if (!discountManuallyAdjusted.value) applyDefaultDiscount();
}, { deep: true });

watch(() => checkoutForm.delivery_fees, (newFee, oldFee) => {
    if (props.editingOrder || checkoutForm.payments.length !== 1 || checkoutForm.payments[0].method !== 'cash') return;
    const oldTotal = merchandiseTotal.value + (Number(oldFee) || 0);
    if (paymentCents(checkoutForm.payments[0].amount) === paymentCents(oldTotal)) {
        checkoutForm.payments[0].amount = total.value;
    }
});
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

const genericCartQty = computed(() => {
    if (!selectedProduct.value) return 0;
    return cart.value
        .filter(c => c.product.id === selectedProduct.value.id && !c.item_id)
        .reduce((sum, item) => sum + item.qty, 0);
});

const maxQty = computed(() => Math.max(0, availableItems.value.length - genericCartQty.value));

const filteredAvailableItems = computed(() => {
    if (!serialSearch.value) return availableItems.value;
    return availableItems.value.filter(i =>
        [i.serial_number, i.system_unique_id].some(code =>
            (code || '').toLowerCase().includes(serialSearch.value.trim().toLowerCase()))
    );
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Open add modal ───────────────────────────────────────────────────────────
const addToCart = async (product) => {
    try {
        const response = await axios.get(route('pos.products.available-items', product.id), { params: { order_id: props.editingOrder?.id } });
        product.items = response.data.items;
        product.available_items_count = response.data.items.length;
    } catch (error) {
        scanFailed.value = true;
        scanMessage.value = error.response?.data?.message || 'Could not load the available units.';
        return;
    }

    if (product.available_items_count <= cartQtyForProduct(product.id)) {
        scanFailed.value = true;
        scanMessage.value = `No additional stock is available for ${product.name}.`;
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
    const existing = cart.value.find(c => c.product.id === selectedProduct.value.id && !c.item_id && !c.original_line_id);
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
        product: item.pos_price_mmk !== undefined ? { ...selectedProduct.value, pos_price_mmk: item.pos_price_mmk } : selectedProduct.value,
        original_line_id: item.original_line_id || null,
        item_id: item.id,
        serial_number: item.serial_number || item.system_unique_id,
        system_unique_id: item.system_unique_id,
        qty: 1,
    });
    closeAddModal();
};
// ─────────────────────────────────────────────────────────────────────────────

// ─── Barcode scan ─────────────────────────────────────────────────────────────
const processBarcodeScan = async (scanValue, focusSearch = true) => {
    scanMessage.value = '';
    scanFailed.value = false;
    try {
        const response = await axios.get(route('pos.products.scan'), { params: { code: scanValue, order_id: props.editingOrder?.id } });
        const { product, item, original_line_id } = response.data;
        if (item) {
            if (cart.value.find(c => c.item_id === item.id)) {
                scanFailed.value = true;
                scanMessage.value = 'This product is already in the current order.';
            } else {
                cart.value.push({
                    product,
                    original_line_id,
                    item_id: item.id,
                    serial_number: item.serial_number || item.system_unique_id,
                    system_unique_id: item.system_unique_id,
                    qty: 1,
                });
                scanMessage.value = `Added ${product.name} — ${item.system_unique_id || scanValue}`;
            }
        } else {
            await addToCart(product);
        }
    } catch (error) {
        scanFailed.value = true;
        scanMessage.value = error.response?.status === 404
            ? 'Code not found, or this product is no longer available.'
            : 'Could not scan this product. Please try again.';
    } finally {
        pendingScans.value--;
        await nextTick();
        if (focusSearch && !isCameraOpen.value && !isAddModalOpen.value && !isCheckoutModalOpen.value) searchInput.value?.focus();
        if (!focusSearch && !scanFailed.value && !isAddModalOpen.value && window.innerWidth < 1024) {
            showCompactPane('order');
        }
    }
};

const handleBarcodeScan = () => {
    const scanValue = search.value.trim();
    if (!scanValue || isCheckoutModalOpen.value) return;
    search.value = '';
    pendingScans.value++;
    scanQueue = scanQueue.then(() => processBarcodeScan(scanValue));
};
const matchedUnitInCart = product => cart.value.some(line => line.item_id === product.matched_item?.id);
const selectSearchProduct = product => {
    if (!product.matched_item) return addToCart(product);
    if (pendingScans.value || matchedUnitInCart(product)) return;
    // Recheck availability through the same exact-unit flow used by scanners.
    const code = product.matched_item.system_unique_id || product.matched_item.serial_number;
    pendingScans.value++;
    scanQueue = scanQueue.then(() => processBarcodeScan(code));
};
const handleCameraScan = (code) => {
    isCameraOpen.value = false;
    if (!code || isCheckoutModalOpen.value) return;
    pendingScans.value++;
    scanQueue = scanQueue.then(() => processBarcodeScan(code, false));
};
// ─────────────────────────────────────────────────────────────────────────────

const removeFromCart = (index) => { cart.value.splice(index, 1); };

const openCheckout = () => {
    if (cart.value.length === 0 || pendingScans.value > 0) return;
    if (!props.editingOrder) checkoutForm.payments = [{ method: 'cash', amount: total.value }];
    checkoutForm.clearErrors();
    checkoutForm.cart = cart.value.map(c => ({
        original_line_id: c.original_line_id || null,
        product_id: c.product.id,
        item_id: c.item_id || null,
        quantity: c.qty,
    }));
    isCheckoutModalOpen.value = true;
};

const submitCheckout = () => {
    if ((amountShort.value > 0 && checkoutForm.status !== 'pending') || nonCashOverpaid.value || invalidPayment.value || checkoutForm.processing) return;
    checkoutForm.discount_percentage = appliedDiscountPercentage.value;
    checkoutForm.submit(props.editingOrder ? 'put' : 'post', props.editingOrder ? route('pos.orders.update', props.editingOrder.id) : route('pos.checkout'), {
        onSuccess: () => {
            cart.value = [];
            isCheckoutModalOpen.value = false;
            checkoutForm.reset();
            discountManuallyAdjusted.value = false;
        },
        onError: (errors) => {
            if (Object.keys(errors).some(key => key.startsWith('cart'))) loadProducts(false);
        },
    });
};

const warnBeforeUnload = event => {
    if (!cart.value.length) return;
    event.preventDefault();
    event.returnValue = '';
};
let removeNavigationGuard;
onMounted(() => {
    window.addEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard = router.on('before', event => {
        if (event.detail.visit.method === 'get' && cart.value.length && !checkoutForm.processing
            && !window.confirm('Leave POS and discard the current cart changes?')) event.preventDefault();
    });
});
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    productRequestId++;
    window.removeEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard?.();
});
</script>

<template>
    <Head :title="editingOrder ? `Edit Order ${editingOrder.order_number}` : 'POS'" />

    <AdminLayout hide-sidebar>
        <header class="pos-header -mx-6 -mt-6 mb-6 flex min-h-16 items-center justify-between gap-4 border-b border-gray-200 bg-white px-6">
            <h1 class="text-lg font-bold text-gray-900">POS System</h1>
            <div class="pos-header-links flex items-center gap-4">
                <Link :href="route('pre-orders.index')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Pre Orders</Link>
                <Link :href="route(['staff', 'manager'].includes($page.props.auth.user.role) ? 'orders.index' : 'dashboard')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                    {{ $page.props.auth.user.role === 'staff' ? 'My Orders' : ($page.props.auth.user.role === 'manager' ? 'All Orders' : 'Back to Dashboard') }}
                </Link>
            </div>
        </header>
        <div v-if="editingOrder" class="mb-8 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gold-200 bg-gold-50 p-4">
            <div>
                <h1 class="font-bold text-gray-900">Editing Order {{ editingOrder.order_number }}</h1>
                <p class="mt-1 text-sm text-gray-600">Existing products keep their saved prices. Review payments before saving. {{ editingOrder.status === 'pending' ? 'This order will remain pending approval.' : '' }}</p>
            </div>
            <Link :href="route('orders.show', editingOrder.id)" class="text-sm font-semibold text-gray-700 underline">Cancel Editing</Link>
        </div>
        <div class="pos-pane-tabs" role="group" aria-label="POS view">
            <button type="button" :aria-pressed="compactPane === 'products'" @click="showCompactPane('products')" :class="{ 'is-active': compactPane === 'products' }">Products</button>
            <button type="button" :aria-pressed="compactPane === 'order'" @click="showCompactPane('order')" :class="{ 'is-active': compactPane === 'order' }">{{ editingOrder ? 'Edit order' : 'Current order' }} <span class="pos-cart-count">{{ cart.length }}</span></button>
        </div>
        <div class="pos-workspace -m-6">

            <!-- ── Left: Product Grid ─────────────────────────────────────── -->
            <div class="pos-products bg-gray-100" :class="{ 'compact-hidden': compactPane !== 'products' }">
                <p v-if="pendingScans" role="status" class="mb-2 text-sm text-gray-600">Processing scans…</p>
                <p v-if="scanMessage" role="status" aria-live="polite" class="mb-2 text-sm" :class="scanFailed ? 'text-red-600' : 'text-green-700'">{{ scanMessage }}</p>
                <!-- Search + currency toggle -->
                <div class="pos-search-tools mb-3 flex flex-wrap gap-3">
                    <input
                        ref="searchInput"
                        v-model="search"
                        @keydown.enter.prevent="handleBarcodeScan"
                        type="text"
                        maxlength="100"
                        placeholder="Search or scan system code…"
                        class="min-w-0 w-full sm:w-auto flex-1 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-gold-500 focus:border-gold-500 p-4 shadow-sm"
                        aria-label="Search products or scan a system code"
                    />
                    <div class="pos-currencies bg-gray-200 p-1 rounded-lg flex items-center shadow-inner self-stretch px-2 shrink-0">
                        <button
                            v-for="currency in ['MMK', 'USD', 'THB']"
                            :key="currency"
                            @click="displayCurrency = currency"
                            :class="[
                                'min-h-11 px-5 rounded-md text-sm font-bold transition-all duration-200',
                                displayCurrency === currency
                                    ? 'bg-white text-gold-600 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700',
                            ]"
                        >{{ currency }}</button>
                    </div>
                </div>

                <SecondaryButton class="mb-4 min-h-11" :disabled="pendingScans > 0" @click="isCameraOpen = true">Scan with Camera</SecondaryButton>

                <div class="pos-product-filters flex gap-2 mb-4" aria-label="Product type">
                    <button v-for="option in [{ value: '', label: 'All products' }, { value: 'watch', label: 'Watches' }, { value: 'accessory', label: 'Accessories' }]"
                        :key="option.value" @click="productKind = option.value" :aria-pressed="productKind === option.value"
                        class="px-4 py-2 text-sm font-semibold rounded-lg border transition-colors"
                        :class="productKind === option.value ? 'bg-gold-50 border-gold-300 text-gold-800' : 'bg-white border-gray-200 text-gray-600'">{{ option.label }}</button>
                </div>
                <!-- Product cards -->
                <div class="pos-product-grid grid grid-cols-2 gap-3">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="selectSearchProduct(product)"
                        role="button" tabindex="0" @keydown.enter.self.prevent="selectSearchProduct(product)" @keydown.space.self.prevent="selectSearchProduct(product)"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden cursor-pointer hover:border-gold-500 hover:shadow-md transition-all group"
                    >
                        <div class="pos-product-image h-32 bg-gray-100 relative">
                            <img
                                v-if="product.images && product.images[0]"
                                :src="$page.props.storage_url + '/' + product.images[0]"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                            <div class="absolute top-2 right-2 bg-black/60 text-white text-[11px] font-semibold px-2 py-0.5 rounded-full">
                                {{ product.available_items_count || 0 }} in stock
                            </div>
                        </div>
                        <div class="p-3">
                            <h3 class="text-gray-900 font-bold text-sm truncate group-hover:text-gold-600">{{ product.name }}</h3>
                            <p class="text-gray-400 text-xs truncate">{{ product.kind === 'accessory' ? 'Accessory' : product.model_number }}</p>
                            <div v-if="product.matched_item" class="mt-2 rounded-lg bg-gold-50 p-2">
                                <p class="text-xs font-semibold text-gold-800">Exact unit match</p>
                                <p class="break-all font-mono text-xs text-gray-700">{{ product.matched_item.system_unique_id || product.matched_item.serial_number }}</p>
                                <p v-if="product.matched_item.serial_number" class="break-all text-xs text-gray-500">Serial: {{ product.matched_item.serial_number }}</p>
                                <button type="button" class="mt-2 w-full rounded-md bg-gold-600 px-2 py-2 text-xs font-semibold text-white hover:bg-gold-700 disabled:opacity-50"
                                    :disabled="pendingScans > 0 || matchedUnitInCart(product)"
                                    @click.stop="selectSearchProduct(product)">
                                    {{ matchedUnitInCart(product) ? 'Already in cart' : (product.kind === 'accessory' ? 'Add this item' : 'Add this watch') }}
                                </button>
                            </div>
                            <p v-if="product.kind === 'accessory'" class="text-gray-500 text-xs">{{ (product.accessory_attributes || []).map(a => `${a.name}: ${a.value}`).join(' · ') }}</p>
                            <div class="mt-2 flex justify-between items-end">
                                <span class="text-gold-600 font-bold text-sm">
                                    {{ formatPrice(getDisplayPrice(product)) }}
                                    <span class="text-[10px] text-gray-400 font-normal">{{ displayCurrency }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-if="filteredProducts.length === 0 && !productsLoading" class="col-span-full py-16 text-center text-gray-400">
                        {{ productLoadError || 'No available products found.' }}
                    </div>
                </div>
                <div v-if="productLoadError && filteredProducts.length > 0" class="mt-4 text-center text-sm text-red-500">
                    {{ productLoadError }}
                </div>
                <div v-if="productPage < lastProductPage" class="mt-6 flex justify-center">
                    <SecondaryButton @click="loadProducts(true)" :disabled="productsLoading">
                        {{ productsLoading ? 'Loading…' : 'Load more products' }}
                    </SecondaryButton>
                </div>
            </div>

            <!-- ── Right: Cart ───────────────────────────────────────────── -->
            <div ref="orderPanel" class="pos-order bg-white border-gray-200 flex flex-col" :class="{ 'compact-hidden': compactPane !== 'order' }">
                <!-- Header -->
                <div class="p-4 border-b border-gray-200 bg-gray-50 space-y-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">{{ editingOrder ? 'Edit Order' : 'Current Order' }}</h2>
                        <span class="text-gray-400 text-sm">{{ cart.length }} line(s)</span>
                    </div>
                    <label for="pos-customer" class="block text-sm font-medium text-gray-700">Customer</label>
                    <SearchableSelect
                        id="pos-customer"
                        v-model="checkoutForm.customer_id"
                        :options="customerPickerOptions"
                        placeholder="Walk-in Customer"
                        search-placeholder="Search by name, phone or email"
                        option-noun="customers"
                    />
                    <PosGuestCustomer :sources="customerSources" :regions="customerRegions" @selected="selectCustomer" />
                    <div v-if="selectedCustomer?.is_guest" class="rounded-lg bg-amber-50 p-3 text-sm">
                        <p>This is a guest customer. All their orders stay linked to this profile.</p>
                        <button type="button" :disabled="promotingCustomer" @click="promoteCustomer" class="mt-2 font-semibold underline">{{ promotingCustomer ? 'Saving…' : 'Make regular customer' }}</button>
                    </div>
                    <p v-if="customerMessage" role="status" class="text-sm text-gray-700">{{ customerMessage }}</p>
                </div>

                <!-- Cart items -->
                <div class="pos-cart-items flex-1 p-4 space-y-2 bg-white">
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
                                    {{ item.system_unique_id ? `Code: ${item.system_unique_id}` : item.serial_number }}
                                </div>
                                <!-- Generic qty badge -->
                                <div v-else class="mt-0.5 inline-flex items-center gap-1 bg-blue-50 border border-blue-200 text-blue-700 text-[10px] px-1.5 py-0.5 rounded">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                                    Qty: {{ item.qty }} unit{{ item.qty > 1 ? 's' : '' }}
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <template v-if="appliedDiscountPercentage > 0">
                                    <div class="text-gray-400 line-through text-[10px]">{{ (getMmkPrice(item.product) * item.qty).toLocaleString() }} Ks</div>
                                    <div class="text-gold-600 font-bold text-sm leading-tight">
                                        {{ (getMmkPrice(item.product) * item.qty * (1 - appliedDiscountPercentage / 100)).toLocaleString() }} Ks
                                    </div>
                                    <div class="text-[10px] text-green-600 bg-green-50 border border-green-100 px-1.5 py-0.5 rounded mt-0.5">
                                        -{{ appliedDiscountPercentage }}%
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-gold-600 font-bold text-sm">{{ (getMmkPrice(item.product) * item.qty).toLocaleString() }} Ks</div>
                                </template>
                                <button @click="removeFromCart(index)" class="text-red-600 hover:text-red-700 text-sm hover:underline min-h-11 min-w-11 block">Remove</button>
                            </div>
                        </div>
                    </TransitionGroup>
                    <div v-if="cart.length === 0" class="text-center text-gray-400 mt-16 select-none">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Cart is empty
                    </div>
                </div>

                <!-- Totals + checkout -->
                <div class="pos-order-totals p-4 bg-gray-50 border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.06)]">
                    <div class="space-y-1.5 mb-4">
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal</span>
                            <span>{{ subTotal.toLocaleString() }} Ks</span>
                        </div>
                        <div class="py-2">
                            <div class="flex items-center justify-between gap-3">
                                <label for="order_discount_percentage" class="text-sm text-gray-600">
                                    Discount percentage
                                </label>
                                <div class="relative w-24">
                                    <input
                                        id="order_discount_percentage"
                                        v-model.number="checkoutForm.discount_percentage"
                                        @input="discountManuallyAdjusted = true"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full rounded-md border-gray-300 py-1.5 pr-7 text-right text-sm focus:border-gold-500 focus:ring-gold-500"
                                    />
                                    <span class="pointer-events-none absolute right-2 top-1.5 text-sm text-gray-400">%</span>
                                </div>
                            </div>
                            <p class="mt-1 text-right text-[10px] text-gray-400">
                                Default from {{ defaultDiscountSource }}; editable by salesperson
                            </p>
                        </div>
                        <div v-if="discount > 0" class="flex justify-between text-sm text-green-600">
                            <span>Discount ({{ appliedDiscountPercentage }}%)</span>
                            <span>-{{ discount.toLocaleString() }} Ks</span>
                        </div>
                        <div v-if="roundingAdjustment" class="flex justify-between text-sm text-gray-500">
                            <span>Rounding</span>
                            <span>{{ roundingAdjustment > 0 ? '+' : '−' }}{{ Math.abs(roundingAdjustment).toLocaleString() }} Ks</span>
                        </div>
                        <div v-if="deliveryFee > 0" class="flex justify-between text-sm text-gray-500">
                            <span>Delivery fee</span>
                            <span>{{ deliveryFee.toLocaleString() }} Ks</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2">
                            <span class="text-gray-900 font-bold text-lg">Total</span>
                            <span class="text-2xl font-bold text-gold-600">{{ total.toLocaleString() }} Ks</span>
                        </div>
                    </div>
                    <PrimaryButton
                        @click="openCheckout"
                        class="pos-panel-checkout w-full justify-center py-3 bg-gold-500 hover:bg-gold-600 text-dark-900 font-bold text-base shadow-md"
                        :disabled="cart.length === 0 || pendingScans > 0"
                    >
                        {{ editingOrder ? 'Review Changes' : 'Checkout' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <div class="pos-mobile-dock" aria-label="Cart summary">
            <div class="min-w-0"><p class="text-xs text-gray-500" aria-live="polite">{{ cart.length }} line(s) in order</p><p class="break-words text-lg font-bold text-gray-900">{{ total.toLocaleString() }} <span class="text-xs font-normal">Ks</span></p></div>
            <button type="button" class="min-h-12 rounded-xl bg-gray-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-40" :disabled="cart.length === 0 || pendingScans > 0" @click="compactPane === 'products' ? showCompactPane('order') : openCheckout()">{{ pendingScans ? 'Scanning…' : compactPane === 'products' ? 'Review order →' : editingOrder ? 'Review changes →' : 'Checkout →' }}</button>
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
                            By System Code
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
                            class="w-12 h-12 rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:border-gold-500 hover:text-gold-600 transition-colors font-bold text-lg"
                        >−</button>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-gray-900 w-16 text-center">{{ addQty }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">unit{{ addQty > 1 ? 's' : '' }}</div>
                        </div>
                        <button
                            @click="addQty = Math.min(maxQty, addQty + 1)"
                            class="w-12 h-12 rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:border-gold-500 hover:text-gold-600 transition-colors font-bold text-lg"
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
                        Select the watch using the <strong>system code</strong> printed on its label.
                    </p>

                    <!-- Search within available items -->
                    <input
                        v-model="serialSearch"
                        type="text"
                        placeholder="Search system code…"
                        aria-label="Search units by system code or manufacturer serial"
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
                                <!-- Highlight units with a generated system code. -->
                                <div :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-xs shrink-0',
                                    item.system_unique_id
                                        ? 'bg-gold-100 text-gold-700'
                                        : 'bg-gray-100 text-gray-400',
                                ]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                                </div>
                                <div>
                                    <div class="text-[10px] text-gray-500">System code</div>
                                    <div v-if="item.system_unique_id" class="font-mono text-base font-semibold text-gray-800 group-hover:text-gold-700">
                                        {{ item.system_unique_id }}
                                    </div>
                                    <div v-else class="text-sm text-gray-400 italic">No system code · Unit #{{ item.id }}</div>
                                    <div v-if="item.serial_number" class="text-xs text-gray-500 mt-0.5">
                                        Manufacturer serial: {{ item.serial_number }}
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">
                                        <span v-if="item.purchase_date">Purchased: {{ new Date(item.purchase_date).toLocaleDateString() }}</span>
                                        <span v-else>Added: {{ new Date(item.created_at).toLocaleDateString() }}</span>
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
            <div class="pos-checkout p-4 sm:p-6 bg-white text-gray-900">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ editingOrder ? 'Update Order' : 'Checkout' }}</h2>

                <form @submit.prevent="submitCheckout" class="space-y-4">
                    <fieldset class="rounded-lg border border-gray-200 p-4">
                        <legend class="px-1 text-sm font-semibold text-gray-800">Salespeople</legend>
                        <p class="mb-3 text-sm text-gray-500">Select one or more people. Each receives full credit for this sale.</p>
                        <div class="grid max-h-48 gap-3 overflow-y-auto sm:grid-cols-2">
                            <label v-for="person in salespeople" :key="person.id" class="flex items-center gap-2 text-sm">
                                <input v-model="checkoutForm.salesperson_ids" type="checkbox" :value="person.id" class="rounded border-gray-300 text-gold-600 focus:ring-gold-500" />
                                {{ person.name }}
                            </label>
                        </div>
                        <p v-for="(message, key) in Object.fromEntries(Object.entries(checkoutForm.errors).filter(([key]) => key.startsWith('salesperson_ids')))" :key="key" role="alert" class="mt-2 text-sm text-red-600">{{ message }}</p>
                    </fieldset>
                    <SalesRecordFields :form="checkoutForm" />
                    <div>
                        <InputLabel value="Order status" />
                        <select v-model="checkoutForm.status" :disabled="!!editingOrder" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="completed">Completed sale</option>
                            <option value="pending">Pending / Reserve stock / COD</option>
                        </select>
                        <p v-if="checkoutForm.status === 'pending'" class="mt-2 text-sm text-amber-800">Saving reserves the selected units for this order. They cannot be sold elsewhere or deleted. Approval marks those same units sold; cancellation releases them. Enter the payment received so far, including zero.</p>
                        <InputError :message="checkoutForm.errors.status" />
                    </div>
                    <div>
                        <InputLabel value="Delivery code (optional)" />
                        <TextInput v-model="checkoutForm.delivery_code" maxlength="255" class="mt-1 block w-full" />
                        <InputError :message="checkoutForm.errors.delivery_code" />
                    </div>
                    <div>
                        <InputLabel value="Money transfer amount (Ks, optional)" />
                        <TextInput v-model="checkoutForm.money_transfer_amount" type="number" min="0" max="999999999999" step="0.01" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500">Record the transferred/deposit amount here; it appears as Deposit in the Sales export. Include actual receipts in the payment entries below to update the balance due.</p>
                        <InputError :message="checkoutForm.errors.money_transfer_amount" />
                    </div>
                    <div>
                        <InputLabel value="Remark (optional)" />
                        <textarea v-model="checkoutForm.remark" maxlength="5000" rows="3" class="mt-1 block w-full rounded-md border-gray-300" />
                        <InputError :message="checkoutForm.errors.remark" />
                    </div>
                    <p v-for="(message, key) in Object.fromEntries(Object.entries(checkoutForm.errors).filter(([key]) => key.startsWith('cart') || key === 'edit_version' || key === 'customer_id'))" :key="key" role="alert" class="text-sm text-red-600">{{ message }}</p>
                    <InputError :message="checkoutForm.errors.checkout_request_id" />
                    <div
                        v-if="checkoutForm.errors.error"
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        {{ checkoutForm.errors.error }}
                    </div>

                    <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                        <span class="font-bold">Customer:</span>
                        {{ customerLabel(selectedCustomer) }}
                        <span v-if="discount > 0" class="text-green-600 ml-2 font-bold">
                            (Discount: -{{ discount.toLocaleString() }} Ks)
                        </span>
                    </div>

                    <!-- Cart summary -->
                    <div class="bg-gray-50 rounded-lg divide-y divide-gray-100 text-sm max-h-40 overflow-y-auto">
                        <div v-for="(item, i) in cart" :key="i" class="flex justify-between px-3 py-2">
                            <div>
                                <span class="font-medium">{{ item.product.name }}</span>
                                <span v-if="item.system_unique_id || item.serial_number" class="ml-1 text-gold-600 font-mono text-xs">{{ item.system_unique_id ? `Code: ${item.system_unique_id}` : `Serial: ${item.serial_number}` }}</span>
                                <span v-else class="ml-1 text-blue-500 text-xs">×{{ item.qty }}</span>
                            </div>
                            <span class="text-gray-700">{{ (getMmkPrice(item.product) * item.qty).toLocaleString() }} Ks</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold text-gray-900">Payments</h3>
                            <SecondaryButton v-if="checkoutForm.payments.length < paymentMethods.length" @click="addPayment">+ Add Payment</SecondaryButton>
                        </div>
                        <p class="text-sm text-gray-500">Split the total across payment methods. Enter the amount received for each.</p>
                        <div v-for="(payment, index) in checkoutForm.payments" :key="index" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-[140px] flex-1">
                                    <InputLabel :for="`payment-method-${index}`" value="Payment Method" />
                                    <select :id="`payment-method-${index}`" v-model="payment.method" class="mt-1 block w-full rounded-md border-gray-300 text-gray-900">
                                        <option v-for="method in checkoutPaymentMethods" :key="method.value" :value="method.value" :disabled="checkoutForm.payments.some((other, otherIndex) => otherIndex !== index && other.method === method.value)">{{ method.label }}</option>
                                    </select>
                                </div>
                                <div class="min-w-[140px] flex-1">
                                    <InputLabel :for="`payment-amount-${index}`" value="Amount Received (Ks)" />
                                    <TextInput :id="`payment-amount-${index}`" type="number" min="0" max="999999999999" step="0.01" required v-model="payment.amount" class="mt-1 block w-full border-gray-300 text-gray-900" />
                                </div>
                                <button v-if="checkoutForm.payments.length > 1" type="button" @click="removePayment(index)" class="py-2 text-sm text-red-600" :aria-label="`Remove payment ${index + 1}`">Remove</button>
                            </div>
                            <p v-if="checkoutForm.errors[`payments.${index}.method`]" class="mt-1 text-sm text-red-600">{{ checkoutForm.errors[`payments.${index}.method`] }}</p>
                            <p v-if="checkoutForm.errors[`payments.${index}.amount`]" class="mt-1 text-sm text-red-600">{{ checkoutForm.errors[`payments.${index}.amount`] }}</p>
                            <p v-else-if="paymentCents(payment.amount) <= 0 && ((total > 0 && checkoutForm.status !== 'pending') || checkoutForm.payments.length > 1)" class="mt-1 text-sm text-red-600">Enter an amount greater than zero, or remove this payment.</p>
                        </div>
                        <p v-if="amountShort > 0" role="status" class="text-sm text-red-600">{{ amountShort.toLocaleString() }} Ks still due.</p>
                        <p v-if="nonCashOverpaid" role="alert" class="text-sm text-red-600">Non-cash payments cannot exceed the total due. Change can only be returned from cash.</p>
                        <p v-if="checkoutForm.errors.payments" role="alert" class="text-sm text-red-600">{{ checkoutForm.errors.payments }}</p>
                    </div>

                    <div class="pt-3 border-t border-gray-200 space-y-1">
                        <div class="flex justify-between text-sm"><span>Total Received</span><span>{{ amountPaid.toLocaleString() }} Ks</span></div>
                        <div v-if="deliveryFee > 0" class="flex justify-between text-sm"><span>Delivery fee</span><span>{{ deliveryFee.toLocaleString() }} Ks</span></div>
                        <div class="flex justify-between text-lg font-bold">
                            <span class="text-gray-900">Total Due</span>
                            <span class="text-gold-600">{{ total.toLocaleString() }} Ks</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">{{ amountShort > 0 ? 'Balance Due' : 'Cash Change' }}</span>
                            <span :class="amountShort > 0 ? 'text-red-500' : 'text-green-600'">
                                {{ (amountShort > 0 ? amountShort : changeDue).toLocaleString() }} Ks
                            </span>
                        </div>
                    </div>

                    <div class="pos-checkout-actions mt-6 flex flex-wrap justify-end gap-3">
                        <SecondaryButton @click="isCheckoutModalOpen = false">Cancel</SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold"
                            :class="{ 'opacity-25': checkoutForm.processing || (amountShort > 0 && checkoutForm.status !== 'pending') || nonCashOverpaid || invalidPayment }"
                            :disabled="checkoutForm.processing || (amountShort > 0 && checkoutForm.status !== 'pending') || nonCashOverpaid || invalidPayment"
                        >{{ checkoutForm.processing ? 'Saving…' : editingOrder ? 'Save Order Changes' : checkoutForm.status === 'pending' ? 'Save Pending Order' : 'Complete Sale' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
        <CameraBarcodeScanner :show="isCameraOpen" @close="isCameraOpen = false" @scan="handleCameraScan" />
    </AdminLayout>
</template>

<style scoped>
.pos-workspace { display: grid; grid-template-columns: minmax(0, 1fr) 360px; align-items: start; }
.pos-products { min-width: 0; padding: 24px; }
.pos-order { min-width: 0; border-left-width: 1px; }
.pos-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.pos-pane-tabs, .pos-mobile-dock { display: none; }
.pos-header-links a { display: inline-flex; align-items: center; min-height: 44px; }
.pos-product-filters button { min-height: 44px; }
@media (min-width: 1280px) {
    .pos-workspace { grid-template-columns: minmax(0, 1fr) 390px; }
    .pos-product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (min-width: 1536px) {
    .pos-product-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (min-width: 1024px) and (min-height: 700px) {
    .pos-order { position: sticky; top: 0; max-height: 100dvh; overflow-y: auto; }
    .pos-order-totals { position: sticky; bottom: 0; z-index: 10; }
}
@media (max-width: 1023px) {
    .pos-header { flex-wrap: wrap; gap: 4px 16px; padding: 8px 16px; margin-bottom: 12px; }
    .pos-header h1 { white-space: nowrap; }
    .pos-header-links { flex-wrap: wrap; gap: 4px 16px; }
    .pos-header-links a { font-size: 12px; }
    .pos-pane-tabs { display: flex; gap: 6px; padding: 6px; margin: 0 -12px 24px; border: 1px solid #e5e7eb; border-radius: 14px; background: white; }
    .pos-pane-tabs button { display: flex; flex: 1; justify-content: center; align-items: center; gap: 8px; min-height: 44px; padding: 8px; border-radius: 9px; font-size: 14px; font-weight: 600; color: #4b5563; }
    .pos-pane-tabs .is-active { background: #111827; color: white; }
    .pos-cart-count { padding: 1px 7px; border-radius: 999px; background: #e5e7eb; color: #111827; font-size: 12px; }
    .pos-workspace { display: block; padding-bottom: calc(100px + env(safe-area-inset-bottom)); }
    .pos-workspace .compact-hidden { display: none; }
    .pos-products { padding: 16px; }
    .pos-order { border-left: 0; min-height: 50dvh; }
    .pos-product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .pos-panel-checkout { display: none; }
    .pos-mobile-dock { position: fixed; bottom: 0; left: 0; right: 0; z-index: 30; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); border-top: 1px solid #e5e7eb; background: white; box-shadow: 0 -4px 20px #00000008; }
    .pos-mobile-dock button { flex-shrink: 0; }
    .pos-checkout :deep(input:not([type="checkbox"])), .pos-checkout :deep(select), .pos-checkout :deep(textarea) { font-size: 16px; min-height: 44px; }
    .pos-checkout-actions > * { flex: 1; justify-content: center; min-height: 48px; }
    .pos-order :deep(input:not([type="checkbox"])), .pos-order :deep(select) { font-size: 16px; min-height: 44px; }
}
@media (max-width: 599px) {
    .pos-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pos-product-image { height: 120px; }
    .pos-search-tools > input { flex-basis: 100%; padding: 12px; font-size: 16px; }
    .pos-currencies { width: 100%; }
    .pos-currencies button { flex: 1; padding-left: 12px; padding-right: 12px; }
    .pos-product-filters { gap: 6px; }
    .pos-product-filters button { flex: 1; padding: 8px 6px; font-size: 12px; }
}

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
