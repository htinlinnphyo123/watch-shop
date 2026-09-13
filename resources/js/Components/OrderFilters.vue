<script setup>
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { paymentMethods } from '@/utils/payments';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    routeName: { type: String, required: true },
});

const filterForm = useForm({
    payment_method: props.filters.payment_method || '',
    payment_type: props.filters.payment_type || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});
const applyFilters = () => filterForm.get(route(props.routeName), { preserveScroll: true });
const clearFilters = () => {
    filterForm.payment_method = '';
    filterForm.payment_type = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.clearErrors();
    applyFilters();
};
</script>

<template>
        <form @submit.prevent="applyFilters" class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="filter-method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                    <select id="filter-method" v-model="filterForm.payment_method" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">All methods</option>
                        <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                </div>
                <div>
                    <label for="filter-type" class="block text-sm font-medium text-gray-700">Payment Type</label>
                    <select id="filter-type" v-model="filterForm.payment_type" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">All payments</option>
                        <option value="single">Single payment</option>
                        <option value="split">Split payment</option>
                    </select>
                </div>
                <div>
                    <label for="filter-from" class="block text-sm font-medium text-gray-700">From Date</label>
                    <input id="filter-from" v-model="filterForm.date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label for="filter-to" class="block text-sm font-medium text-gray-700">To Date</label>
                    <input id="filter-to" v-model="filterForm.date_to" type="date" :min="filterForm.date_from || undefined" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
            </div>
            <p v-for="(message, key) in filterForm.errors" :key="key" role="alert" class="mt-2 text-sm text-red-600">{{ message }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <PrimaryButton :disabled="filterForm.processing">Apply Filters</PrimaryButton>
                <SecondaryButton :disabled="filterForm.processing" @click="clearFilters">Clear</SecondaryButton>
                <span class="text-xs text-gray-500">A method filter also includes split orders using that method.</span>
            </div>
        </form>

</template>
