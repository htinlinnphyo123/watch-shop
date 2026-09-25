<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['employee', 'recorder'])
            ->orderBy('attendance_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter: specific user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter: status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter: date range
        if ($request->filled('date_from')) {
            $query->whereDate('attendance_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('attendance_date', '<=', $request->date_to);
        }

        return Inertia::render('Attendance/Index', [
            'attendances' => $query->paginate(20)->withQueryString(),
            'users'       => User::select('id', 'name', 'role')->orderBy('name')->get(),
            'statuses'    => Attendance::statuses(),
            'filters'     => $request->only(['user_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'attendance_date' => 'required|date',
            'check_in_time'   => 'nullable|date_format:H:i,H:i:s',
            'check_out_time'  => 'nullable|date_format:H:i,H:i:s',
            'status'          => 'required|in:present,absent,late,half_day,on_leave',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        // Normalise to HH:MM (strip seconds if present)
        if (!empty($validated['check_in_time']))  $validated['check_in_time']  = substr($validated['check_in_time'],  0, 5);
        if (!empty($validated['check_out_time'])) $validated['check_out_time'] = substr($validated['check_out_time'], 0, 5);

        // Validate check-out is not before check-in when both provided
        if (!empty($validated['check_in_time']) && !empty($validated['check_out_time'])) {
            if ($validated['check_out_time'] < $validated['check_in_time']) {
                return back()->withErrors(['check_out_time' => 'Check-out time must be after check-in time.']);
            }
        }

        $validated['recorded_by'] = $request->user()->id;

        Attendance::create($validated);

        return redirect()->back()->with('success', 'Attendance recorded successfully.');
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'attendance_date' => 'required|date',
            'check_in_time'   => 'nullable|date_format:H:i,H:i:s',
            'check_out_time'  => 'nullable|date_format:H:i,H:i:s',
            'status'          => 'required|in:present,absent,late,half_day,on_leave',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        // Normalise to HH:MM (strip seconds if present)
        if (!empty($validated['check_in_time']))  $validated['check_in_time']  = substr($validated['check_in_time'],  0, 5);
        if (!empty($validated['check_out_time'])) $validated['check_out_time'] = substr($validated['check_out_time'], 0, 5);

        // Validate check-out is not before check-in when both provided
        if (!empty($validated['check_in_time']) && !empty($validated['check_out_time'])) {
            if ($validated['check_out_time'] < $validated['check_in_time']) {
                return back()->withErrors(['check_out_time' => 'Check-out time must be after check-in time.']);
            }
        }

        $validated['recorded_by'] = $request->user()->id;

        $attendance->update($validated);

        return redirect()->back()->with('success', 'Attendance updated successfully.');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return redirect()->back()->with('success', 'Attendance record deleted.');
    }

    public function export(Request $request)
    {
        $request->validate([
            'user_id'   => 'nullable|exists:users,id',
            'status'    => 'nullable|in:present,absent,late,half_day,on_leave',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $query = Attendance::with(['employee', 'recorder'])
            ->orderBy('attendance_date', 'asc')
            ->orderBy('created_at', 'asc');

        if ($request->filled('user_id'))   $query->where('user_id', $request->user_id);
        if ($request->filled('status'))    $query->where('status',  $request->status);
        if ($request->filled('date_from')) $query->whereDate('attendance_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('attendance_date', '<=', $request->date_to);

        $records = $query->get();
        $statusLabels = Attendance::statuses();
        $filename = 'attendance-' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($records, $statusLabels) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel to auto-detect UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'Date', 'Staff Name', 'Role',
                'Check-In', 'Check-Out', 'Status',
                'Remarks', 'Recorded By', 'Recorded At',
            ]);

            foreach ($records as $row) {
                fputcsv($handle, [
                    $row->attendance_date?->format('Y-m-d'),
                    $row->employee?->name,
                    $row->employee?->role,
                    $row->check_in_time  ? substr($row->check_in_time,  0, 5) : '',
                    $row->check_out_time ? substr($row->check_out_time, 0, 5) : '',
                    $statusLabels[$row->status] ?? $row->status,
                    $row->remarks ?? '',
                    $row->recorder?->name,
                    $row->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
