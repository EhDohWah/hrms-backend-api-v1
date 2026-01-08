<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

#[OA\Schema(
    schema: 'JobOffer',
    title: 'Job Offer',
    description: 'Job Offer model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Job offer ID'),
        new OA\Property(property: 'date', type: 'string', format: 'date', description: 'Offer date'),
        new OA\Property(property: 'candidate_name', type: 'string', description: 'Name of the candidate'),
        new OA\Property(property: 'position_name', type: 'string', description: 'Name of the position'),
        new OA\Property(property: 'probation_salary', type: 'number', format: 'float', description: 'Probation period salary'),
        new OA\Property(property: 'post_probation_salary', type: 'number', format: 'float', description: 'Post-probation salary'),
        new OA\Property(property: 'acceptance_deadline', type: 'string', format: 'date', description: 'Deadline for acceptance'),
        new OA\Property(property: 'acceptance_status', type: 'string', description: 'Status of acceptance'),
        new OA\Property(property: 'note', type: 'string', description: 'Additional notes'),
        new OA\Property(property: 'created_by', type: 'string', description: 'User who created the record'),
        new OA\Property(property: 'updated_by', type: 'string', description: 'User who last updated the record'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Last update timestamp'),
    ]
)]
class JobOffer extends Model
{
    use HasFactory, KeepsDeletedModels;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'custom_offer_id',
        'date',
        'candidate_name',
        'position_name',
        'probation_salary',
        'post_probation_salary',
        'acceptance_deadline',
        'acceptance_status',
        'note',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'acceptance_deadline' => 'date',
        'probation_salary' => 'decimal:2',
        'post_probation_salary' => 'decimal:2',
    ];

    /**
     * Override the restore method to handle SQL Server IDENTITY columns
     */
    public static function restore(string $deletionKey): static
    {
        $deletedModel = app(config('deleted-models.model'))->where('key', $deletionKey)->firstOrFail();

        $modelData = $deletedModel->values;
        $originalId = $modelData['id'] ?? null;

        // Remove the ID from the data so SQL Server can auto-generate it
        unset($modelData['id']);

        DB::beginTransaction();

        try {
            // Enable IDENTITY_INSERT for this table
            if ($originalId) {
                DB::statement('SET IDENTITY_INSERT job_offers ON');

                // Create with the original ID
                $restored = static::create(array_merge($modelData, ['id' => $originalId]));

                // Disable IDENTITY_INSERT
                DB::statement('SET IDENTITY_INSERT job_offers OFF');
            } else {
                // Create without ID (let SQL Server auto-generate)
                $restored = static::create($modelData);
            }

            // Delete the record from deleted_models
            $deletedModel->delete();

            DB::commit();

            return $restored;

        } catch (\Exception $e) {
            DB::rollBack();

            // Make sure IDENTITY_INSERT is turned off even if there's an error
            try {
                DB::statement('SET IDENTITY_INSERT job_offers OFF');
            } catch (\Exception $cleanupException) {
                // Ignore cleanup errors
            }

            throw $e;
        }
    }
}
