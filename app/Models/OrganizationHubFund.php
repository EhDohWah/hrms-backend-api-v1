<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrganizationHubFund',
    title: 'Organization Hub Fund',
    description: 'Organization Hub Fund model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'ID of the organization hub fund'),
        new OA\Property(property: 'organization', type: 'string', maxLength: 5, description: 'Organization code'),
        new OA\Property(property: 'hub_grant_id', type: 'integer', format: 'int64', description: 'ID of the hub grant'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Last update timestamp'),
        new OA\Property(property: 'created_by', type: 'string', maxLength: 100, nullable: true, description: 'User who created the record'),
        new OA\Property(property: 'updated_by', type: 'string', maxLength: 100, nullable: true, description: 'User who last updated the record'),
    ]
)]
class OrganizationHubFund extends Model
{
    /**
     * @var array
     *
     * @OA\Property(
     *     property="fillable",
     *     type="array",
     *
     *     @OA\Items(type="string"),
     *     description="Mass assignable attributes"
     * )
     */
    protected $fillable = [
        'organization',
        'hub_grant_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the hub grant that this fund belongs to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function hubGrant()
    {
        return $this->belongsTo(Grant::class, 'hub_grant_id');
    }
}
