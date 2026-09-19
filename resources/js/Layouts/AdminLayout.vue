<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";

const props = defineProps({
  hideSidebar: { type: Boolean, default: false },
});

const showingNavigationDropdown = ref(false);
const sidebar = ref(null);
const menuButton = ref(null);
const closeButton = ref(null);
let desktopMedia;
const closeNavigation = () => { showingNavigationDropdown.value = false; };
const onBreakpointChange = () => { if (desktopMedia.matches) closeNavigation(); };
onMounted(() => {
  desktopMedia = window.matchMedia('(min-width: 768px)');
  desktopMedia.addEventListener('change', onBreakpointChange);
});
onBeforeUnmount(() => desktopMedia?.removeEventListener('change', onBreakpointChange));
watch(showingNavigationDropdown, async (open, previous, onCleanup) => {
  if (open) {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    onCleanup(() => { document.body.style.overflow = previousOverflow; });
    await nextTick();
    if (showingNavigationDropdown.value) closeButton.value?.focus();
  } else if (menuButton.value?.offsetParent) {
    menuButton.value.focus();
  }
}, { flush: 'post' });
const handleNavigationKey = (event) => {
  if (!showingNavigationDropdown.value) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    closeNavigation();
  } else if (event.key === 'Tab') {
    const controls = [...sidebar.value.querySelectorAll('a[href], button:not([disabled])')].filter(el => el.offsetParent !== null);
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first?.focus();
    }
  }
};
const page = usePage();
watch(() => page.url, closeNavigation);
watch(() => props.hideSidebar, closeNavigation);
const dismissedFlash = ref({ success: null, error: null });
watch(() => page.props.flash?.success, () => { dismissedFlash.value.success = null; });
watch(() => page.props.flash?.error, () => { dismissedFlash.value.error = null; });
</script>

<template>
  <div class="min-h-screen bg-gray-100 font-sans">
    <div v-if="!hideSidebar && showingNavigationDropdown" class="fixed inset-0 z-40 bg-black/50 md:hidden" aria-hidden="true" @click="closeNavigation" />
    <!-- Shared desktop sidebar and mobile navigation drawer -->
    <aside
      v-if="!hideSidebar"
      id="admin-navigation"
      ref="sidebar"
      :class="showingNavigationDropdown ? 'flex' : 'hidden md:flex'"
      :role="showingNavigationDropdown ? 'dialog' : undefined"
      :aria-modal="showingNavigationDropdown ? 'true' : undefined"
      aria-label="Main navigation"
      class="fixed inset-y-0 left-0 w-64 max-w-[85vw] bg-white border-r border-gray-200 flex-col z-50 md:z-20"
      @keydown="handleNavigationKey"
      @click="event => { if (event.target.closest('a[href]')) closeNavigation(); }"
    >
      <div
        class="flex items-center justify-center border-b border-gray-900 px-4 py-5 bg-black"
      >
        <Link :href="route('dashboard')">
          <img
            src="/assets/timeonyou.jpg"
            alt="Time On You"
            class="w-32 h-auto object-contain"
          />
        </Link>
        <button ref="closeButton" type="button" class="ml-3 rounded-lg p-2 text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gold-500 md:hidden" aria-label="Close navigation" @click="closeNavigation">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-width="2" stroke-linecap="round" /></svg>
        </button>
      </div>

      <nav class="min-h-0 flex-1 px-4 py-6 space-y-2 overflow-y-auto overscroll-contain">
        <Link
          :href="route('dashboard')"
          :class="{ 'text-gold-600 bg-gold-50': route().current('dashboard') }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
            ></path>
          </svg>
          Dashboard
        </Link>

        <div>
          <div
            class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 ml-4"
          >
            Inventory
          </div>

          <Link
            :href="route('categories.index')"
            :class="{
              'text-gold-600 bg-gold-50': route().current('categories.*'),
            }"
            class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
              ></path>
            </svg>
            Categories
          </Link>

          <Link
            :href="route('brands.index')"
            :class="{ 'text-gold-600 bg-gold-50': route().current('brands.*') }"
            class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            Brands
          </Link>

          <Link
            :href="route('collections.index')"
            :class="{
              'text-gold-600 bg-gold-50': route().current('collections.*'),
            }"
            class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
              ></path>
            </svg>
            Collections
          </Link>

          <Link
            :href="route('banners.index')"
            :class="{
              'text-gold-600 bg-gold-50': route().current('banners.*'),
            }"
            class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
              ></path>
            </svg>
            Banners
          </Link>

          <Link
            :href="route('articles.index')"
            :class="{
              'text-gold-600 bg-gold-50': route().current('articles.*'),
            }"
            class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h6"
              ></path>
            </svg>
            Articles
          </Link>
        </div>

        <div
          class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 ml-4"
        >
          Sales & Operations
        </div>

                <Link :href="route('products.index')"
                    :class="{ 'text-gold-600 bg-gold-50': route().current('products.*') }"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Watches
                </Link>

                <Link v-if="$page.props.auth.user.role === 'admin'" :href="route('accessories.index')"
                    :class="{ 'text-gold-600 bg-gold-50': route().current('accessories.*') }"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14v12H5V8zm-1-4h16v4H4V4zm6 8h4" /></svg>
                    Accessories
                </Link>

                <Link :href="route('pos.index')" :class="{ 'text-gold-600 bg-gold-50': route().current('pos.*') }"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    POS System
                </Link>

                <Link :href="route('pre-orders.index')"
                    :class="{ 'text-gold-600 bg-gold-50': route().current('pre-orders.*') }"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm4 10h6m-6 4h3" />
                    </svg>
                    Pre Orders
                </Link>

        <Link
          :href="route('orders.index')"
          :class="{
            'text-gold-600 bg-gold-50':
              route().current('orders.*') && !route().current('orders.summary'),
          }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            ></path>
          </svg>
          Orders & Invoices
        </Link>
        <Link
          :href="route('orders.summary')"
          :class="{
            'text-gold-600 bg-gold-50': route().current('orders.summary'),
          }"
          class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"
            />
          </svg>
          Order Summary
        </Link>
        <Link :href="route('sales.analytics')" :class="{ 'text-gold-600 bg-gold-50': route().current('sales.analytics') }" class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors">
          <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 4v16h16M8 16v-4m4 4V8m4 8V5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          Sales Analytics
        </Link>
        <Link v-if="$page.props.auth.user.role === 'admin'" :href="route('watch-services.index')" :class="{ 'text-gold-600 bg-gold-50': route().current('watch-services.*') }" class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors">
          <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3zm-4 9l3 3 5-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          Repairs &amp; Service
        </Link>

        <Link
          v-if="$page.props.auth.user.role === 'admin'"
          :href="route('low-stock-notifications.index')"
          :class="{
            'text-gold-600 bg-gold-50': route().current(
              'low-stock-notifications.*',
            ),
          }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"
            ></path>
          </svg>
          Low Stock
          <span
            v-if="$page.props.low_stock_pending_count"
            class="ml-auto min-w-6 h-6 px-1.5 rounded-full bg-red-100 text-red-700 text-xs font-bold flex items-center justify-center"
          >
            {{ $page.props.low_stock_pending_count }}
          </span>
        </Link>

        <Link
          :href="route('customers.index')"
          :class="{
            'text-gold-600 bg-gold-50': route().current('customers.*'),
          }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            ></path>
          </svg>
          Customers
        </Link>

        <Link
          :href="route('wallet.index')"
          :class="{ 'text-gold-600 bg-gold-50': route().current('wallet.*') }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0-6h4a2 2 0 012 2v2a2 2 0 01-2 2h-4a2 2 0 01-2-2v-2a2 2 0 012-2z"
            ></path>
          </svg>
          {{
            $page.props.auth.user.role === "admin"
              ? "User Wallets"
              : "My Wallet"
          }}
        </Link>

        <!-- HR Section — visible to Admin + Manager -->
        <div
          v-if="['admin', 'manager'].includes($page.props.auth.user.role)"
          class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 ml-4"
        >
          HR
        </div>

        <Link
          v-if="['admin', 'manager'].includes($page.props.auth.user.role)"
          :href="route('attendance.index')"
          :class="{ 'text-gold-600 bg-gold-50': route().current('attendance.*') }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          Attendance
        </Link>

        <div
          v-if="$page.props.auth.user.role === 'admin'"
          class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 ml-4"
        >
          System
        </div>

        <Link
          v-if="$page.props.auth.user.role === 'admin'"
          :href="route('users.index')"
          :class="{ 'text-gold-600 bg-gold-50': route().current('users.*') }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
            ></path>
          </svg>
          User Management (Roles)
        </Link>

        <Link
          v-if="$page.props.auth.user.role === 'admin'"
          :href="route('customer-groups.index')"
          :class="{
            'text-gold-600 bg-gold-50': route().current('customer-groups.*'),
          }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            ></path>
          </svg>
          Customer Groups
        </Link>

        <Link
          v-if="$page.props.auth.user.role === 'admin'"
          :href="route('top-level-discounts.index')"
          :class="{
            'text-gold-600 bg-gold-50': route().current(
              'top-level-discounts.*',
            ),
          }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            ></path>
          </svg>
          Top Level Discounts
        </Link>

        <Link
          v-if="$page.props.auth.user.role === 'admin'"
          :href="route('settings.index')"
          :class="{ 'text-gold-600 bg-gold-50': route().current('settings.*') }"
          class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-lg transition-colors"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
            ></path>
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            ></path>
          </svg>
          Settings
        </Link>
      </nav>

      <div class="p-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <Link
            :href="route('profile.edit')"
            class="flex items-center text-sm text-gray-500 hover:text-gray-900 group transition-colors"
          >
            <div
              class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 group-hover:ring-2 group-hover:ring-gold-500"
            >
              {{ $page.props.auth.user.name.charAt(0) }}
            </div>
            <div>
              <div class="font-medium text-gray-700">
                {{ $page.props.auth.user.name }}
              </div>
              <div class="text-[10px] uppercase text-gold-600 font-bold">
                {{ $page.props.auth.user.role }}
              </div>
            </div>
          </Link>

          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="text-gray-400 hover:text-red-500 transition-colors"
          >
            <svg
              class="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
              ></path>
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Main Content (With top bar for mobile) -->
    <main
      :inert="showingNavigationDropdown && !hideSidebar ? true : undefined"
      :class="{ 'md:ml-64': !hideSidebar }"
      class="min-h-screen transition-all duration-300"
    >
      <!-- Mobile Header -->
      <header
        v-if="!hideSidebar"
        class="md:hidden flex items-center justify-between p-2 bg-black border-b border-gray-800"
      >
        <Link :href="route('dashboard')">
          <img
            src="/assets/timeonyou.jpg"
            alt="Time On You"
            class="w-auto h-20"
          />
        </Link>
        <button
          ref="menuButton"
          type="button"
          aria-label="Open navigation"
          aria-controls="admin-navigation"
          :aria-expanded="showingNavigationDropdown"
          @click="showingNavigationDropdown = !showingNavigationDropdown"
          class="rounded-lg p-3 text-gray-200 hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-gold-500"
        >
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"
            ></path>
          </svg>
        </button>
      </header>

      <!-- Content -->
      <div class="p-6">
        <!-- Flash Messages -->
        <div
          v-if="$page.props.flash?.success && dismissedFlash.success !== $page.props.flash.success"
          class="mb-4 bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded flex items-start justify-between gap-4"
          role="alert"
        >
          <div>
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline ml-2">{{ $page.props.flash.success }}</span>
          </div>
          <button type="button" class="shrink-0 rounded p-1 text-green-800 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-600" aria-label="Dismiss success message" @click="dismissedFlash.success = $page.props.flash.success">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-width="2" stroke-linecap="round" /></svg>
          </button>
        </div>
        <div
          v-if="$page.props.flash?.error && dismissedFlash.error !== $page.props.flash.error"
          class="mb-4 bg-red-100 border border-red-200 text-red-800 px-4 py-3 rounded flex items-start justify-between gap-4"
          role="alert"
        >
          <div>
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline ml-2">{{ $page.props.flash.error }}</span>
          </div>
          <button type="button" class="shrink-0 rounded p-1 text-red-800 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-600" aria-label="Dismiss error message" @click="dismissedFlash.error = $page.props.flash.error">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-width="2" stroke-linecap="round" /></svg>
          </button>
        </div>

        <slot />
      </div>
    </main>
  </div>
</template>
