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
    customers: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    groups: {
        type: Array,
        default: () => [],
    },
    sourceOptions: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    name: '',
    phone: '',
    email: '',
    password: '',
    address: '',
    customer_group_id: null,
    source: '',
    source_details: '',
});

const profileUrl = (value) => {
    if (!value) return null;
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
};

const isModalOpen = ref(false);
const editingCustomer = ref(null);

const openModal = (customer = null) => {
    form.clearErrors();
    editingCustomer.value = customer;
    if (customer) {
        form.name = customer.name;
        form.phone = customer.phone;
        form.email = customer.email;
        form.password = '';
        form.address = customer.address;
        form.customer_group_id = customer.customer_group_id;
        form.source = customer.source || '';
        form.source_details = customer.source_details || '';
    } else {
        form.reset();
        form.password = '';
        form.customer_group_id = null;
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingCustomer.value = null;
};

const submit = () => {
    if (editingCustomer.value) {
        form.put(route('customers.update', editingCustomer.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('customers.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteCustomer = (customer) => {
    if (confirm('Are you sure you want to delete this customer?')) {
        useForm({}).delete(route('customers.destroy', customer.id));
    }
};
</script>

<template>
    <Head title="Customers" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customers</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Customers</h1>
            <Link v-if="$page.props.auth.user.role === 'admin'" :href="route('customers.leaderboard')" class="ml-auto mr-3 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700">Leaderboard &amp; sources</Link>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                Add Customer
            </PrimaryButton>
        </div>

        <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer source</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ customer.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gold-600 text-sm font-mono">{{ customer.phone }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ customer.email || '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-sm">{{ customer.address || '-' }}</td>
                        <td class="px-6 py-4 text-sm min-w-[200px] max-w-xs">
                            <p class="font-medium text-gray-800">{{ sourceOptions[customer.source] || 'Not specified' }}</p>
                            <a v-if="profileUrl(customer.source_details)" :href="profileUrl(customer.source_details)" target="_blank" rel="noopener noreferrer" class="mt-1 block break-words text-gold-600 underline hover:text-gold-800">{{ customer.source_details }}</a>
                            <p v-else-if="customer.source_details" class="mt-1 break-words text-gray-500">{{ customer.source_details }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button v-if="$page.props.auth.user.role !== 'staff'" @click="openModal(customer)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button v-if="$page.props.auth.user.role !== 'staff'" @click="deleteCustomer(customer)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!customers?.data?.length">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No customers found.</td>
                    </tr>
                </tbody>
            </table>

             <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                 <div class="flex items-center justify-between">
                     <div class="flex-1 flex justify-between sm:hidden">
                         <Link v-if="customers.prev_page_url" :href="customers.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Previous </Link>
                         <Link v-if="customers.next_page_url" :href="customers.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"> Next </Link>
                     </div>
                     <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                         <div>
                             <p class="text-sm text-gray-700">
                                 Showing
                                 <span class="font-medium">{{ customers.from }}</span>
                                 to
                                 <span class="font-medium">{{ customers.to }}</span>
                                 of
                                 <span class="font-medium">{{ customers.total }}</span>
                                 results
                             </p>
                         </div>
                         <div>
                             <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                 <Link v-for="(link, k) in customers.links" :key="k" 
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
                    {{ editingCustomer ? 'Edit Customer' : 'Add New Customer' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel value="Name" class="text-gray-700" />
                        <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.name" required autofocus />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel value="Phone" class="text-gray-700" />
                        <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.phone" required />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div>
                        <InputLabel value="Email" class="text-gray-700" />
                        <TextInput type="email" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.email" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel value="Password (leave blank to keep current)" class="text-gray-700" v-if="editingCustomer" />
                        <InputLabel value="Password" class="text-gray-700" v-else />
                        <TextInput type="password" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.password" :required="!editingCustomer" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="customer_group_id" value="Group" class="text-gray-700" />
                         <select
                            id="customer_group_id"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                            v-model="form.customer_group_id"
                        >
                            <option :value="null">None</option>
                            <option v-for="group in groups" :key="group.id" :value="group.id">
                                {{ group.name }} ({{ group.percentage }}%)
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.customer_group_id" />
                    </div>

                    <div>
                        <InputLabel value="Address (optional)" class="text-gray-700" />
                        <TextInput type="text" class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900" v-model="form.address" />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <fieldset class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-4">
                        <legend class="px-1 text-sm font-semibold text-gray-800">How did this customer find us?</legend>
                        <div>
                            <InputLabel for="customer_source" value="Customer source (optional)" />
                            <select id="customer_source" v-model="form.source" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-gold-500 focus:ring-gold-500">
                                <option value="">Not specified</option>
                                <option v-for="(label, value) in sourceOptions" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.source" />
                        </div>
                        <div>
                            <InputLabel for="customer_source_details" :value="form.source === 'referral' ? 'Referrer name or profile link (optional)' : 'Profile link, name, or source details (optional)'" />
                            <TextInput id="customer_source_details" type="text" v-model="form.source_details" maxlength="2048" class="mt-1 block w-full border-gray-300" :placeholder="form.source === 'referral' ? 'e.g. Referred by Aung Aung' : 'e.g. https://www.facebook.com/username'" aria-describedby="customer_source_help" />
                            <p id="customer_source_help" class="mt-2 text-xs text-gray-500">Add the customer's profile link, the person who referred them, or details about another source.</p>
                            <InputError class="mt-2" :message="form.errors.source_details" />
                        </div>
                    </fieldset>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingCustomer ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
