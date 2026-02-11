<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'total_hours',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_hours' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Auto-calculate total_hours when both clock_in and clock_out are present
        static::saving(function (Attendance $attendance) {
            if ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = \Carbon\Carbon::parse($attendance->clock_in);
                $clockOut = \Carbon\Carbon::parse($attendance->clock_out);

                // Handle overnight shifts (clock_out before clock_in)
                if ($clockOut->lt($clockIn)) {
                    $clockOut->addDay();
                }

                $attendance->total_hours = round($clockOut->diffInMinutes($clockIn) / 60, 2);
            } elseif (! $attendance->clock_in || ! $attendance->clock_out) {
                // Clear total_hours if either time is missing
                $attendance->total_hours = null;
            }
        });
    }

    // Relationship: Each attendance belongs to an employee
    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
