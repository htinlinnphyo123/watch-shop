<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

import { router } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const props = defineProps({
    categories: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    all_categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    }
});

const form = useForm({
    name: '',
    parent_id: '',
    description: '',
    photo: null,
});

const isModalOpen = ref(false);
const editingCategory = ref(null);
const parentFilter = ref(props.filters.parent_id || 'all');

watch(parentFilter, debounce((value) => {
    router.get(route('categories.index'), { parent_id: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

const openModal = (category = null) => {
    editingCategory.value = category;
    if (category) {
        form.name = category.name;
        form.parent_id = category.parent_id || '';
        form.description = category.description || '';
        form.photo = null;
    } else {
        form.reset();
        form.photo = null;
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingCategory.value = null;
};

const submit = () => {
    if (editingCategory.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('categories.update', editingCategory.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('categories.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const handlePhotoUpload = (e) => {
    form.photo = e.target.files[0];
};

const deleteCategory = (category) => {
    if (confirm('Are you sure you want to delete this category?')) {
        useForm({}).delete(route('categories.destroy', category.id));
    }
};
</script>

<template>
    <Head title="Categories" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Categories</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Categories</h1>
            <div class="flex gap-4">
                <select
                    v-model="parentFilter"
                    class="block w-48 bg-white border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm text-sm font-medium"
                >
                    <option value="all">All Categories</option>
                    <option value="top_level">Parent / Top Level Only</option>
                    <option
                        v-for="cat in all_categories"
                        :key="cat.id"
                        :value="cat.id"
                    >
                        Subcategories of: {{ cat.name }}
                    </option>
                </select>

                <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                    Add Category
                </PrimaryButton>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent Category</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Watches</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ category.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img v-if="category.photo" :src="$page.props.storage_url + '/' + category.photo" class="h-10 w-10 object-cover rounded-full" />
                            <span v-else class="text-gray-400 text-sm">No photo</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">{{ category.parent?.name || '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ category.slug }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ category.description }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gold-600 font-bold text-sm">
                            {{ category.products_count || 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(category)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteCategory(category)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!categories?.data?.length">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No categories found.</td>
                    </tr>
                </tbody>
            </table>

             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="categories.prev_page_url" :href="categories.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="categories.next_page_url" :href="categories.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ categories.from }}</span>
                                 to
                                 <span class="font-medium">{{ categories.to }}</span>
                                 of
                                 <span class="font-medium">{{ categories.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in categories.links" :key="k" 
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
                    {{ editingCategory ? 'Edit Category' : 'Add New Category' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
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
                        <InputLabel value="Parent Category (Optional)" class="text-gray-700" />
                        <select
                            v-model="form.parent_id"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                        >
                            <option value="">None (Top Level)</option>
                            <option
                                v-for="cat in all_categories"
                                :key="cat.id"
                                :value="cat.id"
                                :disabled="editingCategory && editingCategory.id === cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.parent_id" />
                    </div>

                    <div>
                        <InputLabel for="photo" value="Category Photo" class="text-gray-700" />
                        <input
                            id="photo"
                            type="file"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gold-50 file:text-gold-700 hover:file:bg-gold-100"
                            @change="handlePhotoUpload"
                            accept="image/*"
                        />
                        <InputError class="mt-2" :message="form.errors.photo" />
                    </div>

                    <div>
                        <InputLabel value="Description" class="text-gray-700" />
                        <div class="mt-1 bg-white border border-gray-300 rounded-md">
                            <QuillEditor theme="snow" v-model:content="form.description" contentType="html" toolbar="minimal" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingCategory ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
