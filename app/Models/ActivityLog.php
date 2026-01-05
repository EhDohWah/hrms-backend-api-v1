<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    /**
     * Disable default timestamps since we only use created_at
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_name',
        'description',
        'properties',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject of the activity (polymorphic)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Static helper to log an activity
     *
     * @param  string  $action  The action performed (created, updated, deleted, processed, imported)
     * @param  Model|null  $subject  The model being acted upon
     * @param  string|null  $description  Optional description of the action
     * @param  array|null  $properties  Optional properties (old/new values)
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $properties = null
    ): self {
        $subjectName = null;
        $subjectType = null;
        $subjectId = null;

        if ($subject) {
            $subjectType = get_class($subject);
            $subjectId = $subject->getKey();
            $subjectName = method_exists($subject, 'getActivityLogName')
                ? $subject->getActivityLogName()
                : ($subject->name ?? $subject->title ?? "#{$subjectId}");
        }

        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Scope to filter by subject type and id
     */
    public function scopeForSubject($query, string $type, $id)
    {
        // Convert short type names to full class names
        $typeMap = [
            'grant' => Grant::class,
            'grants' => Grant::class,
            'employee' => Employee::class,
            'employees' => Employee::class,
            'employment' => Employment::class,
            'employments' => Employment::class,
            'payroll' => Payroll::class,
            'payrolls' => Payroll::class,
        ];

        $fullType = $typeMap[strtolower($type)] ?? $type;

        return $query->where('subject_type', $fullType)
            ->where('subject_id', $id);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by action
     */
    public function scopeByAction($query, $action)
    {
        if (is_array($action)) {
            return $query->whereIn('action', $action);
        }

        return $query->where('action', $action);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $from = null, $to = null)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope to filter by subject type only
     */
    public function scopeBySubjectType($query, string $type)
    {
        $typeMap = [
            'grant' => Grant::class,
            'grants' => Grant::class,
            'employee' => Employee::class,
            'employees' => Employee::class,
            'employment' => Employment::class,
            'employments' => Employment::class,
            'payroll' => Payroll::class,
            'payrolls' => Payroll::class,
        ];

        $fullType = $typeMap[strtolower($type)] ?? $type;

        return $query->where('subject_type', $fullType);
    }

    /**
     * Get the short subject type name for display
     */
    public function getSubjectTypeShortAttribute(): string
    {
        return class_basename($this->subject_type);
    }

    /**
     * Get formatted action for display
     */
    public function getActionLabelAttribute(): string
    {
        return ucfirst($this->action);
    }

    /**
     * Get the changes made (from properties)
     */
    public function getChangesAttribute(): ?array
    {
        return $this->properties['changes'] ?? null;
    }

    /**
     * Get the old values (from properties)
     */
    public function getOldValuesAttribute(): ?array
    {
        return $this->properties['old'] ?? null;
    }

    /**
     * Get the new values (from properties)
     */
    public function getNewValuesAttribute(): ?array
    {
        return $this->properties['new'] ?? null;
    }
}

