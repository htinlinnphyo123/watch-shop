<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const settings = page.props.settings || {};

const form = useForm({
    usd_rate: settings.usd_rate || '4000',
    thb_rate: settings.thb_rate || '120',
    sgd_rate: settings.sgd_rate || '3000',
    cny_rate: settings.cny_rate || '550',
});

const submit = () => {
    form.put(route('settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="System Settings" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-2xl text-gray-900 leading-tight border-l-4 border-gold-500 pl-4 py-1">
                System Settings
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Exchange Rates Form -->
                <div class="bg-white p-4 sm:p-8 shadow sm:rounded-lg border border-gray-100">
                    <section class="max-w-xl">
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Exchange Rates</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Set the global exchange rates used to calculate MMK prices in the POS.
                            </p>
                        </header>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            
                            <div>
                                <InputLabel for="usd_rate" value="1 USD = (MMK)" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Ks</span>
                                    </div>
                                    <TextInput
                                        id="usd_rate"
                                        type="number"
                                        step="0.01"
                                        class="mt-1 block w-full pl-10 border-gray-300 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                                        v-model="form.usd_rate"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.usd_rate" />
                            </div>

                            <div>
                                <InputLabel for="thb_rate" value="1 THB = (MMK)" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Ks</span>
                                    </div>
                                    <TextInput
                                        id="thb_rate"
                                        type="number"
                                        step="0.01"
                                        class="mt-1 block w-full pl-10 border-gray-300 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                                        v-model="form.thb_rate"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.thb_rate" />
                            </div>

                            <div>
                                <InputLabel for="sgd_rate" value="1 SGD = (MMK)" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Ks</span>
                                    </div>
                                    <TextInput
                                        id="sgd_rate"
                                        type="number"
                                        step="0.01"
                                        class="mt-1 block w-full pl-10 border-gray-300 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                                        v-model="form.sgd_rate"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.sgd_rate" />
                            </div>

                            <div>
                                <InputLabel for="cny_rate" value="1 CNY = (MMK)" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Ks</span>
                                    </div>
                                    <TextInput
                                        id="cny_rate"
                                        type="number"
                                        step="0.01"
                                        class="mt-1 block w-full pl-10 border-gray-300 focus:border-gold-500 focus:ring-gold-500 rounded-md shadow-sm"
                                        v-model="form.cny_rate"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.cny_rate" />
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing" class="bg-gold-500 hover:bg-gold-600 text-dark-900 font-bold border-none shadow-sm">Save Rates</PrimaryButton>

                                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                    <p v-if="form.recentlySuccessful" class="text-sm text-green-600 font-medium">Saved.</p>
                                </Transition>
                            </div>
                        </form>
                    </section>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>
