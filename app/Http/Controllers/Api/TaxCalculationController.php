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
 *     description="API Endpoints for tax calculations and payroll processing"
 * )
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
     *     summary="Calculate comprehensive payroll for an employee",
     *     description="Calculate complete payroll including taxes, deductions, and net salary",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "gross_salary"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="additional_income",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="bonus"),
     *                     @OA\Property(property="amount", type="number", example=5000),
     *                     @OA\Property(property="description", type="string", example="Performance bonus")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="additional_deductions",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="loan"),
     *                     @OA\Property(property="amount", type="number", example=2000),
     *                     @OA\Property(property="description", type="string", example="Company loan repayment")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="gross_salary", type="number", example=50000),
     *                 @OA\Property(property="total_income", type="number", example=55000),
     *                 @OA\Property(property="taxable_income", type="number", example=480000),
     *                 @OA\Property(property="income_tax", type="number", example=1000),
     *                 @OA\Property(property="net_salary", type="number", example=48000),
     *                 @OA\Property(
     *                     property="deductions",
     *                     type="object",
     *                     @OA\Property(property="personal_allowance", type="number", example=60000),
     *                     @OA\Property(property="total_deductions", type="number", example=180000)
     *                 ),
     *                 @OA\Property(
     *                     property="social_security",
     *                     type="object",
     *                     @OA\Property(property="employee_contribution", type="number", example=750),
     *                     @OA\Property(property="employer_contribution", type="number", example=750)
     *                 ),
     *                 @OA\Property(property="tax_breakdown", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
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
                'additional_deductions.*.description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Initialize tax calculation service with the specified year
            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            // Calculate payroll
            $payrollData = $taxService->calculatePayroll(
                $request->employee_id,
                $request->gross_salary,
                $request->get('additional_income', []),
                $request->get('additional_deductions', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Payroll calculated successfully',
                'data' => $payrollData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-calculations/income-tax",
     *     summary="Calculate income tax for a specific amount",
     *     description="Calculate progressive income tax for a given taxable income",
     *     tags={"Tax Calculations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"taxable_income"},
     *             @OA\Property(property="taxable_income", type="number", example=600000),
     *             @OA\Property(property="tax_year", type="integer", example=2025)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Income tax calculated successfully",
     *         @OA\JsonContent(
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
                'tax_year' => 'nullable|integer|min:2000|max:2100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
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
                    'tax_year' => $taxYear
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate income tax',
                'error' => $e->getMessage()
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "monthly_payrolls"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="monthly_payrolls",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="month", type="integer", example=1),
     *                     @OA\Property(property="total_income", type="number", example=55000),
     *                     @OA\Property(property="total_deductions", type="number", example=15000),
     *                     @OA\Property(property="income_tax", type="number", example=1250)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Annual tax summary calculated successfully",
     *         @OA\JsonContent(
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
                'monthly_payrolls.*.income_tax' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
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
                'data' => $annualSummary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate annual tax summary',
                'error' => $e->getMessage()
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="additional_income", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="additional_deductions", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inputs validated successfully",
     *         @OA\JsonContent(
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
            $taxService = new TaxCalculationService();
            $errors = $taxService->validateCalculationInputs($request->all());

            $isValid = empty($errors);

            return response()->json([
                'success' => $isValid,
                'message' => $isValid ? 'Inputs are valid' : 'Validation errors found',
                'errors' => $errors
            ], $isValid ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate inputs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}