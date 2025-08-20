<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\TaxBracket;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $testYear = 2025;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->createTestTaxData();
    }

    /** @test */
    public function it_can_retrieve_tax_brackets()
    {
        $response = $this->getJson('/api/tax-brackets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'min_income',
                        'max_income',
                        'tax_rate',
                        'bracket_order',
                        'effective_year',
                        'is_active',
                        'description',
                        'income_range',
                        'formatted_rate',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_create_tax_bracket()
    {
        $bracketData = [
            'min_income' => 2000001,
            'max_income' => 3000000,
            'tax_rate' => 30,
            'bracket_order' => 7,
            'effective_year' => $this->testYear,
            'description' => 'Test new bracket',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/tax-brackets', $bracketData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tax bracket created successfully',
            ]);

        $this->assertDatabaseHas('tax_brackets', [
            'min_income' => 2000001,
            'tax_rate' => 30,
            'bracket_order' => 7,
        ]);
    }

    /** @test */
    public function it_validates_tax_bracket_creation()
    {
        $invalidData = [
            'min_income' => -1000, // Invalid: negative
            'tax_rate' => 150,     // Invalid: over 100%
            'bracket_order' => 1,  // Invalid: duplicate order
            'effective_year' => $this->testYear,
        ];

        $response = $this->postJson('/api/tax-brackets', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_income', 'tax_rate', 'bracket_order']);
    }

    /** @test */
    public function it_can_calculate_tax_for_income()
    {
        $income = 600000;

        $response = $this->getJson("/api/tax-brackets/calculate/{$income}?year={$this->testYear}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'income',
                    'total_tax',
                    'net_income',
                    'effective_rate',
                    'breakdown',
                    'tax_year',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals($income, $data['income']);
        $this->assertGreaterThan(0, $data['total_tax']);
        $this->assertEquals($income - $data['total_tax'], $data['net_income']);
    }

    /** @test */
    public function it_can_retrieve_tax_settings()
    {
        $response = $this->getJson('/api/tax-settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'setting_key',
                        'setting_value',
                        'setting_type',
                        'description',
                        'effective_year',
                        'is_active',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_get_tax_settings_by_year()
    {
        $response = $this->getJson("/api/tax-settings/by-year/{$this->testYear}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'DEDUCTION',
                    'RATE',
                    'LIMIT',
                ],
                'year',
            ]);
    }

    /** @test */
    public function it_can_get_specific_tax_setting_value()
    {
        $response = $this->getJson("/api/tax-settings/value/PERSONAL_ALLOWANCE?year={$this->testYear}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'PERSONAL_ALLOWANCE',
                    'value' => 60000,
                    'year' => $this->testYear,
                ],
            ]);
    }

    /** @test */
    public function it_can_calculate_payroll_with_tax()
    {
        $employee = Employee::factory()->create();
        Employment::factory()->create(['employee_id' => $employee->id]);

        $payrollData = [
            'employee_id' => $employee->id,
            'gross_salary' => 50000,
            'pay_period_date' => '2025-01-31',
            'tax_year' => $this->testYear,
            'save_payroll' => false,
        ];

        $response = $this->postJson('/api/tax-calculations/payroll', $payrollData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'gross_salary',
                    'total_income',
                    'net_salary',
                    'taxable_income',
                    'income_tax',
                    'deductions',
                    'social_security',
                    'tax_breakdown',
                    'formatted',
                    'ratios',
                ],
            ]);
    }

    /** @test */
    public function it_can_calculate_income_tax_only()
    {
        $taxData = [
            'taxable_income' => 600000,
            'tax_year' => $this->testYear,
        ];

        $response = $this->postJson('/api/tax-calculations/income-tax', $taxData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'taxable_income',
                    'annual_tax',
                    'monthly_tax',
                    'effective_rate',
                    'tax_breakdown',
                ],
            ]);
    }

    /** @test */
    public function it_validates_payroll_calculation_request()
    {
        $invalidData = [
            'employee_id' => 999999, // Non-existent employee
            'gross_salary' => -5000,  // Negative salary
            'pay_period_date' => '2030-01-01', // Future date
        ];

        $response = $this->postJson('/api/tax-calculations/payroll', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'gross_salary', 'pay_period_date']);
    }

    /** @test */
    public function it_can_bulk_update_tax_settings()
    {
        $bulkData = [
            'effective_year' => $this->testYear,
            'settings' => [
                [
                    'setting_key' => 'PERSONAL_ALLOWANCE',
                    'setting_value' => 65000,
                    'setting_type' => 'DEDUCTION',
                    'description' => 'Updated personal allowance',
                ],
                [
                    'setting_key' => 'SPOUSE_ALLOWANCE',
                    'setting_value' => 65000,
                    'setting_type' => 'DEDUCTION',
                    'description' => 'Updated spouse allowance',
                ],
            ],
        ];

        $response = $this->postJson('/api/tax-settings/bulk-update', $bulkData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'updated_count' => 2,
            ]);

        $this->assertDatabaseHas('tax_settings', [
            'setting_key' => 'PERSONAL_ALLOWANCE',
            'setting_value' => 65000,
            'effective_year' => $this->testYear,
        ]);
    }

    /** @test */
    public function it_handles_unauthorized_access()
    {
        // Logout the user
        auth()->logout();

        $response = $this->getJson('/api/tax-brackets');

        $response->assertStatus(401);
    }

    /**
     * Create test tax data
     */
    private function createTestTaxData()
    {
        // Create tax brackets
        $brackets = [
            ['min' => 0, 'max' => 150000, 'rate' => 0, 'order' => 1],
            ['min' => 150001, 'max' => 300000, 'rate' => 5, 'order' => 2],
            ['min' => 300001, 'max' => 500000, 'rate' => 10, 'order' => 3],
            ['min' => 500001, 'max' => 750000, 'rate' => 15, 'order' => 4],
            ['min' => 750001, 'max' => null, 'rate' => 20, 'order' => 5],
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
                'created_by' => 'test',
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
                'created_by' => 'test',
            ]);
        }
    }
}
