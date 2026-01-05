<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Tax Calculations",
 *     description="API Endpoints for Thai Personal Income Tax calculations and payroll processing. All calculations follow Thai Revenue Department regulations and official 2025 tax brackets. The system implements the mandatory Thai tax calculation sequence: (1) Employment deductions first, (2) Personal allowances second, (3) Progressive tax calculation, (4) Social Security contributions."
 * )
 */

/**
 * Thai Personal Income Tax Calculation Controller
 *
 * This controller implements the complete Thai Personal Income Tax system following
 * Thai Revenue Department regulations and official 2025 tax brackets.
 *
 * THAI REVENUE DEPARTMENT COMPLIANCE:
 *
 * 1. CALCULATION SEQUENCE (MANDATORY):
 *    - Step 1: Employment Income Deductions (50% of income, max ฿100,000) - APPLIED FIRST
 *    - Step 2: Personal Allowances (฿60,000 + spouse + children + parents) - APPLIED SECOND
 *    - Step 3: Progressive Tax (8 brackets: 0%, 5%, 10%, 15%, 20%, 25%, 30%, 35%)
 *    - Step 4: Social Security (5% rate, ฿750 monthly cap) - CALCULATED SEPARATELY
 *
 * 2. OFFICIAL 2025 TAX BRACKETS:
 *    - ฿0 - ฿150,000: 0% (tax-exempt)
 *    - ฿150,001 - ฿300,000: 5%
 *    - ฿300,001 - ฿500,000: 10%
 *    - ฿500,001 - ฿750,000: 15%
 *    - ฿750,001 - ฿1,000,000: 20%
 *    - ฿1,000,001 - ฿2,000,000: 25%
 *    - ฿2,000,001 - ฿5,000,000: 30%
 *    - ฿5,000,001+: 35%
 *
 * 3. PERSONAL ALLOWANCES (2025):
 *    - Personal: ฿60,000 per taxpayer
 *    - Spouse: ฿60,000 (if spouse has no income)
 *    - Children: ฿30,000 first child, ฿60,000 subsequent (born 2018+)
 *    - Parents: ฿30,000 per eligible parent (age 60+, income < ฿30,000)
 *    - Senior Citizen: ฿190,000 additional (taxpayer age 65+)
 *
 * 4. SOCIAL SECURITY FUND:
 *    - Rate: 5% (mandatory, non-negotiable)
 *    - Salary Range: ฿1,650 - ฿15,000 monthly
 *    - Maximum: ฿750 monthly, ฿9,000 annually
 *    - Employer Matching: Required
 *
 * 5. COMPLIANCE FEATURES:
 *    - Comprehensive audit logging
 *    - Thai Revenue Department validation
 *    - Official report generation
 *    - Law reference documentation
 *
 * LAW REFERENCES:
 * - Revenue Code Section 42(1): Employment deductions
 * - Revenue Code Section 42(2-6): Personal allowances
 * - Revenue Code Section 48: Progressive tax rates
 * - Social Security Act: SSF contributions
 *
 * @version 1.0 - Thai Revenue Department Compliant
 */
class TaxCalculationController extends Controller
{
    protected $taxCalculationService;

    public function __construct(TaxCalculationService $taxCalculationService)
    {
        $this->taxCalculationService = $taxCalculationService;
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/payroll",
     *     summary="Calculate Thai Revenue Department compliant payroll",
     *     description="Calculate complete payroll following Thai Revenue Department mandatory sequence: (1) Employment income deductions 50% max ฿100,000, (2) Personal allowances ฿60,000 + spouse + children + parents, (3) Progressive tax using official 8-bracket structure 0%-35%, (4) Social Security 5% capped at ฿750/month. Includes comprehensive audit logging and Thai compliance validation.",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "gross_salary"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="additional_income",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="type", type="string", example="bonus"),
     *                     @OA\Property(property="amount", type="number", example=5000),
     *                     @OA\Property(property="description", type="string", example="Performance bonus")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="additional_deductions",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="type", type="string", example="loan"),
     *                     @OA\Property(property="amount", type="number", example=2000),
     *                     @OA\Property(property="description", type="string", example="Company loan repayment")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Thai compliant payroll calculated successfully with full audit trail",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="gross_salary", type="number", example=30000, description="Monthly gross salary"),
     *                 @OA\Property(property="total_income", type="number", example=360000, description="Annual total income"),
     *                 @OA\Property(property="taxable_income", type="number", example=160000, description="Annual taxable income after all deductions"),
     *                 @OA\Property(property="income_tax", type="number", example=58.33, description="Monthly income tax (Thai compliant calculation)"),
     *                 @OA\Property(property="net_salary", type="number", example=28691.67, description="Monthly net salary after all deductions"),
     *                 @OA\Property(property="tax_year", type="integer", example=2025, description="Tax year for calculation"),
     *                 @OA\Property(property="is_thai_compliant", type="boolean", example=true, description="Confirms calculation follows Thai Revenue Department sequence"),
     *                 @OA\Property(property="compliance_notes", type="string", example="Calculation follows Thai Revenue Department sequence: Employment deductions first, then personal allowances"),
     *                 @OA\Property(
     *                     property="deductions",
     *                     type="object",
     *                     description="Thai Revenue Department compliant deduction structure",
     *                     @OA\Property(property="employment_deductions", type="number", example=100000, description="Step 1: Employment income deductions (50% max ฿100,000)"),
     *                     @OA\Property(property="employment_deduction_rate", type="number", example=50, description="Employment deduction rate (50%)"),
     *                     @OA\Property(property="employment_deduction_calculated", type="number", example=180000, description="Calculated amount before cap"),
     *                     @OA\Property(property="employment_deduction_max", type="number", example=100000, description="Maximum allowed by Thai law"),
     *                     @OA\Property(property="personal_allowances", type="number", example=100000, description="Step 2: Total personal allowances"),
     *                     @OA\Property(property="personal_allowance", type="number", example=60000, description="Personal allowance ฿60,000"),
     *                     @OA\Property(property="spouse_allowance", type="number", example=0, description="Spouse allowance ฿60,000 (if applicable)"),
     *                     @OA\Property(property="child_allowance", type="number", example=30000, description="Child allowances ฿30,000 first, ฿60,000 subsequent (2018+)"),
     *                     @OA\Property(property="parent_allowance", type="number", example=0, description="Parent allowances ฿30,000 each (age 60+, income < ฿30,000)"),
     *                     @OA\Property(property="senior_citizen_allowance", type="number", example=0, description="Senior citizen allowance ฿190,000 (age 65+)"),
     *                     @OA\Property(property="total_deductions", type="number", example=200000, description="Total deductions following Thai sequence")
     *                 ),
     *                 @OA\Property(
     *                     property="social_security",
     *                     type="object",
     *                     description="Thai Social Security Fund contributions (5% mandatory)",
     *                     @OA\Property(property="gross_salary", type="number", example=30000, description="Gross salary for SSF calculation"),
     *                     @OA\Property(property="effective_salary", type="number", example=15000, description="Salary after SSF caps (฿1,650-฿15,000)"),
     *                     @OA\Property(property="ssf_rate", type="number", example=5, description="SSF rate (5% mandatory)"),
     *                     @OA\Property(property="employee_contribution", type="number", example=750, description="Employee SSF contribution (max ฿750/month)"),
     *                     @OA\Property(property="employer_contribution", type="number", example=750, description="Employer matching contribution"),
     *                     @OA\Property(property="annual_employee_contribution", type="number", example=9000, description="Annual employee contribution (max ฿9,000)"),
     *                     @OA\Property(property="is_salary_capped", type="boolean", example=true, description="Whether salary exceeded ฿15,000 cap"),
     *                     @OA\Property(property="is_contribution_capped", type="boolean", example=true, description="Whether contribution hit ฿750 cap")
     *                 ),
     *                 @OA\Property(
     *                     property="tax_breakdown",
     *                     type="array",
     *                     description="Progressive tax calculation by bracket",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="bracket_order", type="integer", example=2, description="Tax bracket order"),
     *                         @OA\Property(property="income_range", type="string", example="฿150,001 - ฿300,000", description="Income range for this bracket"),
     *                         @OA\Property(property="tax_rate", type="string", example="5%", description="Tax rate for this bracket"),
     *                         @OA\Property(property="taxable_income", type="number", example=10000, description="Income taxed in this bracket"),
     *                         @OA\Property(property="tax_amount", type="number", example=500, description="Tax amount from this bracket"),
     *                         @OA\Property(property="monthly_tax", type="number", example=41.67, description="Monthly tax from this bracket")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function calculatePayroll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'gross_salary' => 'required|numeric|min:0',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
                'additional_income' => 'nullable|array',
                'additional_income.*.type' => 'required_with:additional_income|string',
                'additional_income.*.amount' => 'required_with:additional_income|numeric|min:0',
                'additional_income.*.description' => 'nullable|string',
                'additional_deductions' => 'nullable|array',
                'additional_deductions.*.type' => 'required_with:additional_deductions|string',
                'additional_deductions.*.amount' => 'required_with:additional_deductions|numeric|min:0',
                'additional_deductions.*.description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Initialize tax calculation service with the specified year
            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            // Get employee data for tax calculation with relationships
            $employee = \App\Models\Employee::with(['employeeChildren'])->findOrFail($request->employee_id);
            $employeeData = [
                'has_spouse' => $employee->has_spouse,
                'children' => $employee->employeeChildren->count(),
                'eligible_parents' => $employee->eligible_parents_count,
                'employee_status' => $employee->status, // For provident fund calculation
                'pf_contribution_annual' => 0, // Legacy field, now calculated based on status
            ];

            // Calculate payroll using new method with global toggle control
            $payrollData = $taxService->calculateEmployeeTax(
                $request->gross_salary,
                $employeeData
            );

            // Add additional income and deductions if provided (for backward compatibility)
            $additionalIncome = $request->get('additional_income', []);
            $additionalDeductions = $request->get('additional_deductions', []);

            if (! empty($additionalIncome) || ! empty($additionalDeductions)) {
                $payrollData['additional_income'] = $additionalIncome;
                $payrollData['additional_deductions'] = $additionalDeductions;
                $payrollData['note'] = 'Additional income and deductions provided but not calculated in new method. Use legacy endpoint if needed.';
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll calculated successfully',
                'data' => $payrollData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate payroll',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/income-tax",
     *     summary="Calculate Thai progressive income tax",
     *     description="Calculate progressive income tax using official Thai Revenue Department 8-bracket structure: 0% (฿0-150k), 5% (฿150k-300k), 10% (฿300k-500k), 15% (฿500k-750k), 20% (฿750k-1M), 25% (฿1M-2M), 30% (฿2M-5M), 35% (฿5M+). Returns detailed breakdown by bracket with Thai law references.",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"taxable_income"},
     *
     *             @OA\Property(property="taxable_income", type="number", example=600000),
     *             @OA\Property(property="tax_year", type="integer", example=2025)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Income tax calculated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Income tax calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="taxable_income", type="number", example=600000),
     *                 @OA\Property(property="annual_tax", type="number", example=15000),
     *                 @OA\Property(property="monthly_tax", type="number", example=1250),
     *                 @OA\Property(property="effective_rate", type="number", example=2.5),
     *                 @OA\Property(property="tax_breakdown", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function calculateIncomeTax(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'taxable_income' => 'required|numeric|min:0',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            $taxableIncome = $request->taxable_income;
            $monthlyTax = $taxService->calculateProgressiveIncomeTax($taxableIncome);
            $annualTax = $monthlyTax * 12;
            $effectiveRate = $taxableIncome > 0 ? ($annualTax / $taxableIncome) * 100 : 0;

            // Get detailed breakdown
            $reflection = new \ReflectionClass($taxService);
            $method = $reflection->getMethod('getTaxBreakdown');
            $method->setAccessible(true);
            $taxBreakdown = $method->invoke($taxService, $taxableIncome);

            return response()->json([
                'success' => true,
                'message' => 'Income tax calculated successfully',
                'data' => [
                    'taxable_income' => $taxableIncome,
                    'annual_tax' => $annualTax,
                    'monthly_tax' => $monthlyTax,
                    'effective_rate' => round($effectiveRate, 2),
                    'tax_breakdown' => $taxBreakdown,
                    'tax_year' => $taxYear,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate income tax',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/annual-summary",
     *     summary="Calculate annual tax summary for an employee",
     *     description="Calculate annual tax liability and compare with taxes paid",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "monthly_payrolls"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="monthly_payrolls",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="month", type="integer", example=1),
     *                     @OA\Property(property="total_income", type="number", example=55000),
     *                     @OA\Property(property="total_deductions", type="number", example=15000),
     *                     @OA\Property(property="income_tax", type="number", example=1250)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Annual tax summary calculated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Annual tax summary calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_income", type="number", example=660000),
     *                 @OA\Property(property="total_deductions", type="number", example=180000),
     *                 @OA\Property(property="taxable_income", type="number", example=480000),
     *                 @OA\Property(property="tax_liability", type="number", example=15000),
     *                 @OA\Property(property="tax_paid", type="number", example=15000),
     *                 @OA\Property(property="tax_difference", type="number", example=0),
     *                 @OA\Property(property="refund_due", type="number", example=0),
     *                 @OA\Property(property="additional_tax_due", type="number", example=0)
     *             )
     *         )
     *     )
     * )
     */
    public function calculateAnnualSummary(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
                'monthly_payrolls' => 'required|array|min:1',
                'monthly_payrolls.*.month' => 'required|integer|between:1,12',
                'monthly_payrolls.*.total_income' => 'required|numeric|min:0',
                'monthly_payrolls.*.total_deductions' => 'required|numeric|min:0',
                'monthly_payrolls.*.income_tax' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            $annualSummary = $taxService->calculateAnnualTax(
                $request->employee_id,
                $request->monthly_payrolls
            );

            return response()->json([
                'success' => true,
                'message' => 'Annual tax summary calculated successfully',
                'data' => $annualSummary,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate annual tax summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/validate-inputs",
     *     summary="Validate tax calculation inputs",
     *     description="Validate inputs before performing tax calculations",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="additional_income", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="additional_deductions", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Inputs validated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inputs are valid"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function validateInputs(Request $request)
    {
        try {
            $taxService = new TaxCalculationService;
            $errors = $taxService->validateCalculationInputs($request->all());

            $isValid = empty($errors);

            return response()->json([
                'success' => $isValid,
                'message' => $isValid ? 'Inputs are valid' : 'Validation errors found',
                'errors' => $errors,
            ], $isValid ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate inputs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/compliance-check",
     *     summary="Thai Revenue Department compliance validation",
     *     description="Comprehensive validation against Thai Revenue Department requirements: (1) Verifies employment deductions applied first (50% max ฿100k), (2) Confirms personal allowances applied second, (3) Validates Social Security rate exactly 5%, (4) Checks tax bracket structure matches official 8-bracket system, (5) Ensures calculation sequence compliance. Returns detailed compliance score and specific error/warning messages.",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "gross_salary"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="tax_year", type="integer", example=2025)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Compliance check completed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compliance check completed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="is_compliant", type="boolean", example=true),
     *                 @OA\Property(property="compliance_score", type="number", example=100),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="warnings", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="tax_year", type="integer", example=2025),
     *                 @OA\Property(property="validation_date", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function complianceCheck(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'gross_salary' => 'required|numeric|min:0',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            // Perform a full calculation to check compliance
            $payrollData = $taxService->calculatePayroll(
                $request->employee_id,
                $request->gross_salary,
                [],
                []
            );

            // Validate Thai compliance
            $complianceResults = $taxService->validateThaiCompliance($payrollData);

            return response()->json([
                'success' => true,
                'message' => 'Compliance check completed successfully',
                'data' => $complianceResults,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform compliance check',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/thai-report",
     *     summary="Generate official Thai Revenue Department tax calculation report",
     *     description="Generate comprehensive tax calculation report in official Thai Revenue Department format with complete calculation sequence, law references, and compliance validation. Report includes: (1) Employee information, (2) Step-by-step calculation sequence per Thai law, (3) Detailed breakdown of employment deductions and personal allowances, (4) Progressive tax calculation with bracket details, (5) Social Security contributions, (6) Complete Thai law references (Revenue Code Sections 42, 48, Social Security Act), (7) Compliance status and validation results.",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "gross_salary"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="additional_income",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="type", type="string", example="bonus"),
     *                     @OA\Property(property="amount", type="number", example=5000),
     *                     @OA\Property(property="description", type="string", example="Performance bonus")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="additional_deductions",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="type", type="string", example="insurance"),
     *                     @OA\Property(property="amount", type="number", example=2000),
     *                     @OA\Property(property="description", type="string", example="Health insurance premium")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Thai tax report generated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Thai tax report generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="report_title", type="string", example="Thai Personal Income Tax Calculation Report"),
     *                 @OA\Property(property="report_date", type="string", example="15/08/2025 14:30:25"),
     *                 @OA\Property(property="tax_year", type="integer", example=2025),
     *                 @OA\Property(property="generated_by", type="string", example="HRMS Thai Tax Compliance System"),
     *                 @OA\Property(property="system_version", type="string", example="1.0 - Thai Revenue Department Compliant"),
     *                 @OA\Property(
     *                     property="employee_info",
     *                     type="object",
     *                     @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="tax_number", type="string", example="1234567890123")
     *                 ),
     *                 @OA\Property(
     *                     property="calculation_sequence",
     *                     type="object",
     *                     description="Thai Revenue Department mandatory calculation sequence",
     *                     @OA\Property(
     *                         property="step_1",
     *                         type="object",
     *                         @OA\Property(property="title", type="string", example="Employment Income Deductions (Applied First)"),
     *                         @OA\Property(property="rate", type="string", example="50%"),
     *                         @OA\Property(property="calculated", type="string", example="180,000.00"),
     *                         @OA\Property(property="maximum_allowed", type="string", example="100,000.00"),
     *                         @OA\Property(property="actual_deduction", type="string", example="100,000.00"),
     *                         @OA\Property(property="law_reference", type="string", example="Revenue Code Section 42(1)")
     *                     ),
     *                     @OA\Property(
     *                         property="step_2",
     *                         type="object",
     *                         @OA\Property(property="title", type="string", example="Personal Allowances (Applied After Employment Deductions)"),
     *                         @OA\Property(property="personal_allowance", type="string", example="60,000.00"),
     *                         @OA\Property(property="spouse_allowance", type="string", example="0.00"),
     *                         @OA\Property(property="child_allowance", type="string", example="30,000.00"),
     *                         @OA\Property(property="parent_allowance", type="string", example="0.00"),
     *                         @OA\Property(property="senior_citizen_allowance", type="string", example="0.00"),
     *                         @OA\Property(property="total_allowances", type="string", example="90,000.00"),
     *                         @OA\Property(property="law_reference", type="string", example="Revenue Code Section 42(2-6)")
     *                     ),
     *                     @OA\Property(
     *                         property="step_3",
     *                         type="object",
     *                         @OA\Property(property="title", type="string", example="Progressive Tax Calculation"),
     *                         @OA\Property(property="taxable_income", type="string", example="170,000.00"),
     *                         @OA\Property(property="annual_tax", type="string", example="1,000.00"),
     *                         @OA\Property(property="monthly_tax", type="string", example="83.33"),
     *                         @OA\Property(property="law_reference", type="string", example="Revenue Code Section 48")
     *                     ),
     *                     @OA\Property(
     *                         property="step_4",
     *                         type="object",
     *                         @OA\Property(property="title", type="string", example="Social Security Contributions (Separate from Income Tax)"),
     *                         @OA\Property(property="employee_contribution", type="string", example="750.00"),
     *                         @OA\Property(property="employer_contribution", type="string", example="750.00"),
     *                         @OA\Property(property="rate", type="string", example="5%"),
     *                         @OA\Property(property="law_reference", type="string", example="Social Security Act")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     description="Complete calculation summary",
     *                     @OA\Property(property="gross_salary_monthly", type="string", example="30,000.00"),
     *                     @OA\Property(property="gross_salary_annual", type="string", example="360,000.00"),
     *                     @OA\Property(property="employment_deductions", type="string", example="100,000.00"),
     *                     @OA\Property(property="personal_allowances", type="string", example="90,000.00"),
     *                     @OA\Property(property="total_deductions", type="string", example="190,000.00"),
     *                     @OA\Property(property="taxable_income", type="string", example="170,000.00"),
     *                     @OA\Property(property="income_tax_annual", type="string", example="1,000.00"),
     *                     @OA\Property(property="income_tax_monthly", type="string", example="83.33"),
     *                     @OA\Property(property="social_security_employee", type="string", example="750.00"),
     *                     @OA\Property(property="net_salary", type="string", example="28,166.67")
     *                 ),
     *                 @OA\Property(
     *                     property="compliance_status",
     *                     type="object",
     *                     description="Thai Revenue Department compliance validation results",
     *                     @OA\Property(property="is_compliant", type="boolean", example=true),
     *                     @OA\Property(property="compliance_score", type="number", example=100),
     *                     @OA\Property(property="errors", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="warnings", type="array", @OA\Items(type="string"))
     *                 ),
     *                 @OA\Property(
     *                     property="thai_law_references",
     *                     type="object",
     *                     description="Complete Thai Revenue Department law references",
     *                     @OA\Property(property="Revenue Code Section 42(1)", type="string", example="Employment income deductions - 50% of gross income, maximum ฿100,000"),
     *                     @OA\Property(property="Revenue Code Section 42(2)", type="string", example="Personal allowance - ฿60,000 per taxpayer"),
     *                     @OA\Property(property="Revenue Code Section 42(3)", type="string", example="Spouse allowance - ฿60,000 (if spouse has no income)"),
     *                     @OA\Property(property="Revenue Code Section 42(4)", type="string", example="Child allowances - ฿30,000 first child, ฿60,000 subsequent (born 2018+)"),
     *                     @OA\Property(property="Revenue Code Section 42(5)", type="string", example="Parent allowance - ฿30,000 per eligible parent (age 60+, income < ฿30,000)"),
     *                     @OA\Property(property="Revenue Code Section 42(6)", type="string", example="Senior citizen allowance - ฿190,000 additional (taxpayer age 65+)"),
     *                     @OA\Property(property="Revenue Code Section 48", type="string", example="Progressive tax rates - 0%, 5%, 10%, 15%, 20%, 25%, 30%, 35%"),
     *                     @OA\Property(property="Social Security Act", type="string", example="SSF contributions - 5% rate, ฿750 monthly maximum")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function generateThaiReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'gross_salary' => 'required|numeric|min:0',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
                'additional_income' => 'nullable|array',
                'additional_income.*.type' => 'required_with:additional_income|string',
                'additional_income.*.amount' => 'required_with:additional_income|numeric|min:0',
                'additional_income.*.description' => 'nullable|string',
                'additional_deductions' => 'nullable|array',
                'additional_deductions.*.type' => 'required_with:additional_deductions|string',
                'additional_deductions.*.amount' => 'required_with:additional_deductions|numeric|min:0',
                'additional_deductions.*.description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            // Calculate payroll with all details
            $payrollData = $taxService->calculatePayroll(
                $request->employee_id,
                $request->gross_salary,
                $request->get('additional_income', []),
                $request->get('additional_deductions', [])
            );

            // Generate Thai compliant report
            $thaiReport = $taxService->generateThaiTaxReport($request->employee_id, $payrollData);

            return response()->json([
                'success' => true,
                'message' => 'Thai tax report generated successfully',
                'data' => $thaiReport,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Thai tax report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
