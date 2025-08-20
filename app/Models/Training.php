<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Training",
 *     title="Training",
 *     description="Training model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Training ID"),
 *     @OA\Property(property="title", type="string", description="Training title"),
 *     @OA\Property(property="organizer", type="string", description="Training organizer"),
 *     @OA\Property(property="start_date", type="string", format="date", description="Training start date"),
 *     @OA\Property(property="end_date", type="string", format="date", description="Training end date"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date"),
 *     @OA\Property(property="created_by", type="string", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", description="User who last updated the record")
 * )
 */
class Training extends Model
{
    //
    protected $table = 'trainings';

    protected $fillable = [
        'title',
        'organizer',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    // Relationship: A training may have many employee trainings
    public function employeeTrainings()
    {
        return $this->hasMany(EmployeeTraining::class);
    }
}
