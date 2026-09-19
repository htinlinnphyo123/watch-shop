<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CustomerInsights from '@/Components/CustomerInsights.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ report: { type: Object, default: null }, filters: Object, sourceOptions: Object });
</script>
<template>
    <Head title="Dashboard" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-gray-900 p-8 text-white">
                <div><h1 class="text-3xl font-bold">Welcome back, {{ $page.props.auth.user.name }}</h1><p class="mt-2 text-gray-300">{{ report ? 'Customer purchases and sales performance at a glance.' : 'Your shop workspace.' }}</p></div>
                <Link :href="route('pos.index')" class="rounded-lg bg-gold-500 px-5 py-3 font-semibold text-gray-900 hover:bg-gold-400">Open POS</Link>
            </div>
            <div class="flex flex-wrap gap-3 text-sm"><Link :href="route('orders.index')" class="rounded-lg border bg-white px-4 py-2">Orders</Link><Link :href="route('customers.index')" class="rounded-lg border bg-white px-4 py-2">Customers</Link><Link :href="route('products.index')" class="rounded-lg border bg-white px-4 py-2">Watches</Link><Link v-if="report" :href="route('customers.leaderboard')" class="rounded-lg border bg-white px-4 py-2">Customer leaderboard</Link></div>
            <CustomerInsights v-if="report" :report="report" :filters="filters" :source-options="sourceOptions" route-name="dashboard" />
        </div>
    </AdminLayout>
</template>
