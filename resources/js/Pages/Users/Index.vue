<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    users: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const form = useForm({
    name: '',
    email: '',
    role: 'staff',
    password: '',
    password_confirmation: '',
});

const isModalOpen = ref(false);
const editingUser = ref(null);

const openModal = (user = null) => {
    editingUser.value = user;
    if (user) {
        form.name = user.name;
        form.email = user.email;
        form.role = user.role;
        form.password = '';
        form.password_confirmation = '';
    } else {
        form.reset();
        form.role = 'staff'; // Default
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingUser.value = null;
};

const submit = () => {
    if (editingUser.value) {
        form.put(route('users.update', editingUser.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('users.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteUser = (user) => {
    if (confirm('Are you sure you want to delete this user?')) {
        useForm({}).delete(route('users.destroy', user.id));
    }
};
</script>

<template>
    <Head title="User Management" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">User Management</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                Add User
            </PrimaryButton>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="user in users?.data || []" :key="user.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ user.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ user.email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="{'bg-gold-100 text-gold-800': user.role === 'admin', 'bg-gray-100 text-gray-800': user.role === 'staff'}" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full uppercase">
                                {{ user.role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(user)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteUser(user)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!users?.data?.length">
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                    </tr>
                </tbody>
            </table>

             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="users.next_page_url" :href="users.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ users.from }}</span>
                                 to
                                 <span class="font-medium">{{ users.to }}</span>
                                 of
                                 <span class="font-medium">{{ users.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in users.links" :key="k" 
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
                    {{ editingUser ? 'Edit User' : 'Add New User' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel value="Name" class="text-gray-700" />
                        <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 font-medium focus:border-gold-500 focus:ring-gold-500" v-model="form.name" required autofocus />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel value="Email" class="text-gray-700" />
                        <TextInput type="email" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 font-medium focus:border-gold-500 focus:ring-gold-500" v-model="form.email" required />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel value="Role" class="text-gray-700" />
                        <select v-model="form.role" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>

                    <div>
                        <InputLabel :value="editingUser ? 'Password (Leave blank to keep current)' : 'Password'" class="text-gray-700" />
                        <TextInput type="password" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 font-medium focus:border-gold-500 focus:ring-gold-500" v-model="form.password" :required="!editingUser" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel value="Confirm Password" class="text-gray-700" />
                        <TextInput type="password" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 font-medium focus:border-gold-500 focus:ring-gold-500" v-model="form.password_confirmation" :required="!editingUser" />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingUser ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
