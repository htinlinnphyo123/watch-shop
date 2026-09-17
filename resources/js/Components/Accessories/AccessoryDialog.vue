<script setup>
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
defineProps({ show: Boolean, title: String, description: String, busy: Boolean, wide: Boolean });
const emit = defineEmits(['close']);
</script>
<template>
    <Dialog :open="show" @close="!busy && emit('close')" class="relative z-50">
        <div class="fixed inset-0 bg-gray-950/50 backdrop-blur-sm" aria-hidden="true" />
        <div class="fixed inset-0 overflow-y-auto p-3 sm:p-8">
            <div class="flex min-h-full items-center justify-center">
                <DialogPanel class="w-full rounded-2xl bg-white shadow-2xl overflow-hidden" :class="wide ? 'max-w-4xl' : 'max-w-lg'">
                    <header class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 sm:px-7">
                        <div><DialogTitle class="text-xl font-semibold text-gray-900">{{ title }}</DialogTitle><p v-if="description" class="mt-1 text-sm text-gray-500">{{ description }}</p></div>
                        <button type="button" :disabled="busy" @click="emit('close')" aria-label="Close dialog" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-gold-500"><XMarkIcon class="h-5 w-5" /></button>
                    </header>
                    <slot />
                </DialogPanel>
            </div>
        </div>
    </Dialog>
</template>
