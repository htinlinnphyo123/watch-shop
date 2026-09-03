<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

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

const finalPrice = computed(()=>{
  let price = props.product.price;
  let discount = props.product.discount;
  if(props.product.discount){
    return price - (price * discount / 100);
  }
  return price;
})

const youtubeEmbedUrl = computed(() => {
    if (!props.product.youtube_link) return null;

    try {
        const url = new URL(props.product.youtube_link);
        const hostname = url.hostname.replace(/^www\./, '').toLowerCase();
        let videoId = null;

        if (hostname === 'youtu.be') {
            videoId = url.pathname.split('/').filter(Boolean)[0];
        } else if (['youtube.com', 'm.youtube.com'].includes(hostname)) {
            if (url.pathname === '/watch') {
                videoId = url.searchParams.get('v');
            } else {
                const parts = url.pathname.split('/').filter(Boolean);
                if (['shorts', 'embed', 'live'].includes(parts[0])) {
                    videoId = parts[1];
                }
            }
        }

        return /^[A-Za-z0-9_-]{11}$/.test(videoId || '')
            ? `https://www.youtube-nocookie.com/embed/${videoId}`
            : null;
    } catch {
        return null;
    }
});

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

                         <div v-if="youtubeEmbedUrl" class="w-full mb-4">
                             <iframe
                                 :src="youtubeEmbedUrl"
                                 class="w-full rounded shadow-sm border-0"
                                 style="aspect-ratio: 16 / 9"
                                 title="YouTube video player"
                                 loading="lazy"
                                 allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                 referrerpolicy="strict-origin-when-cross-origin"
                                 allowfullscreen
                             ></iframe>
                         </div>
                         
                         <h1 class="text-2xl font-bold text-gray-900">{{ product.name }}</h1>
                         <p class="text-gold-600 font-bold text-xl mt-2">Price - {{ parseInt(product.price).toLocaleString() }} {{ product.currency }}</p>                  
                         <div v-if="product.discount" class="mt-1 flex items-center gap-2">
                             <span class="text-gray-400 text-xs uppercase tracking-wider">Discount</span>
                             <span class="text-gray-600 font-semibold text-sm">{{ product.discount }}%</span>
                         </div>
                         <div v-if="product.discount" class="mt-1 flex items-center gap-2">
                            <span class="text-gray-400 text-xs uppercase tracking-wider">Final Price </span>
                            <p v-if="product.web_price">{{ parseInt(finalPrice).toLocaleString() }} {{ product.currency }}</p>
                         </div>
                         <div v-if="product.web_price" class="mt-1 flex items-center gap-2">
                             <span class="text-gray-400 text-xs uppercase tracking-wider">Web Price</span>
                             <span class="text-gray-600 font-semibold text-sm">{{ parseInt(product.web_price).toLocaleString() }} {{ product.currency }}</span>
                         </div>
                         <div v-if="product.web_price && product.discount" class="mt-1 flex items-center gap-2">
                             <span class="text-gray-400 text-xs uppercase tracking-wider">Final Discount %</span>
                             <span class="text-gray-600 font-semibold text-sm">{{ (100 - finalPrice / product.web_price * 100).toLocaleString() }} %</span>
                         </div>
                         
                         
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
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Priority Level</span>
                                 <span class="text-gray-900 font-medium">{{ product.priority_level ?? 0 }}</span>
                             </div>
                             <div class="flex justify-between border-b border-gray-100 pb-2">
                                 <span class="text-gray-500">Warranty Type</span>
                                 <span v-if="product.warranty_type === 'international_warranty'"
                                     class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                     <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                                     International
                                 </span>
                                 <span v-else-if="product.warranty_type === 'shop_warranty'"
                                     class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                     <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                     Shop Warranty
                                 </span>
                                 <span v-else class="text-gray-400 text-sm">-</span>
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
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Caliber Code</span><span class="text-gray-900 font-medium">{{ product.caliber_code || '-' }}</span></div>
                                 <div class="flex flex-col"><span class="text-gray-500 text-xs uppercase tracking-wider">Caseback Design</span><span class="text-gray-900 font-medium">{{ product.caseback_design || '-' }}</span></div>
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
                            <span class="text-gold-600 font-bold">{{ (items || []).filter(i => i.status === 'available').length }} Available</span>
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
</template>
