<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmployeeIdentification",
 *     title="Employee Identification",
 *     description="Employee Identification model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employee_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="id_type", type="string", example="passport"),
 *     @OA\Property(property="document_number", type="string", example="A12345678"),
 *     @OA\Property(property="issue_date", type="string", format="date", example="2020-01-01"),
 *     @OA\Property(property="expiry_date", type="string", format="date", example="2030-01-01"),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z")
 * )
 */
class EmployeeIdentification extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'id_type',
        'document_number',
        'issue_date',
        'expiry_date',
        'created_by',
        'updated_by'
    ];

    /**
     * Get the employee that owns the identification.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the identification data.
     */
    public function getIdentificationAttribute()
    {
        return [
            'id_type' => $this->id_type,
            'document_number' => $this->document_number,
        ];
    }

    /**
     * Create a collection-like map method to make the model compatible with collection methods.
     *
     * @param callable $callback
     * @return array
     */
    public function map(callable $callback)
    {
        return [$callback($this)];
    }
}
