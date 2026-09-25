<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options:    { type: Array,  required: true },   // [{ value, label }]
    placeholder:{ type: String, default: '— Select —' },
    id:         { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

// ── State ──────────────────────────────────────────────────────────────────────
const isOpen    = ref(false);
const query     = ref('');
const searchRef = ref(null);
const rootRef   = ref(null);
const highlighted = ref(-1);

// ── Selected label ─────────────────────────────────────────────────────────────
const selectedLabel = computed(() => {
    if (!props.modelValue && props.modelValue !== 0) return '';
    const found = props.options.find(o => String(o.value) === String(props.modelValue));
    return found ? found.label : '';
});

// ── Filtered list ──────────────────────────────────────────────────────────────
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(o => o.label.toLowerCase().includes(q));
});

// ── Open / close ───────────────────────────────────────────────────────────────
const open = async () => {
    isOpen.value = true;
    query.value  = '';
    highlighted.value = -1;
    await nextTick();
    searchRef.value?.focus();
};

const close = () => {
    isOpen.value = false;
    query.value  = '';
};

const toggle = () => isOpen.value ? close() : open();

// ── Selection ──────────────────────────────────────────────────────────────────
const select = (option) => {
    emit('update:modelValue', option.value);
    close();
};

const clear = (e) => {
    e.stopPropagation();
    emit('update:modelValue', '');
    close();
};

// ── Keyboard navigation ────────────────────────────────────────────────────────
const onKeydown = (e) => {
    if (!isOpen.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (highlighted.value >= 0 && filtered.value[highlighted.value]) {
            select(filtered.value[highlighted.value]);
        }
    } else if (e.key === 'Escape') {
        close();
    }
};

// Reset highlight when filter changes
watch(query, () => { highlighted.value = -1; });

// ── Click outside ──────────────────────────────────────────────────────────────
const onClickOutside = (e) => {
    if (rootRef.value && !rootRef.value.contains(e.target)) close();
};

onMounted(()      => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="rootRef" class="relative" @keydown="onKeydown">
        <!-- ── Trigger button ───────────────────────────────────────────── -->
        <button
            type="button"
            :id="id"
            @click="toggle"
            class="mt-1 flex items-center w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white text-left focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all"
            :class="isOpen ? 'ring-2 ring-gray-900 border-transparent' : ''"
        >
            <!-- Selected value or placeholder -->
            <span class="flex-1 truncate" :class="selectedLabel ? 'text-gray-900' : 'text-gray-400'">
                {{ selectedLabel || placeholder }}
            </span>

            <!-- Clear button (shown when a value is selected) -->
            <span
                v-if="modelValue || modelValue === 0"
                @click="clear"
                class="flex-shrink-0 w-4 h-4 mr-1 text-gray-400 hover:text-gray-700 cursor-pointer"
                title="Clear"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </span>

            <!-- Chevron -->
            <svg
                class="flex-shrink-0 w-4 h-4 text-gray-400 transition-transform duration-200"
                :class="isOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- ── Dropdown panel ───────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 mt-1 w-full bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden"
            >
                <!-- Search input -->
                <div class="p-2 border-b border-gray-100">
                    <div class="flex items-center gap-2 px-2 py-1.5 bg-gray-50 rounded-lg">
                        <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z" />
                        </svg>
                        <input
                            ref="searchRef"
                            v-model="query"
                            type="text"
                            placeholder="Type to search…"
                            class="flex-1 bg-transparent text-sm outline-none text-gray-700 placeholder-gray-400"
                        />
                        <button
                            v-if="query"
                            type="button"
                            @click="query = ''"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Option list -->
                <ul class="max-h-52 overflow-y-auto py-1">
                    <!-- Empty state -->
                    <li v-if="filtered.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">
                        No results for "{{ query }}"
                    </li>

                    <li
                        v-for="(option, idx) in filtered"
                        :key="option.value"
                        @click="select(option)"
                        @mouseenter="highlighted = idx"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm cursor-pointer select-none transition-colors"
                        :class="[
                            highlighted === idx ? 'bg-gray-100' : '',
                            String(option.value) === String(modelValue)
                                ? 'font-semibold text-gray-900'
                                : 'text-gray-700',
                        ]"
                    >
                        <!-- Tick for selected -->
                        <svg
                            v-if="String(option.value) === String(modelValue)"
                            class="w-3.5 h-3.5 text-green-600 flex-shrink-0"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="w-3.5 h-3.5 flex-shrink-0" />

                        <span class="truncate">{{ option.label }}</span>
                    </li>
                </ul>

                <!-- Footer count -->
                <div v-if="options.length > 8" class="px-3 py-1.5 border-t border-gray-100 text-[11px] text-gray-400">
                    {{ filtered.length }} of {{ options.length }} staff
                </div>
            </div>
        </Transition>
    </div>
</template>
