<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import SystemCodeLabel from '@/Components/SystemCodeLabel.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArchiveBoxIcon, ArrowTopRightOnSquareIcon, CheckCircleIcon, ChevronLeftIcon, ChevronRightIcon, CubeIcon, MagnifyingGlassIcon, PencilSquareIcon, PhotoIcon, PlusIcon, PrinterIcon, Squares2X2Icon, TagIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AccessoryDialog from '@/Components/Accessories/AccessoryDialog.vue';
const props = defineProps({ accessories: Object, types: Array, filters: Object, summary: Object });
const page = usePage();
const activeTab = ref('inventory');
const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');
const statusFilter = ref(props.filters.status || '');
const stockFilter = ref(props.filters.stock || '');
const filtering = ref(false);
const hasFilters = computed(() => !!(search.value || typeFilter.value || statusFilter.value || stockFilter.value));
const filter = () => router.get(route('accessories.index'), { search: search.value, type: typeFilter.value, status: statusFilter.value, stock: stockFilter.value }, { preserveState: true, preserveScroll: true, replace: true, onStart: () => filtering.value = true, onFinish: () => filtering.value = false });
const resetFilters = () => { search.value = ''; typeFilter.value = ''; statusFilter.value = ''; stockFilter.value = ''; filter(); };
const imageUrl = path => `${page.props.storage_url}/${path}`;
const money = value => Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
const editing = ref(null);
const open = ref(false);
const previews = ref([]);
const uploadError = ref('');
const form = useForm({ name: '', accessory_type_id: '', price: '', currency: 'MMK', barcode: '', description: '', is_active: true, is_public: true, accessory_attributes: [], images: [], uploads: [] });
const clearPreviews = () => { previews.value.forEach(p => URL.revokeObjectURL(p.url)); previews.value = []; };
onBeforeUnmount(clearPreviews);
const edit = (item = null) => {
    clearPreviews(); uploadError.value = ''; form.reset(); form.clearErrors(); editing.value = item;
    if (item) Object.keys(form.data()).forEach(key => { if (item[key] !== undefined) form[key] = JSON.parse(JSON.stringify(item[key])); });
    form.is_active = Boolean(form.is_active); form.is_public = Boolean(form.is_public);
    form.accessory_attributes ||= []; form.images ||= []; open.value = true;
};
const suggestFields = () => {
    // Preserve populated fields when switching types, replace unused suggestions.
    form.accessory_attributes = form.accessory_attributes.filter(a => a.value.trim());
    const type = props.types.find(t => t.id === Number(form.accessory_type_id));
    for (const name of type?.fields || []) {
        if (!form.accessory_attributes.some(a => a.name.toLowerCase() === name.toLowerCase())) form.accessory_attributes.push({ name, value: '' });
    }
};
const chooseImages = event => {
    uploadError.value = '';
    const files = Array.from(event.target.files);
    if (form.images.length + form.uploads.length + files.length > 10) uploadError.value = 'You can add up to 10 images per accessory.';
    else if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) uploadError.value = 'Choose JPG, PNG or WebP images smaller than 5 MB.';
    else files.forEach(file => { form.uploads.push(file); previews.value.push({ name: file.name, url: URL.createObjectURL(file) }); });
    event.target.value = '';
};
const removeUpload = index => { URL.revokeObjectURL(previews.value[index].url); previews.value.splice(index, 1); form.uploads.splice(index, 1); };
const closeEditor = () => { if (!form.processing) { open.value = false; clearPreviews(); } };
const save = () => form.transform(data => ({ ...data, accessory_attributes: data.accessory_attributes.filter(a => a.value.trim()), ...(editing.value ? { _method: 'put' } : {}) }))
    .post(editing.value ? route('accessories.update', editing.value.id) : route('accessories.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => { open.value = false; clearPreviews(); } });
const labelProduct = ref(null);
const labelItems = ref([]);
const labelsOpen = ref(false);
const labelsLoading = ref(null);
const labelsError = ref('');
const printLabels = async item => {
    if (labelsLoading.value) return;
    labelsLoading.value = item.id;
    labelsError.value = '';
    try {
        const response = await axios.post(route('accessories.labels', item.id));
        if (!response.data.items.length) {
            labelsError.value = 'No available stock to label. Add stock first, then print labels.';
            return;
        }
        labelProduct.value = item;
        labelItems.value = response.data.items;
        labelsOpen.value = true;
    } catch {
        labelsError.value = 'Could not prepare barcode labels. Please try again.';
    } finally {
        labelsLoading.value = null;
    }
};
const typeOpen = ref(false);
const typeId = ref(null);
const typeForm = useForm({ name: '', fields: [] });
const editType = (type = null) => { typeForm.reset(); typeForm.clearErrors(); typeId.value = type?.id; typeForm.name = type?.name || ''; typeForm.fields = [...(type?.fields || [])]; typeOpen.value = true; };
const saveType = () => {
    const options = { preserveScroll: true, onSuccess: () => typeOpen.value = false };
    typeId.value ? typeForm.put(route('accessory-types.update', typeId.value), options) : typeForm.post(route('accessory-types.store'), options);
};
</script>

<template>
    <Head title="Accessories" />
    <AdminLayout>
        <div class="accessories mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div><p class="eyebrow">Inventory management</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">Accessories</h1><p class="mt-2 text-sm text-gray-500">Every detail, ready to sell. Manage straps, boxes and more.</p></div>
                <div class="flex gap-3"><Link :href="route('pos.index')" class="btn-secondary">Open POS <ArrowTopRightOnSquareIcon class="h-4 w-4" /></Link><button class="btn-primary" @click="edit()"><PlusIcon class="h-4 w-4" />Add accessory</button></div>
            </header>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="stat-card"><span class="stat-icon bg-gold-50 text-gold-700"><Squares2X2Icon /></span><div><p class="stat-label">Total accessories</p><p class="stat-value">{{ summary.total }}</p></div></div>
                <div class="stat-card"><span class="stat-icon bg-emerald-50 text-emerald-700"><CheckCircleIcon /></span><div><p class="stat-label">Active accessories</p><p class="stat-value">{{ summary.active }}</p></div></div>
                <div class="stat-card"><span class="stat-icon bg-blue-50 text-blue-700"><CubeIcon /></span><div><p class="stat-label">Units available</p><p class="stat-value">{{ summary.available_units }}</p></div></div>
                <div class="stat-card"><span class="stat-icon bg-amber-50 text-amber-700"><ArchiveBoxIcon /></span><div><p class="stat-label">Out of stock</p><p class="stat-value">{{ summary.out_of_stock }}</p></div></div>
            </div>

            <p v-if="labelsError" role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ labelsError }}</p>
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 sm:px-6">
                    <nav class="flex gap-6" aria-label="Accessory sections"><button @click="activeTab = 'inventory'" :aria-pressed="activeTab === 'inventory'" class="section-tab" :class="{ selected: activeTab === 'inventory' }">Inventory <span class="count">{{ summary.total }}</span></button><button @click="activeTab = 'types'" :aria-pressed="activeTab === 'types'" class="section-tab" :class="{ selected: activeTab === 'types' }">Accessory types <span class="count">{{ types.length }}</span></button></nav>
                </div>
                <template v-if="activeTab === 'inventory'">
                    <form @submit.prevent="filter" class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5 sm:px-6">
                        <div class="relative min-w-48 flex-1"><MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-gray-400" /><input v-model="search" class="field search-field" placeholder="Search name or SKU…" aria-label="Search name or SKU" maxlength="100" /></div>
                        <select v-model="typeFilter" @change="filter" class="field filter-select" aria-label="Filter by type"><option value="">All types</option><option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                        <select v-model="statusFilter" @change="filter" class="field filter-select" aria-label="Filter by status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                        <select v-model="stockFilter" @change="filter" class="field filter-select" aria-label="Filter by stock"><option value="">All stock</option><option value="available">In stock</option><option value="out">Out of stock</option></select>
                        <button class="btn-secondary" :disabled="filtering">{{ filtering ? 'Searching…' : 'Search' }}</button><button v-if="hasFilters" type="button" class="text-sm text-gray-500 hover:text-gray-900" @click="resetFilters">Clear</button>
                    </form>
                    <div v-if="accessories.data.length" class="overflow-x-auto" :aria-busy="filtering">
                        <table class="w-full text-left text-sm"><thead><tr><th>Accessory</th><th>Price</th><th>Stock</th><th>Visibility</th><th><span class="sr-only">Actions</span></th></tr></thead>
                            <tbody class="divide-y divide-gray-100"><tr v-for="item in accessories.data" :key="item.id" class="group hover:bg-gray-50/70">
                                <td><div class="flex min-w-64 items-center gap-4"><div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-gray-50"><img v-if="item.images?.length" :src="imageUrl(item.images[0])" :alt="item.name" class="h-full w-full object-contain" /><PhotoIcon v-else class="h-6 w-6 text-gray-300" /></div><div><p class="mb-1 text-xs text-gray-400">{{ item.accessory_type?.name }}</p><Link :href="route('accessories.show', item.id)" class="text-left font-semibold text-gray-900 hover:text-gold-700">{{ item.name }}</Link><p v-if="item.barcode" class="mt-1 text-xs text-gray-400">SKU {{ item.barcode }}</p><div v-if="item.accessory_attributes?.length" class="mt-2 flex flex-wrap gap-1"><span v-for="attribute in item.accessory_attributes.slice(0, 3)" :key="attribute.name" class="attribute" :title="attribute.name">{{ attribute.value }}</span><span v-if="item.accessory_attributes.length > 3" class="attribute" :title="item.accessory_attributes.slice(3).map(a => `${a.name}: ${a.value}`).join(' · ')">+{{ item.accessory_attributes.length - 3 }}</span></div></div></div></td>
                                <td class="whitespace-nowrap"><span class="font-semibold text-gray-900">{{ money(item.price) }}</span><span class="ml-1 text-xs text-gray-400">{{ item.currency }}</span></td>
                                <td class="whitespace-nowrap"><p :class="item.available_items ? 'text-gray-900 font-semibold' : 'text-amber-700 font-medium'">{{ item.available_items ? `${item.available_items} available` : 'Out of stock' }}</p><p class="mt-1 text-xs text-gray-400">{{ item.sold_items }} sold · {{ item.reserved_items }} reserved</p></td>
                                <td class="whitespace-nowrap"><span class="badge" :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"><span class="h-1.5 w-1.5 rounded-full bg-current" />{{ item.is_active ? 'Active' : 'Inactive' }}</span><p class="mt-2 text-xs text-gray-400">{{ item.is_active && item.is_public ? 'Visible via catalog API' : 'Hidden from catalog API' }}</p></td>
                                <td><div class="flex justify-end gap-2"><button class="icon-button disabled:opacity-40" :disabled="!item.available_items || labelsLoading !== null" @click="printLabels(item)" :aria-label="`Print labels for ${item.name}`" :title="item.available_items ? 'Print barcode labels' : 'Add stock to print labels'"><PrinterIcon class="h-5 w-5" :class="{ 'animate-pulse': labelsLoading === item.id }" /></button><Link class="btn-secondary whitespace-nowrap" :href="route('accessories.show', item.id)"><CubeIcon class="h-4 w-4" />Stock</Link><button class="icon-button" @click="edit(item)" :aria-label="`Edit ${item.name}`" title="Edit accessory"><PencilSquareIcon class="h-5 w-5" /></button></div></td>
                            </tr></tbody>
                        </table>
                    </div>
                    <div v-else class="flex flex-col items-center px-6 py-16 text-center"><div class="mb-5 rounded-2xl bg-gold-50 p-5"><ArchiveBoxIcon class="h-9 w-9 text-gold-600" /></div><h2 class="text-lg font-semibold text-gray-900">{{ hasFilters ? 'No matching accessories' : 'Your accessory collection starts here' }}</h2><p class="mt-2 max-w-sm text-sm text-gray-500">{{ hasFilters ? 'Try a different name, SKU or filter to find what you need.' : 'Add your first strap or box, then add stock to make it available in POS.' }}</p><button class="btn-primary mt-6" @click="hasFilters ? resetFilters() : edit()">{{ hasFilters ? 'Clear filters' : 'Add your first accessory' }}</button></div>
                    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4"><p class="text-xs text-gray-500">{{ accessories.total ? `${accessories.from}–${accessories.to} of ${accessories.total} accessories` : '0 accessories' }}</p><div class="flex items-center gap-3"><Link v-if="accessories.prev_page_url" :href="accessories.prev_page_url" preserve-scroll class="icon-button" aria-label="Previous page"><ChevronLeftIcon class="h-4 w-4" /></Link><span class="text-xs text-gray-500">Page {{ accessories.current_page }} of {{ accessories.last_page }}</span><Link v-if="accessories.next_page_url" :href="accessories.next_page_url" preserve-scroll class="icon-button" aria-label="Next page"><ChevronRightIcon class="h-4 w-4" /></Link></div></footer>
                </template>
                <div v-else class="p-6"><div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-gray-900">A place for every accessory</h2><p class="mt-1 text-sm text-gray-500">Set up types and reusable field suggestions for your team.</p></div><button class="btn-secondary" @click="editType()"><PlusIcon class="h-4 w-4" />New type</button></div><div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><div v-for="type in types" :key="type.id" class="rounded-xl border border-gray-200 p-5"><div class="mb-4 flex items-center justify-between"><div class="rounded-lg bg-gold-50 p-2.5"><TagIcon class="h-5 w-5 text-gold-700" /></div><button @click="editType(type)" class="icon-button" :aria-label="`Edit ${type.name}`"><PencilSquareIcon class="h-5 w-5" /></button></div><h3 class="font-semibold text-gray-900">{{ type.name }}</h3><p class="mt-1 text-xs text-gray-400">{{ type.fields.length }} suggested fields</p><div class="mt-4 flex flex-wrap gap-2"><span v-for="field in type.fields" :key="field" class="attribute">{{ field }}</span><p v-if="!type.fields.length" class="text-xs text-gray-400">Add custom fields when creating an accessory.</p></div></div></div></div>
            </section>
            <p class="flex items-start gap-2 text-xs text-gray-400"><CubeIcon class="h-4 w-4 shrink-0" />Active accessories with available stock appear in POS. Use a separate entry for each size or color with its own price and stock.</p>
        </div>

        <SystemCodeLabel v-if="labelProduct" :show="labelsOpen" :product="labelProduct" :items="labelItems" @close="labelsOpen = false" />
        <AccessoryDialog :show="open" :title="editing ? 'Edit accessory' : 'Add accessory'" description="Keep the details your team needs, all in one place." :busy="form.processing" wide @close="closeEditor">
            <form @submit.prevent="save" class="accessories">
                <div v-if="Object.keys(form.errors).length" role="alert" class="mx-6 mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"><p class="mb-1 font-semibold">Please check the following details</p><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
                <div class="grid gap-7 p-5 sm:p-7 md:grid-cols-[1fr_260px]">
                    <div class="space-y-7">
                        <section class="space-y-4"><h3 class="form-section">Basic information</h3><label class="label">Accessory name <span class="required">*</span><input v-model="form.name" class="field" placeholder="e.g. Classic leather strap · 20 mm" required maxlength="255" /></label><div class="grid gap-4 sm:grid-cols-2"><label class="label">Accessory type <span class="required">*</span><select v-model="form.accessory_type_id" class="field" required @change="suggestFields"><option value="" disabled>Select a type</option><option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option></select></label><label class="label">SKU / barcode <span class="optional">Optional</span><input v-model="form.barcode" class="field" placeholder="Auto-generated if empty" maxlength="255" /></label></div><label class="label">Description <span class="optional">Optional</span><textarea v-model="form.description" class="field" rows="3" placeholder="Materials, compatibility and other useful details…" maxlength="10000" /></label></section>
                        <section class="space-y-4 border-t border-gray-100 pt-6"><h3 class="form-section">Pricing</h3><div class="grid grid-cols-[1fr_110px] gap-4"><label class="label">Selling price <span class="required">*</span><input v-model="form.price" class="field" type="number" min="0" max="9999999999.99" step="0.01" placeholder="0.00" required /></label><label class="label">Currency<select v-model="form.currency" class="field"><option v-for="currency in ['MMK','USD','THB','SGD','CNY']" :key="currency">{{ currency }}</option></select></label></div><p class="hint">{{ editing ? 'Use the Stock action in the inventory list to adjust available units.' : 'Save this accessory, then use Stock in the inventory list to add units.' }}</p></section>
                        <section class="space-y-3 border-t border-gray-100 pt-6"><div class="flex items-center justify-between"><h3 class="form-section">Specifications</h3><button type="button" class="text-sm font-semibold text-gold-700 hover:text-gold-900 disabled:opacity-50" :disabled="form.accessory_attributes.length >= 30" @click="form.accessory_attributes.push({ name: '', value: '' })">+ Add field</button></div><p class="hint">Choose a type for suggested fields, or add your own. Empty values are skipped.</p><div v-if="!form.accessory_attributes.length" class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-sm text-gray-400">Size, material, color—add the details that matter.</div><div v-for="(attribute, index) in form.accessory_attributes" :key="index" class="flex items-start gap-2"><input v-model="attribute.name" placeholder="Field name" :aria-label="`Field ${index + 1} name`" class="field min-w-0 flex-1" :required="!!attribute.value" maxlength="80" /><input v-model="attribute.value" placeholder="e.g. 20 mm" :aria-label="`Field ${index + 1} value`" class="field min-w-0 flex-1" maxlength="255" /><button type="button" class="icon-button shrink-0" @click="form.accessory_attributes.splice(index, 1)" :aria-label="`Remove field ${index + 1}`"><TrashIcon class="h-4 w-4" /></button></div></section>
                    </div>
                    <aside class="space-y-6">
                        <section class="rounded-xl border border-gray-200 p-4"><h3 class="form-section">Images <span class="float-right text-xs font-normal text-gray-400">{{ form.images.length + form.uploads.length }}/10</span></h3><p class="mt-2 hint">The first image is the cover.</p><div v-if="form.images.length || previews.length" class="mt-4 grid grid-cols-2 gap-2"><div v-for="(image, index) in form.images" :key="image" class="image-tile"><img :src="imageUrl(image)" alt="Accessory image" /><button type="button" class="image-remove" @click="form.images.splice(index, 1)" :aria-label="`Remove saved image ${index + 1}`"><XMarkIcon class="h-3 w-3" /></button></div><div v-for="(preview, index) in previews" :key="preview.url" class="image-tile"><img :src="preview.url" :alt="preview.name" /><button type="button" class="image-remove" @click="removeUpload(index)" :aria-label="`Remove ${preview.name}`"><XMarkIcon class="h-3 w-3" /></button><span class="absolute bottom-1 left-1 rounded bg-white/90 px-1 text-[10px] text-gray-500">New</span></div></div><label class="mt-4 flex cursor-pointer flex-col items-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-5 text-center hover:border-gold-400 focus-within:ring-2 focus-within:ring-gold-500"><PhotoIcon class="mb-2 h-6 w-6 text-gray-400" /><span class="text-sm font-medium text-gold-700">Choose images</span><span class="mt-1 text-xs text-gray-400">JPG, PNG or WebP · 5 MB each</span><input class="sr-only" type="file" multiple accept="image/jpeg,image/png,image/webp" @change="chooseImages" /></label><p v-if="uploadError" role="alert" class="mt-2 text-xs text-red-600">{{ uploadError }}</p></section>
                        <section class="rounded-xl border border-gray-200 p-4"><h3 class="form-section mb-4">Availability</h3><label class="flex cursor-pointer items-start gap-3"><input v-model="form.is_active" type="checkbox" class="check" /><span><span class="block text-sm font-medium text-gray-800">Active in POS</span><span class="hint mt-1 block">Available for sale when in stock.</span></span></label><div class="my-4 border-t border-gray-100" /><label class="flex cursor-pointer items-start gap-3"><input v-model="form.is_public" type="checkbox" class="check" /><span><span class="block text-sm font-medium text-gray-800">Include in catalog API</span><span class="hint mt-1 block">Let the storefront display this accessory when active.</span></span></label></section>
                    </aside>
                </div>
                <footer class="dialog-footer"><p class="mr-auto hidden text-xs text-gray-400 sm:block">{{ form.progress ? `Uploading ${form.progress.percentage}%` : '* Required fields' }}</p><button type="button" class="btn-secondary" :disabled="form.processing" @click="closeEditor">Cancel</button><button :disabled="form.processing" class="btn-primary">{{ form.processing ? 'Saving…' : 'Save accessory' }}</button></footer>
            </form>
        </AccessoryDialog>

        <AccessoryDialog :show="typeOpen" :title="typeId ? 'Edit accessory type' : 'New accessory type'" description="Give similar accessories a shared set of suggested fields." :busy="typeForm.processing" @close="typeOpen = false">
            <form @submit.prevent="saveType" class="accessories"><div class="space-y-5 p-6"><label class="label">Type name <span class="required">*</span><input v-model="typeForm.name" class="field" placeholder="e.g. Travel cases" required maxlength="100" /></label><div><h3 class="form-section">Suggested fields</h3><p class="hint mt-2 mb-4">Examples: Size, Material, Color or Capacity. Existing accessory values are preserved when you edit these suggestions.</p><div v-for="(_, index) in typeForm.fields" :key="index" class="mb-2 flex gap-2"><input v-model="typeForm.fields[index]" class="field" required maxlength="80" placeholder="Field name" :aria-label="`Suggested field ${index + 1}`" /><button type="button" class="icon-button" @click="typeForm.fields.splice(index, 1)" :aria-label="`Remove suggested field ${index + 1}`"><TrashIcon class="h-4 w-4" /></button></div><button type="button" class="btn-secondary mt-2" :disabled="typeForm.fields.length >= 30" @click="typeForm.fields.push('')"><PlusIcon class="h-4 w-4" />Add field</button></div><p v-for="error in typeForm.errors" :key="error" class="text-sm text-red-600" role="alert">{{ error }}</p></div><footer class="dialog-footer"><button type="button" class="btn-secondary" :disabled="typeForm.processing" @click="typeOpen = false">Cancel</button><button :disabled="typeForm.processing" class="btn-primary">{{ typeForm.processing ? 'Saving…' : 'Save type' }}</button></footer></form>
        </AccessoryDialog>
    </AdminLayout>
</template>

<style scoped>
.eyebrow { @apply text-[10px] font-bold uppercase tracking-[.18em] text-gold-700; }
.btn-primary { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-500 focus-visible:ring-offset-2 disabled:opacity-50; }
.btn-secondary { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-500 disabled:opacity-50; }
.icon-button { @apply inline-flex items-center justify-center rounded-lg p-2.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-gold-500; }
.stat-card { @apply flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5; }
.stat-icon { @apply hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:flex; }
.stat-icon svg { @apply h-5 w-5; }
.stat-label { @apply text-xs text-gray-500; }
.stat-value { @apply mt-1 text-2xl font-semibold tracking-tight text-gray-900; }
.section-tab { @apply flex items-center gap-2 border-b-2 border-transparent py-5 text-sm font-medium text-gray-500 hover:text-gray-900; }
.section-tab.selected { @apply border-gold-600 text-gold-700; }
.count { @apply rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500; }
.field { @apply block w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-gold-500 focus:ring-gold-500; }
.search-field { @apply pl-10; }
.filter-select { @apply w-auto min-w-32 pr-8; }
.label { @apply block text-xs font-semibold text-gray-600; }
.label .field { @apply mt-2 font-normal; }
.required { @apply text-gold-700; }
.optional { @apply ml-1 text-[10px] font-normal text-gray-400; }
.form-section { @apply text-sm font-semibold text-gray-900; }
.hint { @apply text-xs leading-relaxed text-gray-500; }
.attribute { @apply inline-block rounded-md bg-gray-100 px-2 py-1 text-[11px] text-gray-500; }
.badge { @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium; }
th { @apply bg-gray-50/70 px-6 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400; }
td { @apply px-6 py-5; }
.dialog-footer { @apply flex justify-end gap-3 border-t border-gray-100 bg-gray-50/70 px-6 py-4; }
.check { @apply mt-0.5 rounded border-gray-300 text-gold-600 focus:ring-gold-500; }
.image-tile { @apply relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-50; }
.image-tile img { @apply h-full w-full object-contain; }
.image-remove { @apply absolute right-1 top-1 rounded-full bg-white p-1 text-gray-600 shadow hover:text-red-600 focus-visible:ring-2 focus-visible:ring-gold-500; }
</style>
