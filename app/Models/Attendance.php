<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'recorded_by',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'status',
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /** The employee whose attendance this record belongs to */
    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The admin/manager who created this record */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public static function statuses(): array
    {
        return [
            'present'  => 'Present',
            'absent'   => 'Absent',
            'late'     => 'Late',
            'half_day' => 'Half Day',
            'on_leave' => 'On Leave',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }
}
