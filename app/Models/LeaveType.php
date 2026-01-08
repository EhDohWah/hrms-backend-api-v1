<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LeaveType',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', maxLength: 100),
        new OA\Property(property: 'default_duration', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'requires_attachment', type: 'boolean', default: false),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_duration',
        'description',
        'requires_attachment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'default_duration' => 'decimal:2',
    ];

    public $timestamps = true;

    /**
     * Get the leave requests for this leave type.
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the leave balances for this leave type.
     */
    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
