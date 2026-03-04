<?php

namespace Database\Factories;

use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_id' => Employment::factory(),
            'employee_funding_allocation_id' => EmployeeFundingAllocation::factory(),
            'pay_period_date' => fake()->date(),
            'gross_salary' => fake()->randomFloat(2, 10000, 100000),
            'gross_salary_by_FTE' => fake()->randomFloat(2, 10000, 100000),
            'retroactive_adjustment' => '0.00',
            'thirteen_month_salary' => '0.00',
            'thirteen_month_salary_accured' => '0.00',
            'pvd' => '0.00',
            'saving_fund' => '0.00',
            'employer_social_security' => '0.00',
            'employee_social_security' => '0.00',
            'employer_health_welfare' => '0.00',
            'employee_health_welfare' => '0.00',
            'tax' => '0.00',
            'net_salary' => fake()->randomFloat(2, 8000, 90000),
            'total_salary' => '0.00',
            'total_pvd' => '0.00',
            'total_saving_fund' => '0.00',
            'salary_bonus' => '0.00',
            'total_income' => '0.00',
            'employer_contribution' => '0.00',
            'total_deduction' => '0.00',
            'notes' => null,
        ];
    }
}
