<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmployeeReference",
 *     title="Employee Reference",
 *     description="Employee Reference model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="ID"),
 *     @OA\Property(property="referee_name", type="string", maxLength=200, example="John Doe", description="Name of the referee"),
 *     @OA\Property(property="occupation", type="string", maxLength=200, example="Software Engineer", description="Occupation of the referee"),
 *     @OA\Property(property="candidate_name", type="string", maxLength=100, example="Jane Smith", description="Name of the candidate"),
 *     @OA\Property(property="relation", type="string", maxLength=200, example="Former Manager", description="Relation between referee and candidate"),
 *     @OA\Property(property="address", type="string", maxLength=200, example="123 Main St, City", description="Address of the referee"),
 *     @OA\Property(property="phone_number", type="string", maxLength=50, example="+1234567890", description="Phone number of the referee"),
 *     @OA\Property(property="email", type="string", maxLength=200, example="john.doe@example.com", description="Email of the referee"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date"),
 *     @OA\Property(property="created_by", type="string", maxLength=100, example="admin", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", maxLength=100, example="admin", description="User who last updated the record")
 * )
 */
class EmployeeReference extends Model
{
    //
    protected $table = 'employee_references';

    protected $fillable = [
        'referee_name',
        'occupation',
        'candidate_name',
        'relation',
        'address',
        'phone_number',
        'email',
        'created_by',
        'updated_by'
    ];
}
