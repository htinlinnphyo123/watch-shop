<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

defineProps({ sources: { type: Object, default: () => ({}) } });
const emit = defineEmits(['selected']);
const open = ref(false);
const more = ref(false);
const saving = ref(false);
const errors = ref({});
const matches = ref([]);
const defaults = () => ({ name: '', phone: '', email: '', address: '', gender: '', source: '', source_details: '' });
const form = reactive(defaults());
const start = () => { Object.assign(form, defaults()); more.value = false; errors.value = {}; matches.value = []; open.value = true; };
const close = () => { if (!saving.value) { open.value = false; } };
const select = customer => { emit('selected', customer); close(); };
const save = async () => {
    if (saving.value) return;
    saving.value = true; errors.value = {}; matches.value = [];
    try {
        const data = more.value ? { ...form } : { name: form.name, phone: form.phone };
        const response = await axios.post(route('pos.customers.store'), data);
        emit('selected', response.data.customer);
        open.value = false;
    } catch (error) {
        if (error.response?.status === 409) {
            matches.value = error.response.data.matches;
        } else if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors).map(([key, messages]) => [key, messages[0]]));
        } else {
            errors.value.general = 'Could not save the customer. Please try again.';
        }
    } finally { saving.value = false; }
};
</script>

<template>
    <button type="button" @click="start" class="text-sm font-semibold text-gold-700 hover:underline">+ Add guest customer</button>
    <Modal :show="open" :closeable="!saving" @close="close">
        <form @submit.prevent="save" class="space-y-3 p-5">
            <h2 class="text-lg font-semibold">Add guest customer</h2>
            <p class="text-sm text-gray-500">Saved for this and future orders, and hidden from the regular customer list.</p>
            <div><label for="guest-name" class="block text-sm font-medium">Name *</label><input id="guest-name" v-model="form.name" required maxlength="255" autocomplete="name" class="mt-1 w-full rounded-md border-gray-300" /><InputError :message="errors.name" /></div>
            <div><label for="guest-phone" class="block text-sm font-medium">Phone *</label><input id="guest-phone" v-model="form.phone" type="tel" required maxlength="255" autocomplete="tel" class="mt-1 w-full rounded-md border-gray-300" /><InputError :message="errors.phone" /></div>
            <button type="button" role="switch" :aria-checked="more" aria-controls="guest-optional-fields" @click="more = !more" class="flex items-center gap-3 text-sm font-medium">
                <span class="flex h-6 w-11 rounded-full p-1 transition-colors" :class="more ? 'bg-gray-900' : 'bg-gray-300'"><span class="h-4 w-4 rounded-full bg-white transition-transform" :class="more ? 'translate-x-5' : ''"></span></span>
                More customer details (optional)
            </button>
            <div v-if="more" id="guest-optional-fields" class="grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:grid-cols-2">
                <div><label for="guest-email" class="block text-sm font-medium">Email</label><input id="guest-email" v-model="form.email" type="email" maxlength="255" class="mt-1 w-full rounded-md border-gray-300" /><InputError :message="errors.email" /></div>
                <div><label for="guest-gender" class="block text-sm font-medium">Gender</label><select id="guest-gender" v-model="form.gender" class="mt-1 w-full rounded-md border-gray-300"><option value="">Not specified</option><option value="man">Man</option><option value="woman">Woman</option><option value="other">Other</option></select><InputError :message="errors.gender" /></div>
                <div class="sm:col-span-2"><label for="guest-address" class="block text-sm font-medium">Address</label><input id="guest-address" v-model="form.address" maxlength="5000" class="mt-1 w-full rounded-md border-gray-300" /><InputError :message="errors.address" /></div>
                <div><label for="guest-source" class="block text-sm font-medium">Customer source</label><select id="guest-source" v-model="form.source" class="mt-1 w-full rounded-md border-gray-300"><option value="">Not specified</option><option v-for="(label, value) in sources" :key="value" :value="value">{{ label }}</option></select><InputError :message="errors.source" /></div>
                <div><label for="guest-source-details" class="block text-sm font-medium">Profile link / source details</label><input id="guest-source-details" v-model="form.source_details" maxlength="2048" class="mt-1 w-full rounded-md border-gray-300" /><InputError :message="errors.source_details" /></div>
            </div>
            <div v-if="matches.length" role="alert" class="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
                <p>A customer already has this phone or email. Confirm who this is before selecting them.</p>
                <div v-for="customer in matches" :key="customer.id">
                    <p class="font-semibold">{{ customer.name }} · {{ customer.phone || customer.email }} {{ customer.is_guest ? '(Guest)' : '' }}</p>
                    <p v-if="customer.deleted_at" class="text-amber-900">This customer is archived. Ask an administrator to review the record.</p>
                    <button v-else type="button" @click="select(customer)" class="font-semibold underline">Use this customer</button>
                </div>
            </div>
            <InputError :message="errors.general" />
            <div class="flex justify-end gap-3"><button type="button" :disabled="saving" @click="close" class="rounded-lg border px-4 py-2">Cancel</button><button type="submit" :disabled="saving" class="rounded-lg bg-gray-900 px-4 py-2 text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Save & select customer' }}</button></div>
        </form>
    </Modal>
</template>
