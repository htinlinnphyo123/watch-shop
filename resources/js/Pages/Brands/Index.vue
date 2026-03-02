<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    brands: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const form = useForm({
    name: '',
    website: '',
    logo: null,
    bg_logo: null,
});

const isModalOpen = ref(false);
const editingBrand = ref(null);
const logoInput = ref(null);
const bgLogoInput = ref(null);
const previewImage = ref(null);
const previewBgImage = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    form.logo = file;
    
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        previewImage.value = null;
    }
};

const onBgFileChange = (e) => {
    const file = e.target.files[0];
    form.bg_logo = file;
    
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewBgImage.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        previewBgImage.value = null;
    }
};

const openModal = (brand = null) => {
    editingBrand.value = brand;
    previewImage.value = null;
    previewBgImage.value = null;
    if (brand) {
        form.name = brand.name || '';
        form.website = brand.website || '';
        form.logo = null; // Don't carry over file object
        form.bg_logo = null;
    } else {
        form.reset();
        form.website = '';
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingBrand.value = null;
    previewImage.value = null;
    previewBgImage.value = null;
    if (logoInput.value) logoInput.value.value = null;
    if (bgLogoInput.value) bgLogoInput.value.value = null;
};

const submit = () => {
    if (editingBrand.value) {
        router.post(route('brands.update', editingBrand.value.id), {
            _method: 'put',
            name: form.name,
            website: form.website,
            logo: form.logo,
            bg_logo: form.bg_logo,
        }, {
            forceFormData: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('brands.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteBrand = (brand) => {
    if (confirm('Are you sure you want to delete this brand?')) {
        useForm({}).delete(route('brands.destroy', brand.id));
    }
};
</script>

<template>
    <Head title="Brands" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Brands</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Brands</h1>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                Add Brand
            </PrimaryButton>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Background Logo</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Website</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="brand in brands.data" :key="brand.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img v-if="brand.logo" :src="$page.props.storage_url + '/' + brand.logo" class="h-10 w-10 rounded-full object-cover border border-gray-200" />
                            <div v-else class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-xs">No Img</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img v-if="brand.bg_logo" :src="$page.props.storage_url + '/' + brand.bg_logo" class="h-10 w-24 rounded object-cover border border-gray-200" />
                            <div v-else class="h-10 w-16 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs">None</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ brand.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gold-600 text-sm">
                            <a v-if="brand.website" :href="brand.website" target="_blank" class="hover:underline">{{ brand.website }}</a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(brand)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteBrand(brand)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!brands?.data?.length">
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No brands found.</td>
                    </tr>
                </tbody>
            </table>

             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="brands.prev_page_url" :href="brands.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="brands.next_page_url" :href="brands.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ brands.from }}</span>
                                 to
                                 <span class="font-medium">{{ brands.to }}</span>
                                 of
                                 <span class="font-medium">{{ brands.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in brands.links" :key="k" 
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
            <div class="p-6 bg-white text-gray-900">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ editingBrand ? 'Edit Brand' : 'Add New Brand' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">
                    <div>
                        <InputLabel for="name" value="Name" class="text-gray-700" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.name"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="website" value="Website" class="text-gray-700" />
                        <TextInput
                            id="website"
                            type="url"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.website"
                        />
                        <InputError class="mt-2" :message="form.errors.website" />
                    </div>

                    <div>
                        <InputLabel for="logo" value="Logo" class="text-gray-700" />
                        
                        <!-- Image Preview -->
                        <div v-if="previewImage" class="mt-2 mb-4">
                            <img :src="previewImage" class="h-20 w-20 rounded-full object-cover border border-gray-300" />
                            <p class="text-xs text-green-600 mt-1">New image selected</p>
                        </div>
                        <div v-else-if="editingBrand && editingBrand.logo" class="mt-2 mb-4">
                             <img :src="$page.props.storage_url + '/' + editingBrand.logo" class="h-20 w-20 rounded-full object-cover border border-gray-300" />
                             <p class="text-xs text-gray-500 mt-1">Current Logo</p>
                        </div>

                        <input 
                            type="file" 
                            @change="onFileChange"
                            class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-dark-900 hover:file:bg-gold-600"
                            accept="image/*"
                            ref="logoInput"
                        />
                        <InputError class="mt-2" :message="form.errors.logo" />
                    </div>

                    <div>
                        <InputLabel for="bg_logo" value="Background Logo (Frontend)" class="text-gray-700" />
                        
                        <!-- Image Preview -->
                        <div v-if="previewBgImage" class="mt-2 mb-4">
                            <img :src="previewBgImage" class="h-20 w-32 rounded object-cover border border-gray-300" />
                            <p class="text-xs text-green-600 mt-1">New background image selected</p>
                        </div>
                        <div v-else-if="editingBrand && editingBrand.bg_logo" class="mt-2 mb-4">
                             <img :src="$page.props.storage_url + '/' + editingBrand.bg_logo" class="h-20 w-32 rounded object-cover border border-gray-300" />
                             <p class="text-xs text-gray-500 mt-1">Current Background Logo</p>
                        </div>

                        <input 
                            type="file" 
                            @change="onBgFileChange"
                            class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                            accept="image/*"
                            ref="bgLogoInput"
                        />
                        <InputError class="mt-2" :message="form.errors.bg_logo" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingBrand ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
