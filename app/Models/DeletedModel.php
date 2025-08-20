<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="DeletedModel",
 *     type="object",
 *     title="Deleted Model",
 *     description="Model representing deleted records stored in the recycle bin",
 *
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Unique identifier for the deleted model record",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="key",
 *         type="string",
 *         maxLength=40,
 *         description="Unique key for the deleted record (used for restoration)",
 *         example="abc123def456"
 *     ),
 *     @OA\Property(
 *         property="model",
 *         type="string",
 *         description="Full class name of the original model",
 *         example="App\\Models\\Interview"
 *     ),
 *     @OA\Property(
 *         property="values",
 *         type="object",
 *         description="JSON data containing the original model's attributes",
 *         example={"id": 1, "candidate_name": "John Doe", "job_position": "Developer"}
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the record was deleted",
 *         example="2023-12-01T10:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the deleted record was last updated",
 *         example="2023-12-01T10:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="model_type",
 *         type="string",
 *         description="Simple class name of the original model (computed attribute)",
 *         example="Interview"
 *     ),
 *     @OA\Property(
 *         property="original_id",
 *         type="integer",
 *         description="Original ID of the deleted model (computed attribute)",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="deleted_time_ago",
 *         type="string",
 *         description="Human readable time since deletion (computed attribute)",
 *         example="2 hours ago"
 *     ),
 *     @OA\Property(
 *         property="deleted_at",
 *         type="string",
 *         format="date-time",
 *         description="Alias for created_at (computed attribute)",
 *         example="2023-12-01T10:30:00Z"
 *     )
 * )
 */
class DeletedModel extends Model
{
    protected $table = 'deleted_models';

    protected $fillable = [
        'key',
        'model',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    /**
     * Get the model class name without namespace
     * This provides a user-friendly display name for the API
     * e.g., "App\Models\Interview" becomes "Interview"
     */
    public function getModelTypeAttribute(): string
    {
        return class_basename($this->model);
    }

    /**
     * Get the original model ID from the values
     */
    public function getOriginalIdAttribute()
    {
        return $this->values['id'] ?? null;
    }

    /**
     * Get human readable deleted time from created_at
     */
    public function getDeletedTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the deleted at time (using created_at)
     */
    public function getDeletedAtAttribute()
    {
        return $this->created_at;
    }

    /**
     * Check if the original model class still exists
     */
    public function modelClassExists(): bool
    {
        return class_exists($this->model);
    }

    /**
     * Restore this deleted record using the Spatie package method
     */
    public function restoreRecord()
    {
        if (! $this->modelClassExists()) {
            throw new \Exception("Model class {$this->model} no longer exists");
        }

        $modelClass = $this->model;

        return $modelClass::restore($this->key);
    }

    /**
     * Scope to filter by model type
     */
    public function scopeOfModel($query, string $modelClass)
    {
        return $query->where('model', $modelClass);
    }

    /**
     * Scope to get recent deletions
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }
}
