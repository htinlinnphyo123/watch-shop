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
    discounts: {
        type: Array,
        default: () => []
    },
});

const form = useForm({
    amount: 0,
    percentage: 0,
});

const isModalOpen = ref(false);
const editingDiscount = ref(null);

const openModal = (discount = null) => {
    editingDiscount.value = discount;
    if (discount) {
        form.amount = discount.amount;
        form.percentage = discount.percentage;
    } else {
        form.reset();
        form.amount = 0;
        form.percentage = 0;
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingDiscount.value = null;
};

const submit = () => {
    if (editingDiscount.value) {
        form.put(route('top-level-discounts.update', editingDiscount.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('top-level-discounts.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteDiscount = (discount) => {
    if (confirm('Are you sure you want to delete this discount level?')) {
        useForm({}).delete(route('top-level-discounts.destroy', discount.id));
    }
};
</script>

<template>
    <Head title="Top Level Discounts" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Top Level Discounts</h2>
        </template>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Top Level Discounts</h1>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                Add Discount Level
            </PrimaryButton>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required Amount (Ks)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount Percentage</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="discount in discounts" :key="discount.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ parseFloat(discount.amount).toLocaleString() }} Ks</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gold-600 font-bold">{{ discount.percentage }}%</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(discount)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteDiscount(discount)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="discounts.length === 0">
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">No top level discounts arranged.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <Modal :show="isModalOpen" @close="closeModal">
            <div class="p-6 bg-white text-gray-900">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ editingDiscount ? 'Edit Top Level Discount' : 'Add New Top Level Discount' }}
                </h2>
                <p class="text-xs text-gray-500 mb-4">Set dynamic percentage discounts depending on total order amount (e.g. 1% if above 20,000,000 Ks).</p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel for="amount" value="Required Order Amount Threshold (e.g. 20000000)" class="text-gray-700" />
                        <TextInput
                            id="amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.amount"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.amount" />
                    </div>

                    <div>
                        <InputLabel for="percentage" value="Discount Percentage (%) (e.g. 1)" class="text-gray-700" />
                        <TextInput
                            id="percentage"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.percentage"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.percentage" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3 bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ editingDiscount ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
