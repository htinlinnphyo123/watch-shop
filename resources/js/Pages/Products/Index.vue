<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { Head, useForm, router, Link, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import debounce from "lodash/debounce";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const props = defineProps({
  products: {
    type: Object,
    default: () => ({ data: [], links: [] }),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  brands: {
    type: Array,
    default: () => [],
  },
  collections: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  customer_groups: {
    type: Array,
    default: () => [],
  },
  specOptions: {
    type: Object,
    default: () => ({}),
  },
});

console.log("Products Data:", props.products);

const page = usePage();
const displayCurrency = ref("MMK"); // Default view

const activeFilters = ref({
  search: props.filters.search || "",
  category_id: props.filters.category_id || "",
  collection_id: props.filters.collection_id || "",
  min_price: props.filters.min_price || "",
  max_price: props.filters.max_price || "",
  in_stock: props.filters.in_stock === "true" || false,
});

watch(
  activeFilters,
  debounce(function (value) {
    let params = {};
    if (value.search) params.search = value.search;
    if (value.category_id) params.category_id = value.category_id;
    if (value.collection_id) params.collection_id = value.collection_id;
    if (value.min_price) params.min_price = value.min_price;
    if (value.max_price) params.max_price = value.max_price;
    if (value.in_stock) params.in_stock = "true";

    router.get(route("products.index"), params, {
      preserveState: true,
      replace: true,
    });
  }, 300),
  { deep: true },
);

// Helper to calculate the displayed price in the selected displayCurrency
const getDisplayPrice = (product) => {
  if (!product) return 0;

  // First, convert product's base price to MMK (if it isn't already)
  let productMmkRate = 1;
  if (product.currency && product.currency !== "MMK") {
    productMmkRate = parseFloat(
      page.props.settings[product.currency.toLowerCase() + "_rate"] || 1,
    );
  }
  const mmkPrice = parseFloat(product.price) * productMmkRate;

  // Second, convert from MMK to the target display currency
  if (displayCurrency.value === "MMK") {
    return mmkPrice;
  } else {
    const targetRate = parseFloat(
      page.props.settings[displayCurrency.value.toLowerCase() + "_rate"] || 1,
    );
    if (targetRate > 0) {
      return mmkPrice / targetRate;
    }
    return mmkPrice; // Fallback
  }
};

const formatPrice = (amount) => {
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: displayCurrency.value === "MMK" ? 0 : 2,
    maximumFractionDigits: displayCurrency.value === "MMK" ? 0 : 2,
  }).format(amount);
};

const getCustomerGroupName = (id) => {
  const group = props.customer_groups.find((g) => g.id === id);
  return group ? group.name : "Unknown Group";
};

const getGroupDefaultPercentage = (id) => {
  const group = props.customer_groups.find((g) => g.id === id);
  return group ? group.percentage : 0;
};

const form = useForm({
  brand_id: "",
  collection_id: "",
  category_ids: [],
  name: "",
  model_number: "",
  price: "",
  web_price: "",
  discount: "",
  cost_price: "",
  warranty_period: "12", // default 12 months
  warranty_type: "",
  description: "",
  barcode: "",
  currency: "MMK",
  crystal: "",
  caliber_code: "",
  caseback_design: "",
  water_resistant: "",
  case_shape: "",
  dial_size: "",
  dial_color: "",
  strap_material: "",
  strap_size: "",
  strap_color: "",
  movement: "",
  gender: "",
  strap_style: "",
  quick_release: "",
  clasp_type: "",
  origin: "",
  customer_group_discounts: [],
  images: [],
  remove_images: [],
  preview_photo: null,
  preview_bg_photo: null,
  is_latest: false,
  is_featured: false,
  is_banner: false,
  is_limited_collection: false,
  is_active: true,
  is_public: true,
});

const isModalOpen = ref(false);
const editingProduct = ref(null);
const imageInput = ref(null);
const imagesInput = ref(null);
const previewPhotoInput = ref(null);
const previewBgPhotoInput = ref(null);
const previewImages = ref([]);
const previewPhotoUrl = ref(null);
const previewBgPhotoUrl = ref(null);
const fileInput = ref(null);
const isUploading = ref(false);

const handleImport = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append("file", file);

  router.post(route("products.import"), formData, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: (page) => {
      if (page.props.flash?.import_errors?.length > 0) {
        // UI will show the errors box
        alert("Import failed. Please fix the errors listed above the table.");
      } else {
        alert("Products imported successfully!");
      }
      if (fileInput.value) fileInput.value.value = null;
    },
    onError: (errors) => {
      console.error(errors);
      alert("Import failed. Please check the file format.");
      if (fileInput.value) fileInput.value.value = null;
    },
  });
};

const handleMultipleImages = (e) => {
  form.images = Array.from(e.target.files);
  previewImages.value = [];
  form.images.forEach((file) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImages.value.push(e.target.result);
    };
    reader.readAsDataURL(file);
  });
};

const handlePreviewPhoto = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.preview_photo = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    previewPhotoUrl.value = e.target.result;
  };
  reader.readAsDataURL(file);
};

const handlePreviewBgPhoto = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.preview_bg_photo = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    previewBgPhotoUrl.value = e.target.result;
  };
  reader.readAsDataURL(file);
};

const markImageForRemoval = (imgPath, index) => {
  form.remove_images.push(imgPath);
  editingProduct.value.images.splice(index, 1);
};

const openModal = (product = null) => {
  editingProduct.value = product;
  if (product) {
    form.brand_id = product.brand_id;
    form.collection_id = product.collection_id || "";
    form.category_ids = product.categories
      ? product.categories.map((c) => c.id)
      : [];
    form.name = product.name;
    form.model_number = product.model_number;
    form.price = product.price;
    form.web_price = product.web_price || "";
    form.discount = product.discount || "";
    form.cost_price = product.cost_price;
    form.warranty_period = product.warranty_period;
    form.warranty_type = product.warranty_type || "";
    form.description = product.description;
    form.barcode = product.barcode;
    form.currency = product.currency || "MMK";
    form.crystal = product.crystal || "";
    form.caliber_code = product.caliber_code || "";
    form.caseback_design = product.caseback_design || "";
    form.water_resistant = product.water_resistant || "";
    form.case_shape = product.case_shape || "";
    form.dial_size = product.dial_size || "";
    form.dial_color = product.dial_color || "";
    form.strap_material = product.strap_material || "";
    form.strap_size = product.strap_size || "";
    form.strap_color = product.strap_color || "";
    form.movement = product.movement || "";
    form.gender = product.gender || "";
    form.strap_style = product.strap_style || "";
    form.quick_release = product.quick_release || "";
    form.clasp_type = product.clasp_type || "";
    form.origin = product.origin || "";
    form.customer_group_discounts = props.customer_groups.map((group) => {
      const existing = product.customer_groups?.find(
        (cg) => cg.id === group.id,
      );
      return {
        group_id: group.id,
        percentage: existing ? existing.pivot.percentage : "",
      };
    });
    form.images = [];
    form.remove_images = [];
    previewImages.value = [];
    form.preview_photo = null;
    form.preview_bg_photo = null;
    previewPhotoUrl.value = product.preview_photo ? (page.props.storage_url + '/' + product.preview_photo) : null;
    previewBgPhotoUrl.value = product.preview_bg_photo ? (page.props.storage_url + '/' + product.preview_bg_photo) : null;
    form.is_latest = !!product.is_latest;
    form.is_featured = !!product.is_featured;
    form.is_banner = !!product.is_banner;
    form.is_limited_collection = !!product.is_limited_collection;
    form.is_active =
      product.is_active !== undefined ? !!product.is_active : true;
    form.is_public =
      product.is_public !== undefined ? !!product.is_public : true;
  } else {
    form.reset();
    form.collection_id = "";
    form.customer_group_discounts = props.customer_groups.map((group) => ({
      group_id: group.id,
      percentage: "",
    }));
    form.images = [];
    form.remove_images = [];
    form.currency = "MMK";
    form.preview_photo = null;
    form.preview_bg_photo = null;
    previewPhotoUrl.value = null;
    previewBgPhotoUrl.value = null;
    form.is_latest = false;
    form.is_featured = false;
    form.is_banner = false;
    form.is_limited_collection = false;
    form.is_active = true;
    form.is_public = true;
    form.warranty_type = "";
    form.crystal = "";
    form.caliber_code = "";
    form.caseback_design = "";
    form.water_resistant = "";
    form.case_shape = "";
    form.dial_size = "";
    form.dial_color = "";
    form.strap_material = "";
    form.strap_size = "";
    form.strap_color = "";
    form.movement = "";
    form.gender = "";
    form.strap_style = "";
    form.quick_release = "";
    form.clasp_type = "";
    form.origin = "";
    form.web_price = "";
    form.discount = "";
    previewImages.value = [];
  }
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  form.reset();
  form.category_ids = [];
  editingProduct.value = null;
  previewImages.value = [];
  previewPhotoUrl.value = null;
  previewBgPhotoUrl.value = null;
  if (imageInput.value) imageInput.value.value = null;
  if (imagesInput.value) imagesInput.value.value = null;
  if (previewPhotoInput.value) previewPhotoInput.value.value = null;
  if (previewBgPhotoInput.value) previewBgPhotoInput.value.value = null;
};

const uploadFileToS3 = async (file) => {
  if (!file || !(file instanceof File)) return file;
  try {
    const response = await window.axios.post(route("products.presigned-url"), {
      filename: file.name,
      contentType: file.type || 'application/octet-stream',
    });
    
    let targetUrl = response.data.url;
    let extraHeaders = response.data.headers || {};
    
    if (typeof targetUrl === 'object' && targetUrl !== null) {
      extraHeaders = targetUrl.headers || extraHeaders;
      targetUrl = targetUrl.url;
    }

    await fetch(targetUrl, {
      method: "PUT",
      body: file,
      headers: {
        "Content-Type": file.type || 'application/octet-stream',
        "x-amz-acl": "public-read",
        ...extraHeaders
      },
    });
    return response.data.path || response.data.url?.path || '';
  } catch (error) {
    console.error("Error uploading to S3:", error);
    alert("Image upload failed: " + file.name);
    throw error;
  }
};

const submit = async () => {
  isUploading.value = true;
  
  try {
    // Presigned S3 uploads
    if (form.image instanceof File) form.image = await uploadFileToS3(form.image);
    if (form.preview_photo instanceof File) form.preview_photo = await uploadFileToS3(form.preview_photo);
    if (form.preview_bg_photo instanceof File) form.preview_bg_photo = await uploadFileToS3(form.preview_bg_photo);
    
    if (form.images && form.images.length > 0) {
      const processedImages = [];
      for (let i = 0; i < form.images.length; i++) {
        if (form.images[i] instanceof File) {
           processedImages.push(await uploadFileToS3(form.images[i]));
        } else {
           processedImages.push(form.images[i]);
        }
      }
      form.images = processedImages;
    }

    if (editingProduct.value) {
      router.post(
        route("products.update", editingProduct.value.id),
        { _method: "put", ...form.data() },
        { forceFormData: true, onSuccess: () => { isUploading.value = false; closeModal(); }, onError: () => { isUploading.value = false; } }
      );
    } else {
      form.post(route("products.store"), {
        onSuccess: () => { isUploading.value = false; closeModal(); },
        onError: () => { isUploading.value = false; }
      });
    }
  } catch (err) {
    isUploading.value = false;
  }
};

const deleteProduct = (product) => {
  if (confirm("Are you sure you want to delete this product?")) {
    useForm({}).delete(route("products.destroy", product.id));
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
            v-for="currency in ['MMK', 'USD', 'THB']"
            :key="currency"
            @click="displayCurrency = currency"
            :class="[
              'px-4 py-1.5 rounded-md text-sm font-bold transition-all duration-200',
              displayCurrency === currency
                ? 'bg-white text-gold-600 shadow-sm'
                : 'text-gray-500 hover:text-gray-700',
            ]"
          >
            {{ currency }}
          </button>
        </div>

        <a
          :href="route('products.export')"
          class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-bold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-sm"
        >
          Export Excel
        </a>

        <SecondaryButton @click="fileInput.click()" class="shadow-sm">
          Import Excel
        </SecondaryButton>
        <input
          type="file"
          ref="fileInput"
          class="hidden"
          accept=".csv, .xlsx, .xls"
          @change="handleImport"
        />

        <PrimaryButton
          @click="openModal()"
          class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold"
        >
          Add Watch
        </PrimaryButton>
      </div>
    </div>

    <!-- Import Errors Alert -->
    <div
      v-if="$page.props.flash?.import_errors?.length"
      class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm"
    >
      <h3 class="text-red-800 font-bold mb-2 flex items-center">
        <svg
          class="w-5 h-5 mr-2"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          ></path>
        </svg>
        Excel Import Failed ({{
          $page.props.flash.import_errors.length
        }}
        errors)
      </h3>
      <ul
        class="list-disc pl-8 text-sm text-red-700 space-y-1 max-h-40 overflow-y-auto"
      >
        <li v-for="(error, i) in $page.props.flash.import_errors" :key="i">
          {{ error }}
        </li>
      </ul>
    </div>

    <!-- Filter Bar -->
    <div
      class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6 flex flex-wrap gap-4 items-end"
    >
      <div class="flex-1 min-w-[200px]">
        <InputLabel
          value="Search Name, Model or Barcode"
          class="text-gray-700 text-xs"
        />
        <TextInput
          type="text"
          v-model="activeFilters.search"
          placeholder="Search..."
          class="mt-1 block w-full text-sm bg-gray-50 border-gray-300"
        />
      </div>
      <div class="w-[200px]">
        <InputLabel value="Category" class="text-gray-700 text-xs" />
        <select
          v-model="activeFilters.category_id"
          class="mt-1 block w-full text-sm bg-gray-50 border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.name }}
          </option>
        </select>
      </div>
      <div class="w-[200px]">
        <InputLabel value="Collection" class="text-gray-700 text-xs" />
        <select
          v-model="activeFilters.collection_id"
          class="mt-1 block w-full text-sm bg-gray-50 border-gray-300 rounded focus:border-gold-500 focus:ring-gold-500"
        >
          <option value="">All Collections</option>
          <option v-for="col in collections" :key="col.id" :value="col.id">
            {{ col.name }}
          </option>
        </select>
      </div>
      <div class="w-[120px]">
        <InputLabel value="Min Price" class="text-gray-700 text-xs" />
        <TextInput
          type="number"
          v-model="activeFilters.min_price"
          placeholder="Min"
          class="mt-1 block w-full text-sm bg-gray-50 border-gray-300"
        />
      </div>
      <div class="w-[120px]">
        <InputLabel value="Max Price" class="text-gray-700 text-xs" />
        <TextInput
          type="number"
          v-model="activeFilters.max_price"
          placeholder="Max"
          class="mt-1 block w-full text-sm bg-gray-50 border-gray-300"
        />
      </div>
      <div class="w-[120px] pb-2 flex items-center">
        <input
          type="checkbox"
          id="filter_in_stock"
          v-model="activeFilters.in_stock"
          class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
        />
        <label for="filter_in_stock" class="ml-2 block text-sm text-gray-900"
          >In Stock Only</label
        >
      </div>
    </div>

    <div
      class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Image
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Name / Model
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Brand
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Category
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Price Details
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Stock
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="product in products.data"
              :key="product.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <!-- Primary old image or first gallery image -->
                <img
                  v-if="
                    product.image ||
                    (product.images && product.images.length > 0)
                  "
                  :src="
                    $page.props.storage_url +
                    '/' +
                    (product.image || product.images[0])
                  "
                  class="h-12 w-12 rounded object-cover border border-gray-200"
                />
                <div
                  v-else
                  class="h-12 w-12 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs"
                >
                  No Img
                </div>
                <div
                  v-if="product.images && product.images.length > 1"
                  class="text-[10px] text-gray-400 mt-1 text-center"
                >
                  +{{ product.images.length - (product.image ? 0 : 1) }} more
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-gray-900 font-medium">{{ product.name }}</div>
                <div class="text-gray-500 text-xs">
                  {{ product.model_number }}
                </div>
                <div class="text-gray-400 text-[10px]">
                  {{ product.barcode }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                {{ product.brand?.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                <div
                  v-if="product.categories && product.categories.length"
                  class="flex flex-wrap gap-1"
                >
                  <span
                    v-for="cat in product.categories"
                    :key="cat.id"
                    class="px-2 py-0.5 bg-gray-100 rounded text-xs"
                  >
                    {{ cat.name }}
                  </span>
                </div>
                <span v-else class="text-xs text-gray-400">No Category</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-gray-900 font-bold text-lg">
                  {{ formatPrice(getDisplayPrice(product)) }}
                  {{ displayCurrency }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <Link
                  v-if="product.id"
                  :href="route('products.show', product.id)"
                  class="inline-flex items-center text-blue-600 hover:text-blue-800 underline text-sm"
                >
                  Manage Stock
                  <span
                    class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold font-mono"
                    :class="
                      product.available_stock_count > 0
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700'
                    "
                  >
                    ({{ product.available_stock_count || 0 }})
                  </span>
                </Link>
                <span v-else class="text-red-500 text-xs">Invalid ID</span>
              </td>
              <td
                class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3"
              >
                <button
                  @click="openModal(product)"
                  class="text-gold-600 hover:text-gold-800"
                >
                  Edit
                </button>
                <button
                  @click="deleteProduct(product)"
                  class="text-red-600 hover:text-red-800"
                >
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="!products?.data?.length">
              <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                No watches found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        <div class="flex items-center justify-between">
          <div class="flex-1 flex justify-between sm:hidden">
            <Link
              v-if="products.prev_page_url"
              :href="products.prev_page_url"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Previous
            </Link>
            <Link
              v-if="products.next_page_url"
              :href="products.next_page_url"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Next
            </Link>
          </div>
          <div
            class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between"
          >
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
              <nav
                class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                aria-label="Pagination"
              >
                <Link
                  v-for="(link, k) in products.links"
                  :key="k"
                  :href="link.url || '#'"
                  v-html="link.label"
                  :class="{
                    'z-10 bg-gold-50 border-gold-500 text-gold-600':
                      link.active,
                    'bg-white border-gray-300 text-gray-500 hover:bg-gray-50':
                      !link.active,
                    'cursor-not-allowed': !link.url,
                  }"
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
          {{ editingProduct ? "Edit Watch" : "Add New Watch" }}
        </h2>

        <form
          @submit.prevent="submit"
          class="space-y-4"
          enctype="multipart/form-data"
        >
          <div class="grid grid-cols-3 gap-4">
            <div>
              <InputLabel value="Brand" class="text-gray-700" />
              <select
                v-model="form.brand_id"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
              >
                <option value="" disabled>Select Brand</option>
                <option
                  v-for="brand in brands"
                  :key="brand.id"
                  :value="brand.id"
                >
                  {{ brand.name }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.brand_id" />
            </div>
            <div>
              <InputLabel value="Collection" class="text-gray-700" />
              <select
                v-model="form.collection_id"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
              >
                <option value="">No Collection</option>
                <option
                  v-for="collection in collections"
                  :key="collection.id"
                  :value="collection.id"
                >
                  {{ collection.name }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.collection_id" />
            </div>
            <div>
              <InputLabel value="Categories" class="text-gray-700" />
              <!-- Selected badges -->
              <div class="flex flex-wrap gap-1 mt-1 min-h-[24px]">
                <span
                  v-for="id in form.category_ids"
                  :key="id"
                  class="inline-flex items-center gap-1 px-2 py-0.5 bg-gold-100 text-gold-800 text-xs font-semibold rounded-full border border-gold-300"
                >
                  {{ categories.find((c) => c.id === id)?.name }}
                  <button
                    type="button"
                    @click="
                      form.category_ids = form.category_ids.filter(
                        (i) => i !== id,
                      )
                    "
                    class="text-gold-600 hover:text-red-500 leading-none"
                  >
                    &times;
                  </button>
                </span>
                <span
                  v-if="!form.category_ids.length"
                  class="text-xs text-gray-400 italic"
                  >No categories selected</span
                >
              </div>
              <!-- Checkbox list -->
              <div
                class="mt-2 max-h-36 overflow-y-auto border border-gray-300 rounded-md bg-gray-50 divide-y divide-gray-100"
              >
                <label
                  v-for="category in categories"
                  :key="category.id"
                  class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gold-50 transition-colors"
                  :class="{
                    'bg-gold-50': form.category_ids.includes(category.id),
                  }"
                >
                  <input
                    type="checkbox"
                    :value="category.id"
                    v-model="form.category_ids"
                    class="rounded border-gray-300 text-gold-500 shadow-sm focus:ring-gold-400"
                  />
                  <span class="text-sm text-gray-800">{{ category.name }}</span>
                  <span
                    v-if="form.category_ids.includes(category.id)"
                    class="ml-auto text-gold-500"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  </span>
                </label>
              </div>
              <InputError class="mt-2" :message="form.errors.category_ids" />
            </div>
          </div>

          <div>
            <InputLabel value="Name" class="text-gray-700" />
            <TextInput
              type="text"
              class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
              v-model="form.name"
              required
            />
            <InputError class="mt-2" :message="form.errors.name" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <InputLabel value="Model Number" class="text-gray-700" />
              <TextInput
                type="text"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.model_number"
              />
            </div>
            <div>
              <InputLabel
                value="Barcode (Leave empty to generate)"
                class="text-gray-700"
              />
              <TextInput
                type="text"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.barcode"
                placeholder="Auto-generate"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <InputLabel value="Currency" class="text-gray-700" />
              <select
                v-model="form.currency"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
              >
                <option value="MMK">MMK (Myanmar Kyat)</option>
                <option value="USD">USD (US Dollar)</option>
                <option value="THB">THB (Thai Baht)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <InputLabel
                :value="'Price (' + form.currency + ')'"
                class="text-gray-700"
              />
              <TextInput
                type="number"
                step="0.01"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.price"
                required
              />
            </div>
            <div>
              <InputLabel
                :value="'Cost Price (' + form.currency + ')'"
                class="text-gray-700"
              />
              <TextInput
                type="number"
                step="0.01"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.cost_price"
              />
            </div>
          </div>

          <!-- Web Price & Discount -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <InputLabel
                :value="'Web Price (' + form.currency + ')'"
                class="text-gray-700"
              />
              <TextInput
                type="number"
                step="0.01"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.web_price"
                placeholder="Optional website listing price"
              />
              <InputError class="mt-2" :message="form.errors.web_price" />
            </div>
            <div>
              <InputLabel value="Discount (%)" class="text-gray-700" />
              <TextInput
                type="number"
                step="0.01"
                min="0"
                max="100"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.discount"
                placeholder="e.g. 10 for 10%"
              />
              <InputError class="mt-2" :message="form.errors.discount" />
            </div>
          </div>

          <!-- Warranty Period + Type -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <InputLabel
                value="Warranty Period (Months)"
                class="text-gray-700"
              />
              <TextInput
                type="number"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900"
                v-model="form.warranty_period"
                required
              />
            </div>
            <div>
              <InputLabel value="Warranty Type" class="text-gray-700" />
              <select
                v-model="form.warranty_type"
                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
              >
                <option value="">— Select Type —</option>
                <option value="international_warranty">
                  International Warranty
                </option>
                <option value="shop_warranty">Shop Warranty</option>
              </select>
              <InputError class="mt-2" :message="form.errors.warranty_type" />
            </div>
          </div>

          <!-- Detailed Specifications Dropdown or Inputs -->
          <div class="border-t border-gray-200 pt-4 mt-4">
            <h3 class="text-md font-bold text-gray-900 mb-4">
              Detailed Specifications
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div>
                <InputLabel value="Dial Size" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="dial_size_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.dial_size"
                  placeholder="e.g. 40mm"
                />
                <datalist id="dial_size_options">
                  <option
                    v-for="opt in specOptions.dial_size"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Dial Color" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="dial_color_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.dial_color"
                  placeholder="e.g. Black, Blue"
                />
                <datalist id="dial_color_options">
                  <option
                    v-for="opt in specOptions.dial_color"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Strap Size" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="strap_size_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.strap_size"
                  placeholder="e.g. 20mm"
                />
                <datalist id="strap_size_options">
                  <option
                    v-for="opt in specOptions.strap_size"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Strap Color" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="strap_color_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.strap_color"
                  placeholder="e.g. Silver, Gold"
                />
                <datalist id="strap_color_options">
                  <option
                    v-for="opt in specOptions.strap_color"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel
                  value="Strap Material"
                  class="text-gray-700 text-xs"
                />
                <TextInput
                  type="text"
                  list="strap_material_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.strap_material"
                  placeholder="e.g. Steel, Rubber"
                />
                <datalist id="strap_material_options">
                  <option
                    v-for="opt in specOptions.strap_material"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Strap Style" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="strap_style_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.strap_style"
                  placeholder="e.g. Mesh, Link"
                />
                <datalist id="strap_style_options">
                  <option
                    v-for="opt in specOptions.strap_style"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Gender" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="gender_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.gender"
                  placeholder="e.g. Men, Women"
                />
                <datalist id="gender_options">
                  <option
                    v-for="opt in specOptions.gender"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Movement" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="movement_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.movement"
                  placeholder="e.g. Quartz, Automatic"
                />
                <datalist id="movement_options">
                  <option
                    v-for="opt in specOptions.movement"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel
                  value="Quick Release"
                  class="text-gray-700 text-xs"
                />
                <TextInput
                  type="text"
                  list="quick_release_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.quick_release"
                  placeholder="e.g. Yes, No"
                />
                <datalist id="quick_release_options">
                  <option
                    v-for="opt in specOptions.quick_release"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Clasp Type" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="clasp_type_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.clasp_type"
                  placeholder="e.g. Buckle, Folding"
                />
                <datalist id="clasp_type_options">
                  <option
                    v-for="opt in specOptions.clasp_type"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Origin" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="origin_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.origin"
                  placeholder="e.g. Swiss Made"
                />
                <datalist id="origin_options">
                  <option
                    v-for="opt in specOptions.origin"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Case Shape" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="case_shape_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.case_shape"
                  placeholder="e.g. Round, Square"
                />
                <datalist id="case_shape_options">
                  <option
                    v-for="opt in specOptions.case_shape"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel
                  value="Water Resistant"
                  class="text-gray-700 text-xs"
                />
                <TextInput
                  type="text"
                  list="water_resistant_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.water_resistant"
                  placeholder="e.g. 50m, 100m"
                />
                <datalist id="water_resistant_options">
                  <option
                    v-for="opt in specOptions.water_resistant"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel value="Crystal" class="text-gray-700 text-xs" />
                <TextInput
                  type="text"
                  list="crystal_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.crystal"
                  placeholder="e.g. Sapphire, Mineral"
                />
                <datalist id="crystal_options">
                  <option
                    v-for="opt in specOptions.crystal"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel
                  value="Caliber Code"
                  class="text-gray-700 text-xs"
                />
                <TextInput
                  type="text"
                  list="caliber_code_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.caliber_code"
                  placeholder="e.g. ETA 2824, NH35"
                />
                <datalist id="caliber_code_options">
                  <option
                    v-for="opt in specOptions.caliber_code"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
              <div>
                <InputLabel
                  value="Caseback Design"
                  class="text-gray-700 text-xs"
                />
                <TextInput
                  type="text"
                  list="caseback_design_options"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="form.caseback_design"
                  placeholder="e.g. Solid, Exhibition"
                />
                <datalist id="caseback_design_options">
                  <option
                    v-for="opt in specOptions.caseback_design"
                    :key="opt"
                    :value="opt"
                  ></option>
                </datalist>
              </div>
            </div>
          </div>

          <!-- Customer Group Discounts -->
          <div
            class="border-t border-gray-200 pt-4 mt-4"
            v-if="props.customer_groups.length > 0"
          >
            <h3 class="text-md font-bold text-gray-900 mb-4">
              Specific Discounts by Group (%)
            </h3>
            <p class="text-xs text-gray-500 mb-4">
              Leave empty to use the default group discount.
            </p>
            <div class="grid grid-cols-2 gap-4">
              <div
                v-for="(discount, index) in form.customer_group_discounts"
                :key="index"
              >
                <InputLabel
                  :value="getCustomerGroupName(discount.group_id)"
                  class="text-gray-700 text-sm"
                />
                <TextInput
                  type="number"
                  class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 text-sm"
                  v-model="discount.percentage"
                  :placeholder="
                    getGroupDefaultPercentage(discount.group_id) + '% (Default)'
                  "
                />
                <InputError
                  class="mt-2"
                  :message="
                    form.errors[
                      'customer_group_discounts.' + index + '.percentage'
                    ]
                  "
                />
              </div>
            </div>
          </div>

          <!-- Status Options -->
          <div class="border-t border-gray-200 pt-4 mt-4">
            <h3 class="text-md font-bold text-gray-900 mb-4">
              Product Status & Placement
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="flex items-center">
                <input
                  type="checkbox"
                  id="is_featured"
                  v-model="form.is_featured"
                  class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                />
                <label
                  for="is_featured"
                  class="ml-2 block text-sm text-gray-900"
                  >Featured Product</label
                >
              </div>
              <div class="flex items-center">
                <input
                  type="checkbox"
                  id="is_banner"
                  v-model="form.is_banner"
                  class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                />
                <label for="is_banner" class="ml-2 block text-sm text-gray-900"
                  >Show in Banner</label
                >
              </div>
              <div class="flex items-center">
                <input
                  type="checkbox"
                  id="is_limited_collection"
                  v-model="form.is_limited_collection"
                  class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                />
                <label
                  for="is_limited_collection"
                  class="ml-2 block text-sm text-gray-900"
                  >Limited Collection</label
                >
              </div>
              <div class="flex items-center">
                <input
                  type="checkbox"
                  id="is_latest"
                  v-model="form.is_latest"
                  class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                />
                <label for="is_latest" class="ml-2 block text-sm text-gray-900"
                  >Is Latest</label
                >
              </div>
              <div class="flex items-center">
                <input
                  type="checkbox"
                  id="is_active"
                  v-model="form.is_active"
                  class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                />
                <label for="is_active" class="ml-2 block text-sm text-gray-900"
                  >Is Active</label
                >
              </div>
              <div class="flex items-center">
                <input
                  type="checkbox"
                  id="is_public"
                  v-model="form.is_public"
                  class="rounded border-gray-300 text-gold-500 shadow-sm focus:border-gold-500 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                />
                <label for="is_public" class="ml-2 block text-sm text-gray-900"
                  >Is Public</label
                >
              </div>
            </div>
          </div>

          <div>
            <InputLabel value="Description" class="text-gray-700" />
            <textarea
              v-model="form.description"
              class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
              rows="3"
            ></textarea>
          </div>

          <div class="border-t border-gray-200 pt-4 mt-4">
            <h3 class="text-md font-bold text-gray-900 mb-2">Watch Photos</h3>

            <!-- Existing Gallery -->
            <div
              v-if="
                editingProduct &&
                editingProduct.images &&
                editingProduct.images.length > 0
              "
              class="flex gap-2 mb-4 overflow-x-auto pb-2"
            >
              <div
                v-for="(img, idx) in editingProduct.images"
                :key="idx"
                class="relative shrink-0"
              >
                <img
                  :src="$page.props.storage_url + '/' + img"
                  class="h-20 w-20 object-cover rounded border border-gray-300"
                />
                <button
                  @click.prevent="markImageForRemoval(img, idx)"
                  class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center hover:bg-red-700 text-xs shadow"
                >
                  &times;
                </button>
              </div>
            </div>

            <!-- Old Legacy Single Image -->
            <div v-if="editingProduct && editingProduct.image" class="mb-4">
              <p class="text-xs text-gray-400 mb-1">Legacy Main Image</p>
              <img
                :src="$page.props.storage_url + '/' + editingProduct.image"
                class="h-20 w-20 object-cover rounded border border-gray-300"
              />
            </div>

            <!-- Previews -->
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <InputLabel value="Preview Photo" class="text-gray-700" />
                <input
                  type="file"
                  @change="handlePreviewPhoto"
                  class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-dark-900 hover:file:bg-gold-600"
                  accept="image/*"
                  ref="previewPhotoInput"
                />
                <img v-if="previewPhotoUrl" :src="previewPhotoUrl" class="mt-2 h-20 w-20 object-cover rounded border border-gray-300" />
              </div>
              <div>
                <InputLabel value="Preview BG Photo" class="text-gray-700" />
                <input
                  type="file"
                  @change="handlePreviewBgPhoto"
                  class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-dark-900 hover:file:bg-gold-600"
                  accept="image/*"
                  ref="previewBgPhotoInput"
                />
                <img v-if="previewBgPhotoUrl" :src="previewBgPhotoUrl" class="mt-2 h-20 w-[120px] object-cover rounded border border-gray-300" />
              </div>
            </div>

            <div>
              <InputLabel
                value="Upload Multiple Images"
                class="text-gray-700"
              />
              <input
                type="file"
                @change="handleMultipleImages"
                class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-dark-900 hover:file:bg-gold-600"
                accept="image/*"
                multiple
                ref="imagesInput"
              />
              <p class="text-xs text-gray-400 mt-2">
                You can select multiple photos at once. They will be added to
                the gallery.
              </p>

              <!-- NEW PREVIEW -->
              <div
                v-if="previewImages.length > 0"
                class="mt-4 border-t border-gray-100 pt-3"
              >
                <p class="text-xs text-green-600 font-bold mb-2">
                  New Images to be added:
                </p>
                <div class="flex gap-2 overflow-x-auto pb-2">
                  <img
                    v-for="(preview, idx) in previewImages"
                    :key="'new-' + idx"
                    :src="preview"
                    class="h-16 w-16 object-cover rounded shadow-sm border border-green-300"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <SecondaryButton
              @click="closeModal"
              class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
            >
              Cancel
            </SecondaryButton>
            <PrimaryButton
              class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold"
              :class="{ 'opacity-25': form.processing || isUploading }"
              :disabled="form.processing || isUploading"
            >
              <span v-if="isUploading">Uploading...</span>
              <span v-else>{{ editingProduct ? "Update" : "Save" }}</span>
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AdminLayout>
</template>
