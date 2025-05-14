<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Payroll",
 *     type="object",
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="employee_id", type="integer", format="int64"),
 *     @OA\Property(property="pay_period_date", type="string", format="date"),
 *     @OA\Property(property="basic_salary", type="number", format="float"),
 *     @OA\Property(property="salary_by_FTE", type="number", format="float"),
 *     @OA\Property(property="compensation_refund", type="number", format="float"),
 *     @OA\Property(property="thirteen_month_salary", type="number", format="float"),
 *     @OA\Property(property="pvd", type="number", format="float"),
 *     @OA\Property(property="saving_fund", type="number", format="float"),
 *     @OA\Property(property="employer_social_security", type="number", format="float"),
 *     @OA\Property(property="employee_social_security", type="number", format="float"),
 *     @OA\Property(property="employer_health_welfare", type="number", format="float"),
 *     @OA\Property(property="employee_health_welfare", type="number", format="float"),
 *     @OA\Property(property="tax", type="number", format="float"),
 *     @OA\Property(property="grand_total_income", type="number", format="float"),
 *     @OA\Property(property="grand_total_deduction", type="number", format="float"),
 *     @OA\Property(property="net_paid", type="number", format="float"),
 *     @OA\Property(property="employer_contribution_total", type="number", format="float"),
 *     @OA\Property(property="two_sides", type="number", format="float"),
 *     @OA\Property(property="payslip_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="payslip_number", type="string", nullable=true),
 *     @OA\Property(property="staff_signature", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class Payroll extends Model
{
    protected $table = 'payrolls';

    protected $fillable = [
        'employee_id',
        'pay_period_date',
        'basic_salary',
        'salary_by_FTE',
        'compensation_refund',
        'thirteen_month_salary',
        'pvd',
        'saving_fund',
        'employer_social_security',
        'employee_social_security',
        'employer_health_welfare',
        'employee_health_welfare',
        'tax',
        'grand_total_income',
        'grand_total_deduction',
        'net_paid',
        'employer_contribution_total',
        'two_sides',
        'payslip_date',
        'payslip_number',
        'staff_signature',
        'created_by',
        'updated_by'
    ];

    // If you want Laravel to manage created_at and updated_at automatically,
    // remove the next line. Otherwise, keep it if you want to handle them manually.
    public $timestamps = true;

    // Define the relationship to the Employee model.
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function grantAllocations()
    {
        return $this->hasMany(PayrollGrantAllocation::class, 'payroll_id');
    }
}
