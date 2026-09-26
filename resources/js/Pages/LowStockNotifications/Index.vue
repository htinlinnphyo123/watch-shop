<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    notifications: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    statusCounts: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const statuses = ['pending', 'processing', 'completed'];
const page = usePage();
const canEdit = computed(() => page.props.auth.user.role === 'admin');
const editingId = ref(null);
const orderForm = useForm({ ordered_count: 0 });
const editOrderedCount = (notification) => {
    editingId.value = notification.id;
    orderForm.ordered_count = notification.ordered_count;
    orderForm.clearErrors();
};
const saveOrderedCount = () => orderForm.patch(route('low-stock-notifications.update', editingId.value), {
    preserveScroll: true,
    onSuccess: () => editingId.value = null,
});

const filterByStatus = (status) => {
    router.get(route('low-stock-notifications.index'), status ? { status } : {}, {
        preserveState: true,
        replace: true,
    });
};

const updateStatus = (notification, status) => {
    router.patch(route('low-stock-notifications.update', notification.id), { status }, {
        preserveScroll: true,
    });
};

const statusClass = (status) => ({
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
}[status] || 'bg-gray-100 text-gray-800');

const priorityClass = (priority) => priority === 3
    ? 'bg-red-100 text-red-800'
    : 'bg-orange-100 text-orange-800';

const formatDate = (value) => new Date(value).toLocaleString();
</script>

<template>
    <Head title="Low Stock Notifications" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Low Stock Notifications</h2>
        </template>

        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Low Stock Notifications</h1>
            <p class="mt-1 text-sm text-gray-500">Priority 2 and 3 watches appear when available stock falls below their alert threshold. Admins can record the quantity ordered from the company after contacting them.</p>
        </div>

        <div class="flex flex-wrap gap-2 mb-5">
            <button
                type="button"
                @click="filterByStatus('')"
                :class="!filters.status ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 border border-gray-300'"
                class="px-4 py-2 rounded-md text-sm font-medium"
            >
                All
            </button>
            <button
                v-for="status in statuses"
                :key="status"
                type="button"
                @click="filterByStatus(status)"
                :class="filters.status === status ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 border border-gray-300'"
                class="px-4 py-2 rounded-md text-sm font-medium capitalize"
            >
                {{ status }} ({{ statusCounts[status] || 0 }})
            </button>
        </div>

        <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Watch</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordered Count</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="notification in notifications.data" :key="notification.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <Link v-if="notification.product && $page.props.auth.user.role !== 'staff'" :href="route('products.show', notification.product.id)" class="font-medium text-gray-900 hover:text-gold-600">
                                {{ notification.product.name }}
                            </Link>
                            <span v-else class="font-medium text-gray-900">{{ notification.product?.name || 'Deleted watch' }}</span>
                            <div class="text-xs text-gray-500">{{ notification.product?.brand?.name || 'No brand' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="priorityClass(notification.priority_level)" class="px-2 py-1 rounded-full text-xs font-semibold">
                                {{ notification.priority_level }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold" :class="notification.stock_quantity === 0 ? 'text-red-600' : 'text-orange-600'">
                            {{ notification.stock_quantity }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <form v-if="canEdit && editingId === notification.id" @submit.prevent="saveOrderedCount" class="space-y-2">
                                <input v-model="orderForm.ordered_count" type="number" min="0" max="2147483647" step="1" required aria-label="Ordered count" class="w-32 rounded-md border-gray-300 text-sm" />
                                <InputError :message="orderForm.errors.ordered_count" />
                                <div class="flex gap-3"><button :disabled="orderForm.processing" class="text-gold-700 disabled:opacity-50">Save</button><button type="button" :disabled="orderForm.processing" @click="editingId = null" class="text-gray-500">Cancel</button></div>
                            </form>
                            <div v-else class="flex items-center gap-3"><span>{{ notification.ordered_count }}</span><button v-if="canEdit" type="button" :disabled="orderForm.processing" @click="editOrderedCount(notification)" class="text-gold-700">Edit</button></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(notification.created_at) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <span :class="statusClass(notification.status)" class="px-2 py-1 rounded-full text-xs font-semibold capitalize">{{ notification.status }}</span>
                                <select
                                    v-if="canEdit"
                                    :value="notification.status"
                                    @change="updateStatus(notification, $event.target.value)"
                                    class="text-sm rounded-md border-gray-300 focus:border-gold-500 focus:ring-gold-500"
                                >
                                    <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ status }}</option>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!notifications.data?.length">
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">No low-stock notifications found.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="notifications.links?.length > 3" class="px-4 py-3 border-t border-gray-200 flex flex-wrap gap-1">
                <Link
                    v-for="(link, index) in notifications.links"
                    :key="index"
                    :href="link.url || '#'"
                    v-html="link.label"
                    :class="[
                        link.active ? 'bg-gold-50 border-gold-500 text-gold-700' : 'bg-white border-gray-300 text-gray-600',
                        !link.url ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50',
                    ]"
                    class="px-3 py-2 border text-sm"
                />
            </div>
        </div>
    </AdminLayout>
</template>
