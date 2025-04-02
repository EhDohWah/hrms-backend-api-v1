<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;
use App\Models\TravelRequest;

/**
 * @OA\Schema(
 *     schema="TravelRequestApproval",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="travel_request_id", type="integer"),
 *     @OA\Property(property="approver_role", type="string", nullable=true),
 *     @OA\Property(property="approver_name", type="string", nullable=true),
 *     @OA\Property(property="approver_signature", type="string", nullable=true),
 *     @OA\Property(property="approval_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="status", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class TravelRequestApproval extends Model
{
    protected $table = 'travel_request_approvals';

    protected $fillable = [
        'travel_request_id',
        'approver_role',
        'approver_name',
        'approver_signature',
        'approval_date',
        'status',
        'created_by',
        'updated_by'
    ];

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class);
    }
}
