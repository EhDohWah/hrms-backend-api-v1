<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmployeeBeneficiary",
 *     type="object",
 *     title="Employee Beneficiary",
 *     description="Employee beneficiary model",
 *     required={"employee_id", "beneficiary_name", "beneficiary_relationship"},
 *
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Unique identifier for the employee beneficiary",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="employee_id",
 *         type="integer",
 *         description="ID of the employee this beneficiary belongs to",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="beneficiary_name",
 *         type="string",
 *         description="Full name of the beneficiary",
 *         example="John Doe"
 *     ),
 *     @OA\Property(
 *         property="beneficiary_relationship",
 *         type="string",
 *         description="Relationship of the beneficiary to the employee",
 *         example="spouse"
 *     ),
 *     @OA\Property(
 *         property="phone_number",
 *         type="string",
 *         description="Phone number of the beneficiary",
 *         example="+1234567890",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="created_by",
 *         type="string",
 *         description="User who created this record",
 *         example="admin",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="updated_by",
 *         type="string",
 *         description="User who last updated this record",
 *         example="admin",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the record was created",
 *         example="2023-01-01T00:00:00.000000Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the record was last updated",
 *         example="2023-01-01T00:00:00.000000Z"
 *     ),
 *     @OA\Property(
 *         property="employee",
 *         ref="#/components/schemas/Employee",
 *         description="The employee this beneficiary belongs to"
 *     )
 * )
 */
class EmployeeBeneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'beneficiary_name',
        'beneficiary_relationship',
        'phone_number',
        'created_by',
        'updated_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
