<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GrantItem',
    title: 'Grant Item',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'grant_id', type: 'integer', example: 1),
        new OA\Property(property: 'grant_position', type: 'string', example: 'Project Manager', nullable: true, description: 'Position title - must be unique within grant when combined with budget line code'),
        new OA\Property(property: 'grant_salary', type: 'number', format: 'float', example: 75000, nullable: true),
        new OA\Property(property: 'grant_benefit', type: 'number', format: 'float', example: 15000, nullable: true),
        new OA\Property(property: 'grant_level_of_effort', type: 'integer', example: 75, nullable: true),
        new OA\Property(property: 'grant_position_number', type: 'integer', example: 2, description: 'Number of people for this position', nullable: true),
        new OA\Property(property: 'budgetline_code', type: 'string', example: 'BL001', description: 'Budget line code for grant funding - must be unique within grant when combined with position'),
        new OA\Property(property: 'grant_cost_by_monthly', type: 'string', example: '7500', nullable: true),
        new OA\Property(property: 'grant_total_cost_by_person', type: 'string', example: '90000', nullable: true),
        new OA\Property(property: 'grant_benefit_fte', type: 'number', format: 'float', example: 0.75, nullable: true),
        new OA\Property(property: 'position_id', type: 'string', example: 'P123', nullable: true),
        new OA\Property(property: 'grant_total_amount', type: 'number', format: 'float', example: 90000, nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
    ]
)]
class GrantItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grant_id',
        'grant_position',
        'grant_salary',
        'grant_benefit',
        'grant_level_of_effort',
        'grant_position_number',
        'budgetline_code',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'grant_salary' => 'decimal:2',
        'grant_benefit' => 'decimal:2',
        'grant_level_of_effort' => 'decimal:2',
        'grant_position_number' => 'integer',
    ];

    public function grant()
    {
        return $this->belongsTo(Grant::class, 'grant_id');
    }

    public function employeeFundingAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class);
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        // Add validation before creating
        static::creating(function ($grantItem) {
            static::validateUniqueness($grantItem);
        });

        // Add validation before updating
        static::updating(function ($grantItem) {
            static::validateUniqueness($grantItem);
        });
    }

    /**
     * Validate that the combination of grant_position, budgetline_code, and grant_id is unique
     *
     * @throws ValidationException
     */
    protected static function validateUniqueness(GrantItem $grantItem): void
    {
        // Skip validation if any of the required fields are null
        if (is_null($grantItem->grant_position) || is_null($grantItem->budgetline_code) || is_null($grantItem->grant_id)) {
            return;
        }

        $query = static::where('grant_id', $grantItem->grant_id)
            ->where('grant_position', $grantItem->grant_position)
            ->where('budgetline_code', $grantItem->budgetline_code);

        // If updating, exclude the current record
        if ($grantItem->exists) {
            $query->where('id', '!=', $grantItem->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'grant_position' => [
                    'The combination of grant position "'.$grantItem->grant_position.
                    '" and budget line code "'.$grantItem->budgetline_code.
                    '" already exists for this grant.',
                ],
            ]);
        }
    }
}
