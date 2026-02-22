<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-2xl text-gray-900 leading-tight border-l-4 border-gold-500 pl-4 py-1">
                Overview Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl shadow-lg p-8 text-white flex items-center justify-between border-l-4 border-gold-500">
                    <div>
                        <h3 class="text-3xl font-bold mb-2">Welcome Back, {{ $page.props.auth.user.name }}!</h3>
                        <p class="text-gray-400">Here's what's happening in your shop today.</p>
                    </div>
                    <div class="hidden md:block">
                        <Link :href="route('pos.index')" class="bg-gold-500 hover:bg-gold-400 text-dark-900 font-bold px-6 py-3 rounded-lg shadow-md transition-colors inline-flex items-center">
                            Open POS
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </Link>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Stat 1 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 text-transform uppercase">Today's Sales</p>
                                <h4 class="text-2xl font-bold text-gray-900 mt-2">-- Ks</h4>
                            </div>
                            <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center text-green-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4 text-sm">
                            <Link :href="route('orders.index')" class="text-gold-600 hover:text-gold-700 font-medium">View Orders &rarr;</Link>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 text-transform uppercase">Total Orders</p>
                                <h4 class="text-2xl font-bold text-gray-900 mt-2">--</h4>
                            </div>
                            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                        </div>
                         <div class="mt-4 text-sm text-gray-500">Lifetime orders</div>
                    </div>
                    
                    <!-- Stat 3 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 text-transform uppercase">Watch Models</p>
                                <h4 class="text-2xl font-bold text-gray-900 mt-2">--</h4>
                            </div>
                            <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center text-purple-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4 text-sm">
                            <Link :href="route('products.index')" class="text-gold-600 hover:text-gold-700 font-medium">Manage Inventory &rarr;</Link>
                        </div>
                    </div>

                    <!-- Stat 4 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 text-transform uppercase">Total Customers</p>
                                <h4 class="text-2xl font-bold text-gray-900 mt-2">--</h4>
                            </div>
                            <div class="w-10 h-10 bg-gold-50 rounded-lg flex items-center justify-center text-gold-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4 text-sm">
                            <Link :href="route('customers.index')" class="text-gold-600 hover:text-gold-700 font-medium">View Customers &rarr;</Link>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Sections -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-12">
                     <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                         <h3 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Recent Activity</h3>
                         <div class="text-gray-500 italic text-center py-6">
                             Activity logs will appear here soon.
                         </div>
                     </div>
                     <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                         <h3 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Quick Links</h3>
                         <div class="grid grid-cols-2 gap-4">
                             <Link :href="route('products.index')" class="p-3 bg-gray-50 rounded-lg text-center hover:bg-gold-50 transition-colors group">
                                 <span class="block text-gray-900 font-medium group-hover:text-gold-600 transition-colors">Watches</span>
                             </Link>
                             <Link :href="route('brands.index')" class="p-3 bg-gray-50 rounded-lg text-center hover:bg-gold-50 transition-colors group">
                                 <span class="block text-gray-900 font-medium group-hover:text-gold-600 transition-colors">Brands</span>
                             </Link>
                             <Link :href="route('orders.index')" class="p-3 bg-gray-50 rounded-lg text-center hover:bg-gold-50 transition-colors group">
                                 <span class="block text-gray-900 font-medium group-hover:text-gold-600 transition-colors">Orders</span>
                             </Link>
                             <Link :href="route('users.index')" class="p-3 bg-gray-50 rounded-lg text-center hover:bg-gold-50 transition-colors group">
                                 <span class="block text-gray-900 font-medium group-hover:text-gold-600 transition-colors">Users</span>
                             </Link>
                         </div>
                     </div>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>
