<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LeaveRequest',
    description: 'Leave request with support for multiple leave types',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', example: 123),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-01-15'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-01-17'),
        new OA\Property(property: 'total_days', type: 'number', example: 3.5, description: 'Sum of all item days'),
        new OA\Property(property: 'reason', type: 'string', example: 'Family emergency and medical checkup'),
        new OA\Property(property: 'status', type: 'string', example: 'approved', enum: ['pending', 'approved', 'declined', 'cancelled']),
        new OA\Property(property: 'supervisor_approved', type: 'boolean', default: false, example: true),
        new OA\Property(property: 'supervisor_approved_date', type: 'string', format: 'date', nullable: true, example: '2025-01-10'),
        new OA\Property(property: 'hr_site_admin_approved', type: 'boolean', default: false, example: true),
        new OA\Property(property: 'hr_site_admin_approved_date', type: 'string', format: 'date', nullable: true, example: '2025-01-12'),
        new OA\Property(property: 'attachment_notes', type: 'string', nullable: true, example: 'Medical certificate attached'),
        new OA\Property(property: 'created_by', type: 'string', nullable: true, example: 'System'),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true, example: 'John Doe'),
        new OA\Property(property: 'items', type: 'array', description: 'Leave type items with individual days allocation', items: new OA\Items(ref: '#/components/schemas/LeaveRequestItem')),
        new OA\Property(
            property: 'employee',
            type: 'object',
            description: 'Employee information',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 123),
                new OA\Property(property: 'staff_id', type: 'string', example: 'EMP001'),
                new OA\Property(property: 'first_name_en', type: 'string', example: 'John'),
                new OA\Property(property: 'last_name_en', type: 'string', example: 'Doe'),
            ]
        ),
    ]
)]
class LeaveRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'supervisor_approved',
        'supervisor_approved_date',
        'hr_site_admin_approved',
        'hr_site_admin_approved_date',
        'attachment_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
        'supervisor_approved' => 'boolean',
        'supervisor_approved_date' => 'date',
        'hr_site_admin_approved' => 'boolean',
        'hr_site_admin_approved_date' => 'date',
    ];

    /**
     * Get the employee that owns the leave request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * Get the leave type that owns the leave request.
     *
     * @deprecated Use items() relationship instead for multi-leave-type support
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the leave request items (multiple leave types per request).
     */
    public function items(): HasMany
    {
        return $this->hasMany(LeaveRequestItem::class, 'leave_request_id');
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
                'leaveTypeBreakdown' => DB::table('leave_request_items')
                    ->join('leave_types', 'leave_request_items.leave_type_id', '=', 'leave_types.id')
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
