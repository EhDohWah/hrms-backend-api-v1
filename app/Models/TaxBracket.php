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

    // Get all active brackets for a specific year
    public static function getBracketsForYear(int $year)
    {
        return static::active()
            ->forYear($year)
            ->ordered()
            ->get();
    }
}
