<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    isAdmin: Boolean,
    currentWallet: {
        type: Object,
        default: null,
    },
    wallets: {
        type: Object,
        default: null,
    },
    transactions: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    users: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({ out_total: 0 }),
    },
});

const selectedUserId = ref(props.filters.user_id || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

const form = useForm({
    user_id: props.isAdmin ? '' : props.currentWallet?.user_id,
    type: props.isAdmin ? 'credit' : 'debit',
    amount: '',
    description: '',
});
const editingTransaction = ref(null);

const resetForm = () => {
    editingTransaction.value = null;
    form.reset();
    form.user_id = props.isAdmin ? '' : props.currentWallet?.user_id;
    form.type = props.isAdmin ? 'credit' : 'debit';
    form.clearErrors();
};

const submitTransaction = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetForm,
    };

    if (editingTransaction.value) {
        form.put(route('wallet.transactions.update', editingTransaction.value.id), options);
        return;
    }

    form.post(route('wallet.transactions.store'), options);
};

const editTransaction = (transaction) => {
    editingTransaction.value = transaction;
    form.user_id = props.isAdmin
        ? transaction.wallet?.user_id || transaction.wallet?.user?.id
        : props.currentWallet?.user_id;
    form.type = transaction.type;
    form.amount = transaction.amount;
    form.description = transaction.description || '';
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const deleteTransaction = (transaction) => {
    if (!confirm('Delete this wallet record? The wallet balance will be recalculated.')) return;

    form.delete(route('wallet.transactions.destroy', transaction.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingTransaction.value?.id === transaction.id) resetForm();
        },
    });
};

const formatMoney = (value) => new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const formatDate = (value) => new Date(value).toLocaleString();

const transactionTypeLabel = (type) => type === 'credit' ? 'In' : 'Out';

const canManageTransaction = (transaction) => props.isAdmin || (
    transaction.type === 'debit'
    && Number(transaction.created_by) === Number(props.currentWallet?.user_id)
);

const applyFilters = () => {
    const params = {};

    if (props.isAdmin && selectedUserId.value) params.user_id = selectedUserId.value;
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    router.get(route('wallet.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const filterByUser = (userId) => {
    selectedUserId.value = userId;
    applyFilters();
};

const clearDateFilters = () => {
    startDate.value = '';
    endDate.value = '';
    applyFilters();
};
</script>

<template>
    <Head title="Wallets" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wallets</h2>
        </template>

        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">{{ isAdmin ? 'User Wallets' : 'My Wallet' }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ isAdmin ? 'Allocate budgets with In records and review spending with Out records.' : 'Use Out records for expenses and view your remaining budget.' }}
            </p>
        </div>

        <div v-if="!isAdmin" class="mb-6 rounded-xl bg-gray-900 text-white p-6 shadow-sm max-w-xl">
            <div class="text-sm uppercase tracking-wider text-gray-400">Available Balance</div>
            <div class="mt-2 text-4xl font-bold">{{ formatMoney(currentWallet?.balance) }} <span class="text-lg text-gold-500">{{ currentWallet?.currency }}</span></div>
        </div>

        <div :class="isAdmin ? 'grid lg:grid-cols-3 gap-6' : 'max-w-xl'" class="mb-8">
            <div v-if="isAdmin" class="lg:col-span-2 bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-900">All User Balances</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="wallet in wallets?.data || []" :key="wallet.id">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ wallet.user?.name }}</div>
                                <div class="text-xs text-gray-500">{{ wallet.user?.email }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm capitalize text-gray-600">{{ wallet.user?.role }}</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900">{{ formatMoney(wallet.balance) }} {{ wallet.currency }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="wallets?.links?.length > 3" class="px-4 py-3 border-t border-gray-200 flex flex-wrap gap-1">
                    <Link v-for="(link, index) in wallets.links" :key="index" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-gold-50 border-gold-500 text-gold-700' : 'bg-white border-gray-300 text-gray-600', !link.url ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50']" class="px-3 py-2 border text-sm" />
                </div>
            </div>

            <form @submit.prevent="submitTransaction" class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 space-y-4 h-fit">
                <h3 class="font-bold text-gray-900">{{ editingTransaction ? 'Edit Wallet Record' : 'Add Wallet Record' }}</h3>
                <div v-if="isAdmin">
                    <InputLabel value="User" />
                    <select v-model="form.user_id" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100" :disabled="!!editingTransaction" required>
                        <option value="" disabled>Select user</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.role }})</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.user_id" />
                </div>
                <div>
                    <InputLabel value="In / Out" />
                    <select v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300">
                        <option v-if="isAdmin" value="credit">In</option>
                        <option value="debit">Out</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ isAdmin ? 'In adds to the budget. Out deducts an expense.' : 'Out records an expense and deducts it from your available budget.' }}
                    </p>
                    <InputError class="mt-2" :message="form.errors.type" />
                </div>
                <div>
                    <InputLabel value="Amount (MMK)" />
                    <TextInput v-model="form.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>
                <div>
                    <InputLabel value="Description" />
                    <TextInput v-model="form.description" type="text" maxlength="255" class="mt-1 block w-full" placeholder="Reason or reference" />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
                <div class="flex gap-2">
                    <button v-if="editingTransaction" type="button" @click="resetForm" class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <PrimaryButton class="flex-1 justify-center bg-gold-500 hover:bg-gold-600 text-dark-900" :disabled="form.processing" :class="{ 'opacity-50': form.processing }">
                        {{ editingTransaction ? 'Update Record' : 'Save Record' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-bold text-gray-900">{{ isAdmin ? 'All Wallet Records' : 'My Wallet Records' }}</h3>
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div v-if="isAdmin">
                        <label for="wallet-user-filter" class="block text-xs font-medium text-gray-600">User</label>
                        <select
                            id="wallet-user-filter"
                            :value="selectedUserId"
                            @change="filterByUser($event.target.value)"
                            class="mt-1 rounded-md border-gray-300 text-sm focus:border-gold-500 focus:ring-gold-500"
                        >
                            <option value="">All users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }} — {{ user.email }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="wallet-start-date" class="block text-xs font-medium text-gray-600">Start Date</label>
                        <input
                            id="wallet-start-date"
                            v-model="startDate"
                            type="date"
                            :max="endDate || undefined"
                            class="mt-1 rounded-md border-gray-300 text-sm focus:border-gold-500 focus:ring-gold-500"
                        />
                    </div>
                    <div>
                        <label for="wallet-end-date" class="block text-xs font-medium text-gray-600">End Date</label>
                        <input
                            id="wallet-end-date"
                            v-model="endDate"
                            type="date"
                            :min="startDate || undefined"
                            class="mt-1 rounded-md border-gray-300 text-sm focus:border-gold-500 focus:ring-gold-500"
                        />
                    </div>
                    <button
                        type="button"
                        @click="applyFilters"
                        class="px-4 py-2 rounded-md bg-gray-900 text-sm font-medium text-white hover:bg-gray-800"
                    >
                        Apply
                    </button>
                    <button
                        v-if="startDate || endDate"
                        type="button"
                        @click="clearDateFilters"
                        class="px-4 py-2 rounded-md border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Clear Dates
                    </button>
                </div>
                <p v-if="$page.props.errors.start_date" class="mt-2 text-sm text-red-600">{{ $page.props.errors.start_date }}</p>
                <p v-if="$page.props.errors.end_date" class="mt-2 text-sm text-red-600">{{ $page.props.errors.end_date }}</p>
            </div>
            <div class="px-6 py-4 border-b border-red-100 bg-red-50 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-red-600">Total Out</div>
                    <div class="text-xs text-gray-500">
                        {{ filters.start_date || 'First record' }} to {{ filters.end_date || 'Today' }}
                    </div>
                </div>
                <div class="text-xl font-bold text-red-700">-{{ formatMoney(summary.out_total) }} MMK</div>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th v-if="isAdmin" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">In / Out</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance After</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50">
                        <td v-if="isAdmin" class="px-6 py-4 font-medium text-gray-900">{{ transaction.wallet?.user?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(transaction.created_at) }}</td>
                        <td class="px-6 py-4">
                            <span :class="transaction.type === 'credit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-1 rounded-full text-xs font-semibold">{{ transactionTypeLabel(transaction.type) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap font-semibold" :class="transaction.type === 'credit' ? 'text-green-600' : 'text-red-600'">
                            {{ transaction.type === 'credit' ? '+' : '-' }}{{ formatMoney(transaction.amount) }} MMK
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap text-gray-900">{{ formatMoney(transaction.balance_after) }} MMK</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ transaction.description || '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ transaction.created_by?.name || 'System' }}</td>
                        <td class="px-6 py-4 text-right whitespace-nowrap text-sm space-x-3">
                            <template v-if="canManageTransaction(transaction)">
                            <button type="button" @click="editTransaction(transaction)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button type="button" @click="deleteTransaction(transaction)" class="text-red-600 hover:text-red-800">Delete</button>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="!transactions.data?.length">
                        <td :colspan="isAdmin ? 8 : 7" class="px-6 py-10 text-center text-gray-500">No wallet records yet.</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="transactions.links?.length > 3" class="px-4 py-3 border-t border-gray-200 flex flex-wrap gap-1">
                <Link v-for="(link, index) in transactions.links" :key="index" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-gold-50 border-gold-500 text-gold-700' : 'bg-white border-gray-300 text-gray-600', !link.url ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50']" class="px-3 py-2 border text-sm" />
            </div>
        </div>
    </AdminLayout>
</template>
