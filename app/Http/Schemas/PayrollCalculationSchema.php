<?php

namespace App\Http\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PayrollCalculation",
 *     type="object",
 *     title="Payroll Calculation Result",
 *     description="Complete payroll calculation with tax breakdown",
 *     @OA\Property(property="gross_salary", type="number", format="float", example=50000, description="Monthly gross salary"),
 *     @OA\Property(property="total_income", type="number", format="float", example=55000, description="Total income including additional income"),
 *     @OA\Property(property="net_salary", type="number", format="float", example=48275, description="Net take-home salary"),
 *     @OA\Property(property="taxable_income", type="number", format="float", example=342000, description="Annual taxable income after deductions"),
 *     @OA\Property(property="income_tax", type="number", format="float", example=975, description="Monthly income tax amount"),
 *     @OA\Property(property="tax_year", type="integer", example=2025, description="Tax calculation year"),
 *     @OA\Property(
 *         property="deductions",
 *         type="object",
 *         description="Breakdown of all deductions",
 *         @OA\Property(property="personal_allowance", type="number", format="float", example=60000),
 *         @OA\Property(property="spouse_allowance", type="number", format="float", example=60000),
 *         @OA\Property(property="child_allowance", type="number", format="float", example=60000),
 *         @OA\Property(property="personal_expenses", type="number", format="float", example=60000),
 *         @OA\Property(property="provident_fund", type="number", format="float", example=18000),
 *         @OA\Property(property="additional_deductions", type="number", format="float", example=0),
 *         @OA\Property(property="total_deductions", type="number", format="float", example=258000)
 *     ),
 *     @OA\Property(
 *         property="social_security",
 *         type="object",
 *         description="Social security contribution breakdown",
 *         @OA\Property(property="employee_contribution", type="number", format="float", example=750),
 *         @OA\Property(property="employer_contribution", type="number", format="float", example=750),
 *         @OA\Property(property="total_contribution", type="number", format="float", example=1500)
 *     ),
 *     @OA\Property(
 *         property="tax_breakdown",
 *         type="array",
 *         description="Tax calculation breakdown by bracket",
 *         @OA\Items(
 *             @OA\Property(property="bracket_order", type="integer", example=1),
 *             @OA\Property(property="income_range", type="string", example="฿0 - ฿150,000"),
 *             @OA\Property(property="tax_rate", type="string", example="0%"),
 *             @OA\Property(property="taxable_income", type="number", format="float", example=150000),
 *             @OA\Property(property="tax_amount", type="number", format="float", example=0),
 *             @OA\Property(property="monthly_tax", type="number", format="float", example=0)
 *         )
 *     ),
 *     @OA\Property(
 *         property="formatted",
 *         type="object",
 *         description="Formatted currency values for display",
 *         @OA\Property(property="gross_salary", type="string", example="฿50,000.00"),
 *         @OA\Property(property="total_income", type="string", example="฿55,000.00"),
 *         @OA\Property(property="net_salary", type="string", example="฿48,275.00"),
 *         @OA\Property(property="income_tax", type="string", example="฿975.00"),
 *         @OA\Property(property="total_deductions", type="string", example="฿258,000.00"),
 *         @OA\Property(property="employee_ss_contribution", type="string", example="฿750.00")
 *     ),
 *     @OA\Property(
 *         property="ratios",
 *         type="object",
 *         description="Percentage ratios for analysis",
 *         @OA\Property(property="tax_rate", type="number", format="float", example=1.95, description="Effective tax rate as percentage"),
 *         @OA\Property(property="deduction_rate", type="number", format="float", example=43.0, description="Deduction rate as percentage of annual income"),
 *         @OA\Property(property="net_rate", type="number", format="float", example=87.77, description="Net salary rate as percentage of gross"),
 *         @OA\Property(property="ss_rate", type="number", format="float", example=1.5, description="Social security rate as percentage")
 *     ),
 *     @OA\Property(property="calculation_date", type="string", format="date-time", example="2025-08-07T10:30:00Z"),
 *     @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31", description="Pay period date"),
 *     @OA\Property(
 *         property="calculation_summary",
 *         type="object",
 *         description="Summary of total costs and deductions",
 *         @OA\Property(property="total_cost_to_employer", type="number", format="float", example=55750),
 *         @OA\Property(property="total_employee_deductions", type="number", format="float", example=1725),
 *         @OA\Property(property="take_home_percentage", type="number", format="float", example=87.77),
 *         @OA\Property(property="effective_tax_rate", type="number", format="float", example=1.95)
 *     )
 * )
 */
class PayrollCalculationSchema
{
    // This class exists solely for OpenAPI schema documentation
    // It doesn't contain any actual logic, just the schema definition
}