<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BulkPayrollBatch Model
 *
 * Tracks bulk payroll creation batches with real-time progress
 *
 * @property int $id
 * @property string $pay_period Format: YYYY-MM
 * @property array|null $filters JSON filters (organization, department, grant, employment_type)
 * @property int $total_employees
 * @property int $total_payrolls Total payroll records (> employees due to multiple allocations)
 * @property int $processed_payrolls
 * @property int $successful_payrolls
 * @property int $failed_payrolls
 * @property int $advances_created
 * @property string $status pending|processing|completed|failed
 * @property array|null $errors Array of error objects
 * @property array|null $summary Final summary with totals and breakdown
 * @property string|null $current_employee Currently processing employee name
 * @property string|null $current_allocation Currently processing allocation label
 * @property int $created_by User ID who created the batch
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BulkPayrollBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'pay_period',
        'filters',
        'total_employees',
        'total_payrolls',
        'processed_payrolls',
        'successful_payrolls',
        'failed_payrolls',
        'advances_created',
        'status',
        'errors',
        'summary',
        'current_employee',
        'current_allocation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'errors' => 'array',
            'summary' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user who created the batch
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if (! $this->total_payrolls || $this->total_payrolls === 0) {
            return 0.0;
        }

        return round(($this->processed_payrolls / $this->total_payrolls) * 100, 2);
    }

    /**
     * Check if batch is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if batch has errors
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get error count
     */
    public function getErrorCountAttribute(): int
    {
        return $this->errors ? count($this->errors) : 0;
    }
}
