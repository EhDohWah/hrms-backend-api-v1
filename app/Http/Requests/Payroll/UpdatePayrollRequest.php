<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'sometimes|required|exists:employees,id',
            'pay_period_date' => 'sometimes|required|date',
            'basic_salary' => 'sometimes|required|numeric',
            'salary_by_FTE' => 'sometimes|required|numeric',
            'retroactive_salary' => 'sometimes|numeric',
            'thirteen_month_salary' => 'sometimes|required|numeric',
            'pvd' => 'sometimes|required|numeric',
            'saving_fund' => 'sometimes|required|numeric',
            'study_loan' => 'sometimes|numeric|min:0',
            'employer_social_security' => 'sometimes|required|numeric',
            'employee_social_security' => 'sometimes|required|numeric',
            'employer_health_welfare' => 'sometimes|required|numeric',
            'employee_health_welfare' => 'sometimes|required|numeric',
            'tax' => 'sometimes|required|numeric',
            'grand_total_income' => 'sometimes|required|numeric',
            'grand_total_deduction' => 'sometimes|required|numeric',
            'net_paid' => 'sometimes|required|numeric',
            'employer_contribution_total' => 'sometimes|required|numeric',
            'two_sides' => 'sometimes|required|numeric',
            'payslip_date' => 'nullable|date',
            'payslip_number' => 'nullable|string|max:50',
            'staff_signature' => 'nullable|string|max:200',
            'updated_by' => 'nullable|string|max:100',
        ];
    }
}
