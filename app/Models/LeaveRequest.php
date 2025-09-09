<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LeaveRequest",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="employee_id", type="integer"),
 *     @OA\Property(property="leave_type_id", type="integer"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="end_date", type="string", format="date"),
 *     @OA\Property(property="total_days", type="number"),
 *     @OA\Property(property="reason", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class LeaveRequest extends Model
{
    //
    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
    ];

    /**
     * Get the employee that owns the leave request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave type that owns the leave request.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the approvals for the leave request.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }

    /**
     * Get the attachments for the leave request.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveAttachment::class);
    }

    /**
     * Get leave request statistics with caching
     */
    public static function getStatistics(): array
    {
        return Cache::remember('leave_request_statistics', 300, function () {
            $now = now();
            $currentMonth = $now->month;
            $currentYear = $now->year;
            $startOfWeek = $now->copy()->startOfWeek();
            $endOfWeek = $now->copy()->endOfWeek();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();

            return [
                'totalRequests' => LeaveRequest::count(),
                'pendingRequests' => LeaveRequest::where('status', 'pending')->count(),
                'approvedRequests' => LeaveRequest::where('status', 'approved')->count(),
                'declinedRequests' => LeaveRequest::where('status', 'declined')->count(),
                'cancelledRequests' => LeaveRequest::where('status', 'cancelled')->count(),
                'thisMonthRequests' => LeaveRequest::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'thisWeekRequests' => LeaveRequest::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
                'thisYearRequests' => LeaveRequest::whereYear('created_at', $currentYear)->count(),
                'statusBreakdown' => [
                    'pending' => LeaveRequest::where('status', 'pending')->count(),
                    'approved' => LeaveRequest::where('status', 'approved')->count(),
                    'declined' => LeaveRequest::where('status', 'declined')->count(),
                    'cancelled' => LeaveRequest::where('status', 'cancelled')->count(),
                ],
                'timeBreakdown' => [
                    'thisWeek' => LeaveRequest::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
                    'thisMonth' => LeaveRequest::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                    'thisYear' => LeaveRequest::whereYear('created_at', $currentYear)->count(),
                ],
                'leaveTypeBreakdown' => DB::table('leave_requests')
                    ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
                    ->select('leave_types.name', DB::raw('count(*) as count'))
                    ->groupBy('leave_types.id', 'leave_types.name')
                    ->orderBy('count', 'desc')
                    ->limit(5)
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->name => $item->count];
                    })
                    ->toArray(),
            ];
        });
    }
}
