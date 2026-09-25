<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
    attendances: { type: Object, default: () => ({ data: [], links: [] }) },
    users:       { type: Array,  default: () => [] },
    statuses:    { type: Object, default: () => ({}) },
    filters:     { type: Object, default: () => ({}) },
});

// ── Status badge config ───────────────────────────────────────────────────────
const statusConfig = {
    present:  { label: 'Present',  cls: 'bg-green-100 text-green-800' },
    absent:   { label: 'Absent',   cls: 'bg-red-100 text-red-800'     },
    late:     { label: 'Late',     cls: 'bg-yellow-100 text-yellow-800'},
    half_day: { label: 'Half Day', cls: 'bg-blue-100 text-blue-800'   },
    on_leave: { label: 'On Leave', cls: 'bg-purple-100 text-purple-800'},
};

// ── Filters ───────────────────────────────────────────────────────────────────
const filterForm = ref({
    user_id:   props.filters.user_id   || '',
    status:    props.filters.status    || '',
    date_from: props.filters.date_from || '',
    date_to:   props.filters.date_to   || '',
});

const applyFilters = () => {
    router.get(route('attendance.index'), filterForm.value, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.value = { user_id: '', status: '', date_from: '', date_to: '' };
    applyFilters();
};

// ── Modal ─────────────────────────────────────────────────────────────────────
const isModalOpen  = ref(false);
const editingRecord = ref(null);
const deleteTarget  = ref(null);
const showDeleteConfirm = ref(false);

const form = useForm({
    user_id:         '',
    attendance_date: new Date().toISOString().split('T')[0],
    check_in_time:   '',
    check_out_time:  '',
    status:          'present',
    remarks:         '',
});

const openCreate = () => {
    editingRecord.value = null;
    form.reset();
    form.attendance_date = new Date().toISOString().split('T')[0];
    form.status = 'present';
    isModalOpen.value = true;
};

const openEdit = (record) => {
    editingRecord.value = record;
    form.user_id         = record.user_id;
    form.attendance_date = record.attendance_date;
    // Strip seconds from DB time values ("09:00:00" → "09:00") so the
    // HTML time input and the backend H:i validator both accept the value.
    const toHHMM = (t) => (t ? t.substring(0, 5) : '');
    form.check_in_time   = toHHMM(record.check_in_time);
    form.check_out_time  = toHHMM(record.check_out_time);
    form.status          = record.status;
    form.remarks         = record.remarks || '';
    isModalOpen.value    = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
    editingRecord.value = null;
};

const submit = () => {
    if (editingRecord.value) {
        form.put(route('attendance.update', editingRecord.value.id), {
            onSuccess: closeModal,
        });
    } else {
        form.post(route('attendance.store'), {
            onSuccess: closeModal,
        });
    }
};

// ── Delete ────────────────────────────────────────────────────────────────────
const confirmDelete = (record) => {
    deleteTarget.value    = record;
    showDeleteConfirm.value = true;
};

const destroyRecord = () => {
    router.delete(route('attendance.destroy', deleteTarget.value.id), {
        onSuccess: () => { showDeleteConfirm.value = false; deleteTarget.value = null; },
    });
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatTime = (t) => {
    if (!t) return '—';
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    return `${hour % 12 || 12}:${m} ${hour < 12 ? 'AM' : 'PM'}`;
};

const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
    });
};

const totalPresent  = computed(() => props.attendances.data.filter(a => a.status === 'present').length);
const totalAbsent   = computed(() => props.attendances.data.filter(a => a.status === 'absent').length);
const totalLate     = computed(() => props.attendances.data.filter(a => a.status === 'late').length);
const totalLeave    = computed(() => props.attendances.data.filter(a => a.status === 'on_leave').length);

// Normalised options for SearchableSelect
const staffOptions = computed(() =>
    props.users.map(u => ({ value: u.id, label: `${u.name} (${u.role})` }))
);

// Build the export URL with active filters so the Excel file matches the current view
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (filterForm.value.user_id)   params.set('user_id',   filterForm.value.user_id);
    if (filterForm.value.status)    params.set('status',    filterForm.value.status);
    if (filterForm.value.date_from) params.set('date_from', filterForm.value.date_from);
    if (filterForm.value.date_to)   params.set('date_to',   filterForm.value.date_to);
    const qs = params.toString();
    return route('attendance.export') + (qs ? '?' + qs : '');
});
</script>

<template>
    <Head title="Attendance Management" />
    <AdminLayout>
        <div class="py-8 px-6">

            <!-- ── Page Header ──────────────────────────────────────────────── -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Attendance Management</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Record and manage staff attendance. Only admins and managers can access this page.</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Export Excel -->
                    <a
                        :href="exportUrl"
                        class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors"
                        title="Export current filtered results to CSV (opens in Excel)"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export CSV
                    </a>

                    <!-- Add Attendance -->
                    <button
                        @click="openCreate"
                        class="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Attendance
                    </button>
                </div>
            </div>

            <!-- ── Summary Cards ────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Present</p>
                    <p class="text-3xl font-black text-green-600 mt-1">{{ totalPresent }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Absent</p>
                    <p class="text-3xl font-black text-red-600 mt-1">{{ totalAbsent }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Late</p>
                    <p class="text-3xl font-black text-yellow-600 mt-1">{{ totalLate }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">On Leave</p>
                    <p class="text-3xl font-black text-purple-600 mt-1">{{ totalLeave }}</p>
                </div>
            </div>

            <!-- ── Filters ──────────────────────────────────────────────────── -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <!-- Staff member -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Staff Member</label>
                        <SearchableSelect
                            v-model="filterForm.user_id"
                            :options="[{ value: '', label: 'All Staff' }, ...staffOptions]"
                            placeholder="All Staff"
                        />
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
                        <select v-model="filterForm.status"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none">
                            <option value="">All Statuses</option>
                            <option v-for="(label, val) in statuses" :key="val" :value="val">{{ label }}</option>
                        </select>
                    </div>
                    <!-- Date From -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">From Date</label>
                        <input type="date" v-model="filterForm.date_from"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none" />
                    </div>
                    <!-- Date To -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">To Date</label>
                        <input type="date" v-model="filterForm.date_to"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none" />
                    </div>
                    <!-- Actions -->
                    <div class="flex items-end gap-2">
                        <button @click="applyFilters"
                            class="flex-1 px-4 py-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold rounded-lg transition-colors">
                            Filter
                        </button>
                        <button @click="clearFilters"
                            class="px-3 py-2 border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-semibold rounded-lg transition-colors">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Table ────────────────────────────────────────────────────── -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Staff Member</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Check-In</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Check-Out</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Remarks</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Recorded By</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-50">
                            <tr v-if="attendances.data.length === 0">
                                <td colspan="8" class="px-5 py-12 text-center text-gray-400 text-sm">
                                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    No attendance records found.
                                </td>
                            </tr>
                            <tr v-for="record in attendances.data" :key="record.id"
                                class="hover:bg-gray-50 transition-colors">
                                <!-- Date -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <p class="text-sm font-semibold text-gray-900">{{ formatDate(record.attendance_date) }}</p>
                                </td>
                                <!-- Staff -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-black text-gray-600">
                                            {{ record.employee?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ record.employee?.name }}</p>
                                            <p class="text-xs text-gray-400 capitalize">{{ record.employee?.role }}</p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Check-In -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700 font-mono">{{ formatTime(record.check_in_time) }}</span>
                                </td>
                                <!-- Check-Out -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700 font-mono">{{ formatTime(record.check_out_time) }}</span>
                                </td>
                                <!-- Status -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span :class="statusConfig[record.status]?.cls ?? 'bg-gray-100 text-gray-700'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold">
                                        {{ statusConfig[record.status]?.label ?? record.status }}
                                    </span>
                                </td>
                                <!-- Remarks -->
                                <td class="px-5 py-4 max-w-[200px]">
                                    <p class="text-sm text-gray-500 truncate">{{ record.remarks || '—' }}</p>
                                </td>
                                <!-- Recorded By -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <p class="text-xs text-gray-400">{{ record.recorder?.name }}</p>
                                </td>
                                <!-- Actions -->
                                <td class="px-5 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEdit(record)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="confirmDelete(record)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="attendances.links?.length > 3" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-sm text-gray-500">
                        Showing {{ attendances.from }}–{{ attendances.to }} of {{ attendances.total }} records
                    </p>
                    <div class="flex gap-1">
                        <template v-for="link in attendances.links" :key="link.label">
                            <a v-if="link.url" :href="link.url"
                                :class="link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                                class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors"
                                v-html="link.label" />
                            <span v-else class="px-3 py-1.5 text-sm text-gray-300 cursor-not-allowed" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Add / Edit Modal ─────────────────────────────────────────────── -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="lg">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-6">
                    {{ editingRecord ? 'Edit Attendance Record' : 'Add Attendance Record' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Staff Member -->
                    <div>
                        <InputLabel for="user_id" value="Staff Member *" />
                        <SearchableSelect
                            id="user_id"
                            v-model="form.user_id"
                            :options="staffOptions"
                            placeholder="— Select staff member —"
                        />
                        <InputError :message="form.errors.user_id" class="mt-1" />
                    </div>

                    <!-- Attendance Date -->
                    <div>
                        <InputLabel for="attendance_date" value="Attendance Date *" />
                        <input id="attendance_date" type="date" v-model="form.attendance_date"
                            class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none" />
                        <InputError :message="form.errors.attendance_date" class="mt-1" />
                    </div>

                    <!-- Check-In / Check-Out -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="check_in_time" value="Check-In Time" />
                            <input id="check_in_time" type="time" v-model="form.check_in_time"
                                class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none" />
                            <InputError :message="form.errors.check_in_time" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="check_out_time" value="Check-Out Time" />
                            <input id="check_out_time" type="time" v-model="form.check_out_time"
                                class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none" />
                            <InputError :message="form.errors.check_out_time" class="mt-1" />
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <InputLabel for="status" value="Attendance Status *" />
                        <select id="status" v-model="form.status"
                            class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none">
                            <option v-for="(label, val) in statuses" :key="val" :value="val">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <!-- Remarks -->
                    <div>
                        <InputLabel for="remarks" value="Remarks" />
                        <textarea id="remarks" v-model="form.remarks" rows="3"
                            placeholder="Optional notes about this attendance record..."
                            class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none resize-none" />
                        <InputError :message="form.errors.remarks" class="mt-1" />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : editingRecord ? 'Update Record' : 'Save Record' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ── Delete Confirmation ──────────────────────────────────────────── -->
        <Modal :show="showDeleteConfirm" @close="showDeleteConfirm = false" max-width="sm">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Delete Attendance Record?</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            This will permanently remove the attendance record for
                            <strong>{{ deleteTarget?.employee?.name }}</strong>
                            on {{ formatDate(deleteTarget?.attendance_date) }}.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <SecondaryButton @click="showDeleteConfirm = false">Cancel</SecondaryButton>
                    <button @click="destroyRecord"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
                        Delete Record
                    </button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
