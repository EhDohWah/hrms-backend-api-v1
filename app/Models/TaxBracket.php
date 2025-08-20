<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="TaxBracket",
 *     type="object",
 *     title="Tax Bracket",
 *     description="Progressive income tax bracket configuration",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="min_income", type="number", format="float", example=150001, description="Minimum income for this bracket"),
 *     @OA\Property(property="max_income", type="number", format="float", example=300000, description="Maximum income for this bracket (null for highest bracket)"),
 *     @OA\Property(property="tax_rate", type="number", format="float", example=5.00, description="Tax rate as percentage"),
 *     @OA\Property(property="bracket_order", type="integer", example=2, description="Order of bracket in progression"),
 *     @OA\Property(property="effective_year", type="integer", example=2025, description="Year this bracket is effective"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Whether bracket is currently active"),
 *     @OA\Property(property="description", type="string", example="5% tax bracket - Income ฿150,001 to ฿300,000"),
 *     @OA\Property(property="created_by", type="string", example="admin@example.com"),
 *     @OA\Property(property="updated_by", type="string", example="admin@example.com"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="income_range", type="string", example="฿150,001 - ฿300,000", description="Formatted income range"),
 *     @OA\Property(property="formatted_rate", type="string", example="5%", description="Formatted tax rate")
 * )
 */
class TaxBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'min_income',
        'max_income',
        'tax_rate',
        'base_tax',
        'bracket_order',
        'effective_year',
        'is_active',
        'description',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'min_income' => 'decimal:2',
        'max_income' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'base_tax' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_year' => 'integer',
        'bracket_order' => 'integer'
    ];

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('effective_year', $year);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('bracket_order');
    }

    // Helper methods
    public function getFormattedRateAttribute(): string
    {
        return $this->tax_rate . '%';
    }

    public function getIncomeRangeAttribute(): string
    {
        $min = number_format($this->min_income);
        $max = $this->max_income ? number_format($this->max_income) : '∞';
        return "฿{$min} - ฿{$max}";
    }

    public function calculateTax(float $taxableAmount): float
    {
        return $taxableAmount * ($this->tax_rate / 100);
    }

    // Thai 2025 Official Tax Brackets Constants
    const THAI_2025_BRACKETS = [
        [
            'min_income' => 0,
            'max_income' => 150000,
            'tax_rate' => 0,
            'bracket_order' => 1,
            'thai_bracket_code' => 'B1_EXEMPT',
            'description' => '0% tax bracket - Income ฿0 to ฿150,000 (Tax Exempt)',
        ],
        [
            'min_income' => 150001,
            'max_income' => 300000,
            'tax_rate' => 5,
            'bracket_order' => 2,
            'thai_bracket_code' => 'B2_5PCT',
            'description' => '5% tax bracket - Income ฿150,001 to ฿300,000',
        ],
        [
            'min_income' => 300001,
            'max_income' => 500000,
            'tax_rate' => 10,
            'bracket_order' => 3,
            'thai_bracket_code' => 'B3_10PCT',
            'description' => '10% tax bracket - Income ฿300,001 to ฿500,000',
        ],
        [
            'min_income' => 500001,
            'max_income' => 750000,
            'tax_rate' => 15,
            'bracket_order' => 4,
            'thai_bracket_code' => 'B4_15PCT',
            'description' => '15% tax bracket - Income ฿500,001 to ฿750,000',
        ],
        [
            'min_income' => 750001,
            'max_income' => 1000000,
            'tax_rate' => 20,
            'bracket_order' => 5,
            'thai_bracket_code' => 'B5_20PCT',
            'description' => '20% tax bracket - Income ฿750,001 to ฿1,000,000',
        ],
        [
            'min_income' => 1000001,
            'max_income' => 2000000,
            'tax_rate' => 25,
            'bracket_order' => 6,
            'thai_bracket_code' => 'B6_25PCT',
            'description' => '25% tax bracket - Income ฿1,000,001 to ฿2,000,000',
        ],
        [
            'min_income' => 2000001,
            'max_income' => 5000000,
            'tax_rate' => 30,
            'bracket_order' => 7,
            'thai_bracket_code' => 'B7_30PCT',
            'description' => '30% tax bracket - Income ฿2,000,001 to ฿5,000,000',
        ],
        [
            'min_income' => 5000001,
            'max_income' => null, // Unlimited
            'tax_rate' => 35,
            'bracket_order' => 8,
            'thai_bracket_code' => 'B8_35PCT',
            'description' => '35% tax bracket - Income ฿5,000,001 and above (Highest Bracket)',
        ],
    ];

    // Get all active brackets for a specific year
    public static function getBracketsForYear(int $year)
    {
        return static::active()
            ->forYear($year)
            ->ordered()
            ->get();
    }

    // Get Thai compliant brackets for a specific year
    public static function getThaiCompliantBrackets(int $year)
    {
        return static::active()
            ->forYear($year)
            ->where('is_thai_compliant', true)
            ->ordered()
            ->get();
    }

    // Thai compliance validation
    public static function validateThaiCompliance(int $year = null): array
    {
        $year = $year ?: date('Y');
        $brackets = static::getBracketsForYear($year);
        $errors = [];
        $warnings = [];

        if ($brackets->isEmpty()) {
            $errors[] = "No tax brackets found for year {$year}";
            return ['is_compliant' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Check if we have the correct number of brackets (8 for Thai system)
        if ($brackets->count() !== 8) {
            $warnings[] = "Expected 8 tax brackets for Thai system, found {$brackets->count()}";
        }

        // Check bracket progression
        $expectedBrackets = self::THAI_2025_BRACKETS;
        foreach ($brackets as $index => $bracket) {
            $expected = $expectedBrackets[$index] ?? null;
            
            if (!$expected) {
                continue;
            }

            // Check bracket order
            if ($bracket->bracket_order !== $expected['bracket_order']) {
                $errors[] = "Bracket {$bracket->id} has incorrect order. Expected {$expected['bracket_order']}, got {$bracket->bracket_order}";
            }

            // Check tax rate
            if ($bracket->tax_rate != $expected['tax_rate']) {
                $errors[] = "Bracket {$bracket->id} has incorrect tax rate. Expected {$expected['tax_rate']}%, got {$bracket->tax_rate}%";
            }

            // Check income ranges
            if ($bracket->min_income != $expected['min_income']) {
                $errors[] = "Bracket {$bracket->id} has incorrect minimum income. Expected ฿{$expected['min_income']}, got ฿{$bracket->min_income}";
            }

            if ($bracket->max_income != $expected['max_income']) {
                $expectedMax = $expected['max_income'] ? "฿{$expected['max_income']}" : 'unlimited';
                $actualMax = $bracket->max_income ? "฿{$bracket->max_income}" : 'unlimited';
                $errors[] = "Bracket {$bracket->id} has incorrect maximum income. Expected {$expectedMax}, got {$actualMax}";
            }
        }

        // Check highest bracket is unlimited
        $highestBracket = $brackets->sortByDesc('bracket_order')->first();
        if ($highestBracket && $highestBracket->max_income !== null) {
            $errors[] = "Highest tax bracket must have unlimited maximum income (null)";
        }

        // Check highest bracket rate is 35%
        if ($highestBracket && $highestBracket->tax_rate != 35) {
            $errors[] = "Highest tax bracket must be 35% for Thai compliance, got {$highestBracket->tax_rate}%";
        }

        return [
            'is_compliant' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    // Calculate cumulative tax at minimum income for this bracket
    public function calculateCumulativeTaxAtMin(): float
    {
        $cumulativeTax = 0;
        
        // Get all brackets below this one
        $lowerBrackets = static::active()
            ->forYear($this->effective_year)
            ->where('bracket_order', '<', $this->bracket_order)
            ->ordered()
            ->get();

        foreach ($lowerBrackets as $bracket) {
            $bracketRange = ($bracket->max_income ?? $bracket->min_income) - $bracket->min_income;
            $cumulativeTax += $bracketRange * ($bracket->tax_rate / 100);
        }

        return $cumulativeTax;
    }

    // Get Thai 2025 default brackets for seeding
    public static function getThaiDefaults2025(int $year = 2025): array
    {
        $brackets = [];
        
        foreach (self::THAI_2025_BRACKETS as $bracketData) {
            $brackets[] = array_merge($bracketData, [
                'effective_year' => $year,
                'is_active' => true,
                'is_thai_compliant' => true,
                'thai_compliance_notes' => 'Official Thai Revenue Department tax bracket for 2025',
                'created_by' => 'system',
                'updated_by' => 'system',
            ]);
        }

        return $brackets;
    }
}
