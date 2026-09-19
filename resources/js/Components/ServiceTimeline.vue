<script setup>
import { computed, ref } from 'vue';
import { serviceTimeline, chargeLabel } from '@/utils/serviceTimeline';
const props = defineProps({ events: Array, statuses: Object, decisions: Object, faults: Object, warranty: Boolean });
const expanded = ref(false);
const updates = computed(() => serviceTimeline(props.events || [], props.statuses, props.decisions, props.faults, props.warranty));
const visible = computed(() => expanded.value ? updates.value : updates.value.slice(0, 8));
const timestamp = value => new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
// Older expense entries retain their original audit text in storage.
const readableNote = value => (value || '').replace(/^(Expense #\d+) voided \(/, '$1 cancelled (');
</script>
<template>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div><h2 class="text-lg font-semibold text-gray-900">Service timeline</h2><p class="mt-1 text-sm text-gray-500">What happened, who updated it, and when.</p></div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Newest first</span>
        </div>
        <ol class="mt-6">
            <li v-for="(event, index) in visible" :key="event.id" class="relative ml-2 border-l border-gray-200 pb-7 pl-6 last:pb-0">
                <span class="absolute -left-1.5 top-1 h-3 w-3 rounded-full ring-4 ring-white" :class="index === 0 ? 'bg-gold-500' : 'bg-gray-300'"></span>
                <h3 class="font-semibold text-gray-900">{{ event.title }}</h3>
                <p class="mt-1 text-xs leading-5 text-gray-500"><time :datetime="event.created_at">{{ timestamp(event.created_at) }}</time> · {{ event.actor_name || 'Staff member' }}</p>
                <p v-if="event.notes" class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-gray-700">{{ readableNote(event.notes) }}</p>
                <dl v-if="event.changes.length" class="mt-3 space-y-3 rounded-xl bg-gray-50 p-4 text-sm">
                    <div v-for="change in event.changes" :key="change.label"><dt class="text-xs font-medium text-gray-500">{{ change.label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-800">{{ change.value }}</dd></div>
                </dl>
                <details v-if="event.repair_details" class="mt-3 text-sm text-gray-600">
                    <summary class="cursor-pointer rounded py-1 font-medium text-gold-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-gold-500">View details saved at this time</summary>
                    <dl class="mt-3 grid gap-4 rounded-xl border border-gray-100 p-4 sm:grid-cols-2">
                        <div><dt class="text-xs text-gray-500">Cause of the problem</dt><dd class="mt-1">{{ faults[event.repair_details.fault_type] || 'Not determined yet' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Customer charge</dt><dd class="mt-1">{{ chargeLabel(event.repair_details) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Repair location</dt><dd class="mt-1 break-words">{{ event.repair_details.repair_provider === 'external' ? event.repair_details.external_shop_name : 'Time On You' }}</dd><dd v-if="event.repair_details.repair_provider === 'external'" class="mt-1 break-words">{{ [event.repair_details.external_shop_phone, event.repair_details.external_reference].filter(Boolean).join(' · ') }}</dd></div>
                        <div v-if="warranty"><dt class="text-xs text-gray-500">Warranty decision</dt><dd class="mt-1">{{ decisions[event.coverage_decision] }}</dd><dd class="mt-1 whitespace-pre-wrap break-words">{{ event.decision_notes }}</dd></div>
                        <template v-for="[key, label] in [['diagnosis', 'Inspection findings'], ['provider_notes', 'Instructions for the shop'], ['charge_notes', 'Charge explanation']]" :key="key"><div v-if="event.repair_details[key] && (key !== 'provider_notes' || event.repair_details.repair_provider === 'external')" class="sm:col-span-2"><dt class="text-xs text-gray-500">{{ label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ event.repair_details[key] }}</dd></div></template>
                    </dl>
                </details>
            </li>
        </ol>
        <p v-if="!updates.length" class="py-8 text-center text-sm text-gray-500">Updates will appear here when work is recorded.</p>
        <button v-if="updates.length > 8" type="button" @click="expanded = !expanded" class="mt-6 w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ expanded ? 'Show recent updates only' : `Show all ${updates.length} updates` }}</button>
    </section>
</template>
