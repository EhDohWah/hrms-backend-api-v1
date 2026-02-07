<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletionManifest extends Model
{
    protected $fillable = [
        'deletion_key',
        'root_model',
        'root_id',
        'root_display_name',
        'snapshot_keys',
        'table_order',
        'deleted_by',
        'deleted_by_name',
        'reason',
    ];

    protected $casts = [
        'snapshot_keys' => 'array',
        'table_order' => 'array',
    ];

    /**
     * Get the user who deleted this record.
     */
    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the model class name without namespace.
     */
    public function getRootModelTypeAttribute(): string
    {
        return class_basename($this->root_model);
    }

    /**
     * Get human-readable time since deletion.
     */
    public function getDeletedTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the count of snapshot records (including root).
     */
    public function getSnapshotCountAttribute(): int
    {
        return count($this->snapshot_keys ?? []);
    }

    /**
     * Scope to filter by model type.
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('root_model', $modelClass);
    }

    /**
     * Scope to find manifests older than given days.
     */
    public function scopeExpired($query, int $days = 30)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
}
