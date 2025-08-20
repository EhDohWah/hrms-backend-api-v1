<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Payroll",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="employment_id", type="integer", format="int64"),
 *     @OA\Property(property="employee_funding_allocation_id", type="integer", format="int64"),
 *     @OA\Property(property="pay_period_date", type="string", format="date"),
 *     @OA\Property(property="gross_salary", type="number", format="float"),
 *     @OA\Property(property="gross_salary_by_FTE", type="number", format="float"),
 *     @OA\Property(property="compensation_refund", type="number", format="float"),
 *     @OA\Property(property="thirteen_month_salary", type="number", format="float"),
 *     @OA\Property(property="thirteen_month_salary_accured", type="number", format="float"),
 *     @OA\Property(property="pvd", type="number", format="float"),
 *     @OA\Property(property="saving_fund", type="number", format="float"),
 *     @OA\Property(property="employer_social_security", type="number", format="float"),
 *     @OA\Property(property="employee_social_security", type="number", format="float"),
 *     @OA\Property(property="employer_health_welfare", type="number", format="float"),
 *     @OA\Property(property="employee_health_welfare", type="number", format="float"),
 *     @OA\Property(property="tax", type="number", format="float"),
 *     @OA\Property(property="net_salary", type="number", format="float"),
 *     @OA\Property(property="total_salary", type="number", format="float"),
 *     @OA\Property(property="total_pvd", type="number", format="float"),
 *     @OA\Property(property="total_saving_fund", type="number", format="float"),
 *     @OA\Property(property="salary_bonus", type="number", format="float"),
 *     @OA\Property(property="total_income", type="number", format="float"),
 *     @OA\Property(property="employer_contribution", type="number", format="float"),
 *     @OA\Property(property="total_deduction", type="number", format="float"),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class Payroll extends Model
{
    protected $table = 'payrolls';

    protected $fillable = [
        'employment_id',
        'employee_funding_allocation_id',
        'gross_salary',
        'gross_salary_by_FTE',
        'compensation_refund',
        'thirteen_month_salary',
        'thirteen_month_salary_accured',
        'pvd',
        'saving_fund',
        'employer_social_security',
        'employee_social_security',
        'employer_health_welfare',
        'employee_health_welfare',
        'tax',
        'net_salary',
        'total_salary',
        'total_pvd',
        'total_saving_fund',
        'salary_bonus',
        'total_income',
        'employer_contribution',
        'total_deduction',
        'notes',
        'pay_period_date',
    ];

    public $timestamps = true;

    // Encrypted attributes - Laravel will automatically encrypt/decrypt these
    protected $casts = [
        'gross_salary' => 'encrypted',
        'gross_salary_by_FTE' => 'encrypted',
        'compensation_refund' => 'encrypted',
        'thirteen_month_salary' => 'encrypted',
        'thirteen_month_salary_accured' => 'encrypted',
        'pvd' => 'encrypted',
        'saving_fund' => 'encrypted',
        'employer_social_security' => 'encrypted',
        'employee_social_security' => 'encrypted',
        'employer_health_welfare' => 'encrypted',
        'employee_health_welfare' => 'encrypted',
        'tax' => 'encrypted',
        'net_salary' => 'encrypted',
        'total_salary' => 'encrypted',
        'total_pvd' => 'encrypted',
        'total_saving_fund' => 'encrypted',
        'salary_bonus' => 'encrypted',
        'total_income' => 'encrypted',
        'employer_contribution' => 'encrypted',
        'total_deduction' => 'encrypted',
        'pay_period_date' => 'date',
    ];

    // Define relationships
    public function employment()
    {
        return $this->belongsTo(Employment::class, 'employment_id');
    }

    public function employeeFundingAllocation()
    {
        return $this->belongsTo(EmployeeFundingAllocation::class, 'employee_funding_allocation_id');
    }

    // Access employee through employment relationship
    public function employee()
    {
        return $this->hasOneThrough(Employee::class, Employment::class, 'id', 'id', 'employment_id', 'employee_id');
    }

    public function grantAllocations()
    {
        return $this->hasMany(PayrollGrantAllocation::class, 'payroll_id');
    }
}
