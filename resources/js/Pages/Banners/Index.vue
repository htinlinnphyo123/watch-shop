<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    banners: Object,
});

const form = useForm({
    title: '',
    link: '',
    image: null,
    is_active: true,
    order: 0,
});

const isModalOpen = ref(false);
const editingBanner = ref(null);
const imageInput = ref(null);
const previewImage = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    form.image = file;
    
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

const openModal = (banner = null) => {
    editingBanner.value = banner;
    previewImage.value = null;
    if (banner) {
        form.title = banner.title;
        form.link = banner.link;
        form.is_active = Boolean(banner.is_active);
        form.order = banner.order;
        form.image = null; // Don't carry over file object
    } else {
        form.reset();
        form.is_active = true;
        form.order = 0;
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingBanner.value = null;
    previewImage.value = null;
    if (imageInput.value) imageInput.value.value = null;
};

const submit = () => {
    if (editingBanner.value) {
        router.post(route('banners.update', editingBanner.value.id), {
            _method: 'put',
            title: form.title,
            link: form.link,
            is_active: form.is_active ? 1 : 0,
            order: form.order,
            image: form.image,
        }, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('banners.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteBanner = (banner) => {
    if (confirm('Are you sure you want to delete this banner?')) {
        useForm({}).delete(route('banners.destroy', banner.id));
    }
};
</script>

<template>
    <Head title="Banners" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Banners</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Banners</h1>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                Add Banner
            </PrimaryButton>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="banner in banners.data" :key="banner.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img :src="$page.props.storage_url + '/' + banner.image" class="h-16 w-32 object-cover rounded border border-gray-200" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ banner.title || '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gold-600 text-sm">
                            <a v-if="banner.link" :href="banner.link" target="_blank" class="hover:underline truncate block max-w-xs">{{ banner.link }}</a>
                            <span v-else>-</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ banner.order }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="{'bg-green-100 text-green-800': banner.is_active, 'bg-gray-100 text-gray-800': !banner.is_active}" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full uppercase">
                                {{ banner.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(banner)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteBanner(banner)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="banners.data.length === 0">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No banners found.</td>
                    </tr>
                </tbody>
            </table>

             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="banners.prev_page_url" :href="banners.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="banners.next_page_url" :href="banners.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ banners.from }}</span>
                                 to
                                 <span class="font-medium">{{ banners.to }}</span>
                                 of
                                 <span class="font-medium">{{ banners.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in banners.links" :key="k" 
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
                    {{ editingBanner ? 'Edit Banner' : 'Add New Banner' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">
                    <div>
                        <InputLabel for="title" value="Title (Optional)" class="text-gray-700" />
                        <TextInput
                            id="title"
                            type="text"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.title"
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="link" value="Link URL (Optional)" class="text-gray-700" />
                        <TextInput
                            id="link"
                            type="text"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.link"
                            placeholder="e.g., /products/1"
                        />
                        <InputError class="mt-2" :message="form.errors.link" />
                    </div>

                     <div class="flex space-x-4">
                        <div class="flex-1">
                            <InputLabel for="order" value="Display Order" class="text-gray-700" />
                            <TextInput
                                id="order"
                                type="number"
                                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                                v-model="form.order"
                            />
                            <InputError class="mt-2" :message="form.errors.order" />
                        </div>
                        <div class="flex items-center pt-6">
                             <label class="flex items-center">
                                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-gold-600 shadow-sm focus:border-gold-300 focus:ring focus:ring-gold-200 focus:ring-opacity-50" />
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="image" value="Banner Image" class="text-gray-700" />
                        
                        <!-- Image Preview -->
                        <div v-if="previewImage" class="mt-2 mb-4">
                            <img :src="previewImage" class="h-32 w-full object-cover rounded border border-gray-300" />
                            <p class="text-xs text-green-600 mt-1">New image selected</p>
                        </div>
                        <div v-else-if="editingBanner && editingBanner.image" class="mt-2 mb-4">
                             <img :src="$page.props.storage_url + '/' + editingBanner.image" class="h-32 w-full object-cover rounded border border-gray-300" />
                             <p class="text-xs text-gray-500 mt-1">Current Image</p>
                        </div>

                        <input 
                            type="file" 
                            @change="onFileChange"
                            class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-dark-900 hover:file:bg-gold-600"
                            accept="image/*"
                            ref="imageInput"
                            :required="!editingBanner"
                        />
                        <InputError class="mt-2" :message="form.errors.image" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingBanner ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
