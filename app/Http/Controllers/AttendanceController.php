<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
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
            'check_in_time'   => 'nullable|date_format:H:i',
            'check_out_time'  => 'nullable|date_format:H:i|after_or_equal:check_in_time',
            'status'          => 'required|in:present,absent,late,half_day,on_leave',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $validated['recorded_by'] = $request->user()->id;

        Attendance::create($validated);

        return redirect()->back()->with('success', 'Attendance recorded successfully.');
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'attendance_date' => 'required|date',
            'check_in_time'   => 'nullable|date_format:H:i',
            'check_out_time'  => 'nullable|date_format:H:i|after_or_equal:check_in_time',
            'status'          => 'required|in:present,absent,late,half_day,on_leave',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $validated['recorded_by'] = $request->user()->id;

        $attendance->update($validated);

        return redirect()->back()->with('success', 'Attendance updated successfully.');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return redirect()->back()->with('success', 'Attendance record deleted.');
    }
}
