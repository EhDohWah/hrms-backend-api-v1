<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LeaveAttachment",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="leave_request_id", type="integer"),
 *     @OA\Property(property="document_name", type="string", maxLength=255),
 *     @OA\Property(property="document_url", type="string", maxLength=1000),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="added_at", type="string", format="date-time"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class LeaveAttachment extends Model
{
    protected $table = 'leave_attachments';

    protected $fillable = [
        'leave_request_id',
        'document_name',
        'document_url',
        'description',
        'added_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the leave request that owns the attachment.
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
