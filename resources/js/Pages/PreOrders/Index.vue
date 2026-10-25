<script setup>
import ReservationManager from '@/Components/ReservationManager.vue';
defineProps({ preOrders: Object, brands: Array, customers: Array, products: Array, availableItems: Array, filters: Object });
</script>

<template>
    <ReservationManager v-bind="$props" />
</template>
