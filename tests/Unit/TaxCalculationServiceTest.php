<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TaxCalculationService;
use App\Models\TaxBracket;
use App\Models\TaxSetting;
use App\Models\Employee;
use App\Models\Employment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $taxService;
    protected $testYear = 2025;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taxService = new TaxCalculationService($this->testYear);
        $this->createTestTaxData();
    }

    /** @test */
    public function it_calculates_progressive_income_tax_correctly()
    {
        // Test income of 600,000 (should be in multiple brackets)
        $taxableIncome = 600000;
        $monthlyTax = $this->taxService->calculateProgressiveIncomeTax($taxableIncome);
        
        // Expected calculation:
        // 0-150,000: 0% = 0
        // 150,001-300,000: 5% = 7,500
        // 300,001-500,000: 10% = 20,000
        // 500,001-600,000: 15% = 15,000
        // Total annual tax: 42,500
        // Monthly tax: 42,500 / 12 = 3,541.67
        
        $this->assertEqualsWithDelta(3541.67, $monthlyTax, 0.01);
    }

    /** @test */
    public function it_calculates_zero_tax_for_income_below_threshold()
    {
        $taxableIncome = 100000; // Below 150,000 threshold
        $monthlyTax = $this->taxService->calculateProgressiveIncomeTax($taxableIncome);
        
        $this->assertEquals(0, $monthlyTax);
    }

    /** @test */
    public function it_calculates_social_security_contributions_correctly()
    {
        $grossSalary = 20000; // Monthly salary
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->taxService);
        $method = $reflection->getMethod('calculateSocialSecurity');
        $method->setAccessible(true);
        
        $socialSecurity = $method->invoke($this->taxService, $grossSalary);
        
        // Expected: 5% of 20,000 = 1,000 (but capped at 750)
        $this->assertEquals(750, $socialSecurity['employee_contribution']);
        $this->assertEquals(750, $socialSecurity['employer_contribution']);
        $this->assertEquals(1500, $socialSecurity['total_contribution']);
    }

    /** @test */
    public function it_applies_personal_allowances_correctly()
    {
        $employee = Employee::factory()->create([
            'marital_status' => 'married'
        ]);
        
        Employment::factory()->create([
            'employee_id' => $employee->id
        ]);

        // Create 2 children for the employee
        $employee->employeeChildren()->create(['name' => 'Child 1', 'date_of_birth' => '2015-01-01']);
        $employee->employeeChildren()->create(['name' => 'Child 2', 'date_of_birth' => '2017-01-01']);

        $grossSalary = 50000;
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->taxService);
        $method = $reflection->getMethod('calculateDeductions');
        $method->setAccessible(true);
        
        $deductions = $method->invoke($this->taxService, $employee, $grossSalary, []);
        
        // Expected deductions:
        // Personal allowance: 60,000
        // Spouse allowance: 60,000 (married)
        // Child allowance: 60,000 (2 children × 30,000)
        // Personal expenses: 240,000 (40% of 600,000 annual salary)
        // Provident fund: 18,000 (3% of 600,000 annual salary)
        
        $this->assertEquals(60000, $deductions['personal_allowance']);
        $this->assertEquals(60000, $deductions['spouse_allowance']);
        $this->assertEquals(60000, $deductions['child_allowance']);
        $this->assertGreaterThan(0, $deductions['personal_expenses']);
        $this->assertGreaterThan(0, $deductions['provident_fund']);
    }

    /** @test */
    public function it_calculates_complete_payroll_correctly()
    {
        $employee = Employee::factory()->create();
        Employment::factory()->create(['employee_id' => $employee->id]);
        
        $grossSalary = 50000;
        $payrollData = $this->taxService->calculatePayroll($employee->id, $grossSalary);
        
        $this->assertArrayHasKey('gross_salary', $payrollData);
        $this->assertArrayHasKey('total_income', $payrollData);
        $this->assertArrayHasKey('taxable_income', $payrollData);
        $this->assertArrayHasKey('income_tax', $payrollData);
        $this->assertArrayHasKey('net_salary', $payrollData);
        $this->assertArrayHasKey('deductions', $payrollData);
        $this->assertArrayHasKey('social_security', $payrollData);
        $this->assertArrayHasKey('tax_breakdown', $payrollData);
        
        $this->assertEquals($grossSalary, $payrollData['gross_salary']);
        $this->assertLessThan($grossSalary, $payrollData['net_salary']);
        $this->assertGreaterThanOrEqual(0, $payrollData['income_tax']);
    }

    /** @test */
    public function it_validates_calculation_inputs()
    {
        $validInputs = [
            'employee_id' => 1,
            'gross_salary' => 50000,
            'additional_income' => [],
            'additional_deductions' => []
        ];
        
        $errors = $this->taxService->validateCalculationInputs($validInputs);
        $this->assertEmpty($errors);
        
        $invalidInputs = [
            'employee_id' => 'invalid',
            'gross_salary' => -1000,
        ];
        
        $errors = $this->taxService->validateCalculationInputs($invalidInputs);
        $this->assertNotEmpty($errors);
    }

    /** @test */
    public function it_handles_additional_income_and_deductions()
    {
        $employee = Employee::factory()->create();
        Employment::factory()->create(['employee_id' => $employee->id]);
        
        $grossSalary = 40000;
        $additionalIncome = [
            ['type' => 'bonus', 'amount' => 10000, 'description' => 'Performance bonus']
        ];
        $additionalDeductions = [
            ['type' => 'loan', 'amount' => 5000, 'description' => 'Company loan']
        ];
        
        $payrollData = $this->taxService->calculatePayroll(
            $employee->id,
            $grossSalary,
            $additionalIncome,
            $additionalDeductions
        );
        
        $this->assertEquals(50000, $payrollData['total_income']); // 40000 + 10000
        $this->assertEquals(5000, $payrollData['deductions']['additional_deductions']);
    }

    /**
     * Create test tax data for the test year
     */
    private function createTestTaxData()
    {
        // Create tax brackets
        $brackets = [
            ['min' => 0, 'max' => 150000, 'rate' => 0, 'order' => 1],
            ['min' => 150001, 'max' => 300000, 'rate' => 5, 'order' => 2],
            ['min' => 300001, 'max' => 500000, 'rate' => 10, 'order' => 3],
            ['min' => 500001, 'max' => 750000, 'rate' => 15, 'order' => 4],
            ['min' => 750001, 'max' => 1000000, 'rate' => 20, 'order' => 5],
            ['min' => 1000001, 'max' => null, 'rate' => 25, 'order' => 6],
        ];

        foreach ($brackets as $bracket) {
            TaxBracket::create([
                'min_income' => $bracket['min'],
                'max_income' => $bracket['max'],
                'tax_rate' => $bracket['rate'],
                'bracket_order' => $bracket['order'],
                'effective_year' => $this->testYear,
                'is_active' => true,
                'description' => "Test bracket {$bracket['order']}",
                'created_by' => 'test'
            ]);
        }

        // Create tax settings
        $settings = [
            [TaxSetting::KEY_PERSONAL_ALLOWANCE, 60000, TaxSetting::TYPE_DEDUCTION],
            [TaxSetting::KEY_SPOUSE_ALLOWANCE, 60000, TaxSetting::TYPE_DEDUCTION],
            [TaxSetting::KEY_CHILD_ALLOWANCE, 30000, TaxSetting::TYPE_DEDUCTION],
            [TaxSetting::KEY_PERSONAL_EXPENSE_RATE, 40, TaxSetting::TYPE_RATE],
            [TaxSetting::KEY_PERSONAL_EXPENSE_MAX, 60000, TaxSetting::TYPE_LIMIT],
            [TaxSetting::KEY_SSF_RATE, 5, TaxSetting::TYPE_RATE],
            [TaxSetting::KEY_SSF_MAX_MONTHLY, 750, TaxSetting::TYPE_LIMIT],
            [TaxSetting::KEY_PF_MIN_RATE, 3, TaxSetting::TYPE_RATE],
            [TaxSetting::KEY_PF_MAX_RATE, 15, TaxSetting::TYPE_RATE],
        ];

        foreach ($settings as $setting) {
            TaxSetting::create([
                'setting_key' => $setting[0],
                'setting_value' => $setting[1],
                'setting_type' => $setting[2],
                'effective_year' => $this->testYear,
                'is_active' => true,
                'description' => "Test setting {$setting[0]}",
                'created_by' => 'test'
            ]);
        }
    }
}