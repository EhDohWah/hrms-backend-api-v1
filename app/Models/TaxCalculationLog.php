<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="TaxCalculationLog",
 *     title="Tax Calculation Log",
 *     description="Audit log for all tax calculations performed in the system",
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employee_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="calculation_type", type="string", enum={"payroll", "annual_summary", "income_tax", "compliance_check"}, example="payroll"),
 *     @OA\Property(property="tax_year", type="integer", example=2025),
 *     @OA\Property(property="gross_salary", type="number", format="float", example=50000.00),
 *     @OA\Property(property="total_income", type="number", format="float", example=600000.00),
 *     @OA\Property(property="employment_deductions", type="number", format="float", example=100000.00),
 *     @OA\Property(property="personal_allowances", type="number", format="float", example=150000.00),
 *     @OA\Property(property="total_deductions", type="number", format="float", example=250000.00),
 *     @OA\Property(property="taxable_income", type="number", format="float", example=350000.00),
 *     @OA\Property(property="income_tax_annual", type="number", format="float", example=10000.00),
 *     @OA\Property(property="income_tax_monthly", type="number", format="float", example=833.33),
 *     @OA\Property(property="social_security_employee", type="number", format="float", example=750.00),
 *     @OA\Property(property="social_security_employer", type="number", format="float", example=750.00),
 *     @OA\Property(property="net_salary", type="number", format="float", example=48416.67),
 *     @OA\Property(property="calculation_details", type="object", description="JSON object containing detailed calculation breakdown"),
 *     @OA\Property(property="input_parameters", type="object", description="JSON object containing input parameters used"),
 *     @OA\Property(property="tax_bracket_breakdown", type="array", @OA\Items(type="object"), description="Array of tax bracket calculations"),
 *     @OA\Property(property="is_thai_compliant", type="boolean", example=true),
 *     @OA\Property(property="compliance_notes", type="string", example="Calculation follows Thai Revenue Department guidelines"),
 *     @OA\Property(property="calculated_by", type="string", example="admin@example.com"),
 *     @OA\Property(property="calculated_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TaxCalculationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'calculation_type',
        'tax_year',
        'gross_salary',
        'total_income',
        'employment_deductions',
        'personal_allowances',
        'total_deductions',
        'taxable_income',
        'income_tax_annual',
        'income_tax_monthly',
        'social_security_employee',
        'social_security_employer',
        'net_salary',
        'calculation_details',
        'input_parameters',
        'tax_bracket_breakdown',
        'is_thai_compliant',
        'compliance_notes',
        'calculated_by',
        'calculated_at',
    ];

    protected $casts = [
        'tax_year' => 'integer',
        'gross_salary' => 'decimal:2',
        'total_income' => 'decimal:2',
        'employment_deductions' => 'decimal:2',
        'personal_allowances' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'income_tax_annual' => 'decimal:2',
        'income_tax_monthly' => 'decimal:2',
        'social_security_employee' => 'decimal:2',
        'social_security_employer' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'calculation_details' => 'array',
        'input_parameters' => 'array',
        'tax_bracket_breakdown' => 'array',
        'is_thai_compliant' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    // Calculation types
    public const TYPE_PAYROLL = 'payroll';

    public const TYPE_ANNUAL_SUMMARY = 'annual_summary';

    public const TYPE_INCOME_TAX = 'income_tax';

    public const TYPE_COMPLIANCE_CHECK = 'compliance_check';

    public const CALCULATION_TYPES = [
        self::TYPE_PAYROLL => 'Payroll Calculation',
        self::TYPE_ANNUAL_SUMMARY => 'Annual Tax Summary',
        self::TYPE_INCOME_TAX => 'Income Tax Calculation',
        self::TYPE_COMPLIANCE_CHECK => 'Compliance Check',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('calculation_type', $type);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('tax_year', $year);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeThaiCompliant($query)
    {
        return $query->where('is_thai_compliant', true);
    }

    public function scopeNonCompliant($query)
    {
        return $query->where('is_thai_compliant', false);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('calculated_at', '>=', Carbon::now()->subDays($days));
    }

    // Accessors
    public function getCalculationTypeDisplayAttribute(): string
    {
        return self::CALCULATION_TYPES[$this->calculation_type] ?? $this->calculation_type;
    }

    public function getEffectiveTaxRateAttribute(): float
    {
        if ($this->total_income <= 0) {
            return 0;
        }

        return round(($this->income_tax_annual / $this->total_income) * 100, 2);
    }

    public function getFormattedCalculatedAtAttribute(): string
    {
        return $this->calculated_at->format('d/m/Y H:i:s');
    }

    // Static methods for creating logs
    public static function logPayrollCalculation(
        int $employeeId,
        array $calculationData,
        array $inputParameters,
        ?string $calculatedBy = null
    ): self {
        return self::create([
            'employee_id' => $employeeId,
            'calculation_type' => self::TYPE_PAYROLL,
            'tax_year' => $calculationData['tax_year'],
            'gross_salary' => $calculationData['gross_salary'],
            'total_income' => $calculationData['total_income'],
            'employment_deductions' => $calculationData['deductions']['employment_deductions'] ?? 0,
            'personal_allowances' => $calculationData['deductions']['personal_allowances'] ?? 0,
            'total_deductions' => $calculationData['deductions']['total_deductions'],
            'taxable_income' => $calculationData['taxable_income'],
            'income_tax_annual' => $calculationData['income_tax'] * 12,
            'income_tax_monthly' => $calculationData['income_tax'],
            'social_security_employee' => $calculationData['social_security']['employee_contribution'],
            'social_security_employer' => $calculationData['social_security']['employer_contribution'],
            'net_salary' => $calculationData['net_salary'],
            'calculation_details' => $calculationData,
            'input_parameters' => $inputParameters,
            'tax_bracket_breakdown' => $calculationData['tax_breakdown'] ?? [],
            'is_thai_compliant' => true,
            'compliance_notes' => 'Calculation follows Thai Revenue Department sequence and regulations',
            'calculated_by' => $calculatedBy,
            'calculated_at' => Carbon::now(),
        ]);
    }

    public static function logIncomeTaxCalculation(
        int $employeeId,
        float $taxableIncome,
        float $annualTax,
        array $taxBreakdown,
        int $taxYear,
        ?string $calculatedBy = null
    ): self {
        return self::create([
            'employee_id' => $employeeId,
            'calculation_type' => self::TYPE_INCOME_TAX,
            'tax_year' => $taxYear,
            'total_income' => $taxableIncome,
            'taxable_income' => $taxableIncome,
            'income_tax_annual' => $annualTax,
            'income_tax_monthly' => $annualTax / 12,
            'calculation_details' => [
                'taxable_income' => $taxableIncome,
                'annual_tax' => $annualTax,
                'monthly_tax' => $annualTax / 12,
                'effective_rate' => $taxableIncome > 0 ? ($annualTax / $taxableIncome) * 100 : 0,
            ],
            'input_parameters' => ['taxable_income' => $taxableIncome],
            'tax_bracket_breakdown' => $taxBreakdown,
            'is_thai_compliant' => true,
            'compliance_notes' => 'Income tax calculated using Thai progressive tax brackets',
            'calculated_by' => $calculatedBy,
            'calculated_at' => Carbon::now(),
        ]);
    }

    public static function logComplianceCheck(
        int $employeeId,
        array $complianceResults,
        int $taxYear,
        ?string $calculatedBy = null
    ): self {
        return self::create([
            'employee_id' => $employeeId,
            'calculation_type' => self::TYPE_COMPLIANCE_CHECK,
            'tax_year' => $taxYear,
            'calculation_details' => $complianceResults,
            'input_parameters' => ['compliance_check' => true],
            'is_thai_compliant' => $complianceResults['is_compliant'] ?? false,
            'compliance_notes' => $complianceResults['notes'] ?? 'Compliance check performed',
            'calculated_by' => $calculatedBy,
            'calculated_at' => Carbon::now(),
        ]);
    }

    // Validation rules
    public static function getValidationRules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'calculation_type' => 'required|in:'.implode(',', array_keys(self::CALCULATION_TYPES)),
            'tax_year' => 'required|integer|min:2000|max:2100',
            'gross_salary' => 'nullable|numeric|min:0',
            'total_income' => 'required|numeric|min:0',
            'taxable_income' => 'required|numeric|min:0',
            'income_tax_annual' => 'required|numeric|min:0',
            'income_tax_monthly' => 'required|numeric|min:0',
            'is_thai_compliant' => 'boolean',
            'calculated_by' => 'nullable|string|max:100',
        ];
    }
}
