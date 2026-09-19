<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CustomerInsights from '@/Components/CustomerInsights.vue';
defineProps({ report: Object, filters: Object, sourceOptions: Object });
</script>
<template>
    <Head title="Customer Leaderboard" />
    <AdminLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-3xl font-bold text-gray-900">Customer leaderboard</h1><p class="mt-2 text-gray-500">Discover your highest-spending customers and strongest sales sources.</p></div><Link :href="route('customers.index')" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium">Manage customers</Link></div>
        <CustomerInsights :report="report" :filters="filters" :source-options="sourceOptions" route-name="customers.leaderboard" />
    </AdminLayout>
</template>
