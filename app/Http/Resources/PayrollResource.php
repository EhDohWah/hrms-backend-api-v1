<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization' => $this->organization,
            'employment_id' => $this->employment_id,
            'employee_funding_allocation_id' => $this->employee_funding_allocation_id,

            // Snapshot fields (immutable point-in-time data)
            'snapshot_staff_id' => $this->snapshot_staff_id,
            'snapshot_employee_name' => $this->snapshot_employee_name,
            'snapshot_department' => $this->snapshot_department,
            'snapshot_position' => $this->snapshot_position,
            'snapshot_site' => $this->snapshot_site,
            'snapshot_grant_code' => $this->snapshot_grant_code,
            'snapshot_grant_name' => $this->snapshot_grant_name,
            'snapshot_budget_line_code' => $this->snapshot_budget_line_code,
            'snapshot_fte' => $this->snapshot_fte,
            'gross_salary' => $this->gross_salary,
            'gross_salary_by_FTE' => $this->gross_salary_by_FTE,
            'retroactive_salary' => $this->retroactive_salary,
            'thirteen_month_salary' => $this->thirteen_month_salary,
            // 'thirteen_month_salary_accured' => $this->thirteen_month_salary_accured, // Disabled — accrual projection not needed for now
            'pvd' => $this->pvd,
            'saving_fund' => $this->saving_fund,
            'study_loan' => $this->study_loan,
            'employer_social_security' => $this->employer_social_security,
            'employee_social_security' => $this->employee_social_security,
            'employer_health_welfare' => $this->employer_health_welfare,
            'employee_health_welfare' => $this->employee_health_welfare,
            'tax' => $this->tax,
            'net_salary' => $this->net_salary,
            'total_salary' => $this->total_salary,
            'total_pvd' => $this->total_pvd,
            'total_saving_fund' => $this->total_saving_fund,
            'salary_increase' => $this->salary_increase,
            'total_income' => $this->total_income,
            'employer_contribution' => $this->employer_contribution,
            'total_deduction' => $this->total_deduction,
            'notes' => $this->notes,
            'pay_period_date' => $this->pay_period_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (loaded conditionally)
            'employment' => $this->whenLoaded('employment', fn () => [
                'id' => $this->employment->id,
                'employee_id' => $this->employment->employee_id,
                'department_id' => $this->employment->department_id,
                'position_id' => $this->employment->position_id,
                'pay_method' => $this->employment->pay_method,
                'pvd' => $this->employment->pvd,
                'saving_fund' => $this->employment->saving_fund,
                'start_date' => $this->employment->start_date,
                'end_probation_date' => $this->employment->end_probation_date,
                'employee' => $this->when($this->employment->relationLoaded('employee'), fn () => [
                    'id' => $this->employment->employee->id,
                    'staff_id' => $this->employment->employee->staff_id,
                    'initial_en' => $this->employment->employee->initial_en,
                    'first_name_en' => $this->employment->employee->first_name_en,
                    'last_name_en' => $this->employment->employee->last_name_en,
                    'organization' => $this->employment->organization,
                    'status' => $this->employment->employee->status,
                ]),
                'department' => $this->when($this->employment->relationLoaded('department'), fn () => [
                    'id' => $this->employment->department?->id,
                    'name' => $this->employment->department?->name,
                ]),
                'position' => $this->when($this->employment->relationLoaded('position'), fn () => [
                    'id' => $this->employment->position?->id,
                    'title' => $this->employment->position?->title,
                    'department_id' => $this->employment->position?->department_id,
                ]),
            ]),
            'employee_funding_allocation' => $this->whenLoaded('employeeFundingAllocation', fn () => [
                'id' => $this->employeeFundingAllocation->id,
                'employee_id' => $this->employeeFundingAllocation->employee_id,
                'employment_id' => $this->employeeFundingAllocation->employment_id,
                'grant_item_id' => $this->employeeFundingAllocation->grant_item_id,
                'fte' => $this->employeeFundingAllocation->fte,
                'allocated_amount' => $this->employeeFundingAllocation->allocated_amount,
                'status' => $this->employeeFundingAllocation->status,
                'grant_item' => $this->when(
                    $this->employeeFundingAllocation->relationLoaded('grantItem'),
                    fn () => [
                        'id' => $this->employeeFundingAllocation->grantItem?->id,
                        'grant_id' => $this->employeeFundingAllocation->grantItem?->grant_id,
                        'grant_position' => $this->employeeFundingAllocation->grantItem?->grant_position,
                        'budgetline_code' => $this->employeeFundingAllocation->grantItem?->budgetline_code,
                        'grant' => $this->when(
                            $this->employeeFundingAllocation->grantItem?->relationLoaded('grant'),
                            fn () => [
                                'id' => $this->employeeFundingAllocation->grantItem->grant?->id,
                                'name' => $this->employeeFundingAllocation->grantItem->grant?->name,
                                'code' => $this->employeeFundingAllocation->grantItem->grant?->code,
                            ]
                        ),
                    ]
                ),
            ]),
        ];
    }
}
