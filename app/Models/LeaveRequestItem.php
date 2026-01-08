<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

/**
 * Leave Request Item Model
 *
 * Represents individual leave types within a leave request.
 * Allows a single leave request to span multiple leave types.
 *
 * @property int $id
 * @property int $leave_request_id
 * @property int $leave_type_id
 * @property float $days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[OA\Schema(
    schema: 'LeaveRequestItem',
    description: 'Individual leave type item within a leave request',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'leave_request_id', type: 'integer', example: 1, description: 'Parent leave request ID'),
        new OA\Property(property: 'leave_type_id', type: 'integer', example: 1, description: 'Leave type ID'),
        new OA\Property(property: 'days', type: 'number', format: 'float', example: 2, description: 'Number of days for this leave type'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-01-15T10:30:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2025-01-15T10:30:00Z'),
        new OA\Property(
            property: 'leave_type',
            type: 'object',
            description: 'Leave type details',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Annual Leave'),
                new OA\Property(property: 'default_duration', type: 'number', example: 26),
                new OA\Property(property: 'description', type: 'string', example: 'Annual vacation / ลาพักร้อนประจำปี'),
                new OA\Property(property: 'requires_attachment', type: 'boolean', example: false),
            ]
        ),
    ]
)]
class LeaveRequestItem extends Model
{
    use HasFactory;

    protected $table = 'leave_request_items';

    protected $fillable = [
        'leave_request_id',
        'leave_type_id',
        'days',
    ];

    protected $casts = [
        'days' => 'decimal:2',
    ];

    /**
     * Get the leave request that owns this item.
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * Get the leave type for this item.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
