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
    collections: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const form = useForm({
    name: '',
    description: '',
});

const isModalOpen = ref(false);
const editingCollection = ref(null);

const openModal = (collection = null) => {
    editingCollection.value = collection;
    if (collection) {
        form.name = collection.name || '';
        form.description = collection.description || '';
    } else {
        form.reset();
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingCollection.value = null;
};

const submit = () => {
    if (editingCollection.value) {
        router.put(route('collections.update', editingCollection.value.id), form.data(), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('collections.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteCollection = (collection) => {
    if (confirm('Are you sure you want to delete this collection?')) {
        useForm({}).delete(route('collections.destroy', collection.id));
    }
};
</script>

<template>
    <Head title="Collections" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Collections</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Collections</h1>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                Add Collection
            </PrimaryButton>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Watches</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="collection in collections.data" :key="collection.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ collection.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">
                            {{ collection.description || '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gold-600 font-bold text-sm">
                            {{ collection.products_count || 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(collection)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteCollection(collection)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!collections?.data?.length">
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No collections found.</td>
                    </tr>
                </tbody>
            </table>

             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="collections.prev_page_url" :href="collections.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="collections.next_page_url" :href="collections.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ collections.from }}</span>
                                 to
                                 <span class="font-medium">{{ collections.to }}</span>
                                 of
                                 <span class="font-medium">{{ collections.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in collections.links" :key="k" 
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
                    {{ editingCollection ? 'Edit Collection' : 'Add New Collection' }}
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
                        <InputLabel for="description" value="Description" class="text-gray-700" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                            rows="3"
                        ></textarea>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50">Cancel</SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingCollection ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
