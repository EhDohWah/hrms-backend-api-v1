<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait LogsActivity
{
    /**
     * Boot the trait and register model event listeners
     */
    protected static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            // Skip if only timestamps changed
            if ($model->wasOnlyTimestampsChanged()) {
                return;
            }

            $model->logActivity('updated', $model->getActivityChanges());
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    /**
     * Log an activity for this model
     *
     * @param  string  $action  The action performed
     * @param  array|null  $properties  Optional properties to log
     * @param  string|null  $description  Optional description
     */
    public function logActivity(string $action, ?array $properties = null, ?string $description = null): ActivityLog
    {
        return ActivityLog::log($action, $this, $description, $properties);
    }

    /**
     * Get the display name for activity logs
     * Override this in your model to customize
     */
    public function getActivityLogName(): string
    {
        // Try common name fields
        if (isset($this->name)) {
            return $this->name;
        }

        if (isset($this->title)) {
            return $this->title;
        }

        if (isset($this->code)) {
            return $this->code;
        }

        // Default to class name with ID
        return class_basename($this).' #'.$this->getKey();
    }

    /**
     * Sensitive fields that should never be logged for security reasons.
     * Override $activityLogExcludedFields in your model to customize.
     */
    protected static array $defaultExcludedFields = [
        // Authentication & Security
        'password',
        'password_hash',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'two_factor_secret',
        'two_factor_recovery_codes',

        // Personal Identification (PII)
        'ssn',
        'social_security_number',
        'national_id',
        'passport_number',
        'bank_account',
        'bank_account_number',
        'credit_card',

        // Timestamps handled separately
        'created_at',
        'updated_at',
        'updated_by',
        'deleted_at',
    ];

    /**
     * Get the list of fields to exclude from activity logs.
     * Models can override this by defining $activityLogExcludedFields property.
     */
    protected function getExcludedFields(): array
    {
        $modelExcluded = property_exists($this, 'activityLogExcludedFields')
            ? $this->activityLogExcludedFields
            : [];

        return array_merge(self::$defaultExcludedFields, $modelExcluded);
    }

    /**
     * Get the changes made to the model for logging
     */
    protected function getActivityChanges(): ?array
    {
        $changes = $this->getChanges();
        $original = $this->getOriginal();

        // Remove excluded and sensitive fields
        $excludedFields = $this->getExcludedFields();
        $changes = array_diff_key($changes, array_flip($excludedFields));

        if (empty($changes)) {
            return null;
        }

        $oldValues = [];
        $newValues = [];

        foreach (array_keys($changes) as $field) {
            $oldValues[$field] = $original[$field] ?? null;
            $newValues[$field] = $changes[$field];
        }

        return [
            'old' => $oldValues,
            'new' => $newValues,
            'changes' => array_keys($changes),
        ];
    }

    /**
     * Check if only timestamp fields were updated
     */
    protected function wasOnlyTimestampsChanged(): bool
    {
        $changes = $this->getChanges();
        $timestampFields = ['created_at', 'updated_at', 'updated_by'];

        $nonTimestampChanges = array_diff(array_keys($changes), $timestampFields);

        return empty($nonTimestampChanges);
    }

    /**
     * Get activity logs for this model
     */
    public function activityLogs()
    {
        return ActivityLog::forSubject(get_class($this), $this->getKey())
            ->latest('created_at');
    }
}
