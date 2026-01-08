<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeParent',
    title: 'Employee Parent',
    description: 'Employee Parent model for Thai tax allowance calculations',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'สมใจ ใจดี'),
        new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '1950-01-01'),
        new OA\Property(property: 'relationship_type', type: 'string', example: 'father', enum: ['father', 'mother', 'stepfather', 'stepmother', 'adoptive_father', 'adoptive_mother']),
        new OA\Property(property: 'annual_income', type: 'number', format: 'float', example: 25000.00),
        new OA\Property(property: 'id_card_number', type: 'string', example: '1234567890123'),
        new OA\Property(property: 'address', type: 'string', example: '123 Main St, Bangkok'),
        new OA\Property(property: 'phone', type: 'string', example: '0812345678'),
        new OA\Property(property: 'is_dependent', type: 'boolean', example: true),
        new OA\Property(property: 'is_eligible_for_allowance', type: 'boolean', example: true),
        new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class EmployeeParent extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name',
        'date_of_birth',
        'relationship_type',
        'annual_income',
        'id_card_number',
        'address',
        'phone',
        'is_dependent',
        'is_eligible_for_allowance',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'annual_income' => 'decimal:2',
        'is_dependent' => 'boolean',
        'is_eligible_for_allowance' => 'boolean',
    ];

    // Thai tax law constants
    public const RELATIONSHIP_TYPES = [
        'father' => 'Father',
        'mother' => 'Mother',
        'stepfather' => 'Stepfather',
        'stepmother' => 'Stepmother',
        'adoptive_father' => 'Adoptive Father',
        'adoptive_mother' => 'Adoptive Mother',
    ];

    public const THAI_PARENT_ALLOWANCE_AGE_REQUIREMENT = 60;

    public const THAI_PARENT_ALLOWANCE_INCOME_LIMIT = 30000; // Annual income limit for allowance eligibility

    public const THAI_PARENT_ALLOWANCE_AMOUNT = 30000; // Allowance amount per eligible parent

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes
    public function scopeEligibleForAllowance($query)
    {
        return $query->where('is_eligible_for_allowance', true);
    }

    public function scopeByRelationshipType($query, string $type)
    {
        return $query->where('relationship_type', $type);
    }

    // Accessors & Mutators
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : 0;
    }

    public function getRelationshipTypeDisplayAttribute(): string
    {
        return self::RELATIONSHIP_TYPES[$this->relationship_type] ?? $this->relationship_type;
    }

    // Thai tax compliance methods
    public function checkThaiAllowanceEligibility(): array
    {
        $eligibility = [
            'is_eligible' => false,
            'reasons' => [],
            'allowance_amount' => 0,
        ];

        // Check age requirement (60+ years old)
        if ($this->age < self::THAI_PARENT_ALLOWANCE_AGE_REQUIREMENT) {
            $eligibility['reasons'][] = 'Parent must be at least '.self::THAI_PARENT_ALLOWANCE_AGE_REQUIREMENT.' years old';
        }

        // Check income requirement (annual income < 30,000 baht)
        if ($this->annual_income >= self::THAI_PARENT_ALLOWANCE_INCOME_LIMIT) {
            $eligibility['reasons'][] = "Parent's annual income must be less than ฿".number_format(self::THAI_PARENT_ALLOWANCE_INCOME_LIMIT);
        }

        // Check if parent is marked as dependent
        if (! $this->is_dependent) {
            $eligibility['reasons'][] = 'Parent must be marked as dependent';
        }

        // If all requirements are met
        if (empty($eligibility['reasons'])) {
            $eligibility['is_eligible'] = true;
            $eligibility['allowance_amount'] = self::THAI_PARENT_ALLOWANCE_AMOUNT;
        }

        return $eligibility;
    }

    public function updateThaiAllowanceEligibility(): void
    {
        $eligibility = $this->checkThaiAllowanceEligibility();
        $this->update(['is_eligible_for_allowance' => $eligibility['is_eligible']]);
    }

    // Validation rules for Thai compliance
    public static function getThaiValidationRules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'relationship_type' => 'required|in:'.implode(',', array_keys(self::RELATIONSHIP_TYPES)),
            'annual_income' => 'required|numeric|min:0|max:999999.99',
            'id_card_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'is_dependent' => 'boolean',
        ];
    }

    // Model events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-update eligibility when saving
            $eligibility = $model->checkThaiAllowanceEligibility();
            $model->is_eligible_for_allowance = $eligibility['is_eligible'];
        });
    }
}
