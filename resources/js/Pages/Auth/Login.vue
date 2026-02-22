<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Admin Login" />

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-dark-900 text-gold-500">
        <div class="mb-8 text-center flex flex-col items-center">
             <div class="w-16 h-16 bg-gold-500 rounded-full flex items-center justify-center mb-4 shadow-lg shadow-gold-500/20">
                 <svg class="w-8 h-8 text-dark-900" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
             </div>
             <h1 class="text-3xl font-bold tracking-widest uppercase">Watch Shop Admin</h1>
             <p class="text-sm text-gray-400 mt-2">Sign in to manage inventory and sales</p>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-gray-900 shadow-2xl overflow-hidden sm:rounded-xl border border-gray-800">
            <div v-if="status" class="mb-4 font-medium text-sm text-green-400">
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email Address" class="text-gray-300" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full bg-dark-800 border-gray-700 text-white focus:border-gold-500 focus:ring-gold-500"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="admin@example.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-6">
                    <InputLabel for="password" value="Password" class="text-gray-300" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full bg-dark-800 border-gray-700 text-white focus:border-gold-500 focus:ring-gold-500"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="block mt-6">
                    <label class="flex items-center">
                        <Checkbox name="remember" v-model:checked="form.remember" class="border-gray-700 bg-dark-800 text-gold-500 focus:ring-gold-500 focus:ring-offset-dark-900" />
                        <span class="ms-2 text-sm text-gray-400">Keep me logged in</span>
                    </label>
                </div>

                <div class="flex items-center justify-between mt-8">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-gray-500 hover:text-gold-500 transition-colors focus:outline-none"
                    >
                        Forgot password?
                    </Link>

                    <PrimaryButton class="bg-gold-500 hover:bg-gold-400 text-dark-900 font-bold px-8 py-3 rounded-md transition-all uppercase tracking-wider text-sm" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Log In
                    </PrimaryButton>
                </div>
            </form>
        </div>
        
        <div class="mt-12 text-sm text-gray-600 text-center">
            &copy; {{ new Date().getFullYear() }} Watch Shop Admin Portal. All rights reserved.
        </div>
    </div>
</template>
