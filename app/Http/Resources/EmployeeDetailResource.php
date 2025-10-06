<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Eager‑loaded relations are assumed:
        // 'employment.department', 'employment.position', 'employment.workLocation',
        // 'employeeGrantAllocations.grantItemAllocation.grant',
        // 'employeeBeneficiaries', 'employeeIdentification'

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'staff_id' => $this->staff_id,
            'subsidiary' => $this->subsidiary,
            'initial_en' => $this->initial_en,
            'initial_th' => $this->initial_th,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'first_name_th' => $this->first_name_th,
            'last_name_th' => $this->last_name_th,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'status' => $this->status,
            'nationality' => $this->nationality,
            'religion' => $this->religion,
            'social_security_number' => $this->social_security_number,
            'tax_number' => $this->tax_number,
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'mobile_phone' => $this->mobile_phone,
            'permanent_address' => $this->permanent_address,
            'current_address' => $this->current_address,
            'military_status' => $this->military_status,
            'marital_status' => $this->marital_status,
            'spouse_name' => $this->spouse_name,
            'spouse_phone_number' => $this->spouse_phone_number,
            'emergency_contact_person_name' => $this->emergency_contact_person_name,
            'emergency_contact_person_relationship' => $this->emergency_contact_person_relationship,
            'emergency_contact_person_phone' => $this->emergency_contact_person_phone,
            'father_name' => $this->father_name,
            'father_occupation' => $this->father_occupation,
            'father_phone_number' => $this->father_phone_number,
            'mother_name' => $this->mother_name,
            'mother_occupation' => $this->mother_occupation,
            'mother_phone_number' => $this->mother_phone_number,
            'driver_license_number' => $this->driver_license_number,
            'remark' => $this->remark,

            // — Employment & Position —
            'employment' => $this->whenLoaded('employment', function () {
                return [
                    'employment_type' => $this->employment->employment_type,
                    'start_date' => $this->employment->start_date,
                    'probation_end_date' => $this->employment->probation_end_date,
                    'end_date' => $this->employment->end_date,
                    'position_salary' => $this->employment->position_salary,
                    'probation_salary' => $this->employment->probation_salary,
                    'active' => $this->employment->active,
                    'health_welfare' => $this->employment->health_welfare,
                    'pvd' => $this->employment->pvd,
                    'saving_fund' => $this->employment->saving_fund,
                    'department' => $this->whenLoaded('employment.department', function () {
                        return [
                            'id' => $this->employment->department->id,
                            'name' => $this->employment->department->name,
                            'description' => $this->employment->department->description,
                        ];
                    }),
                    'position' => $this->whenLoaded('employment.position', function () {
                        return [
                            'id' => $this->employment->position->id,
                            'title' => $this->employment->position->title,
                            'level' => $this->employment->position->level,
                            'is_manager' => $this->employment->position->is_manager,
                        ];
                    }),
                    'work_location' => $this->whenLoaded('employment.workLocation', function () {
                        return [
                            'id' => $this->employment->workLocation->id,
                            'name' => $this->employment->workLocation->name,
                            'type' => $this->employment->workLocation->type,
                        ];
                    }),
                ];
            }),

            // — Grant Allocations —
            'grant_allocations' => $this->whenLoaded('employeeGrantAllocations', function () {
                return $this->employeeGrantAllocations->map(function ($allocation) {
                    return [
                        'id' => $allocation->id,
                        'fte' => $allocation->fte,
                        'start_date' => $allocation->start_date,
                        'end_date' => $allocation->end_date,
                        'active' => $allocation->active,
                        'grant_item' => $this->whenLoaded('employeeGrantAllocations.grantItemAllocation', function () use ($allocation) {
                            return [
                                'id' => $allocation->grantItemAllocation->id,
                                'grant' => $this->whenLoaded('employeeGrantAllocations.grantItemAllocation.grant', function () use ($allocation) {
                                    return [
                                        'id' => $allocation->grantItemAllocation->grant->id,
                                        'name' => $allocation->grantItemAllocation->grant->name,
                                    ];
                                }),
                            ];
                        }),
                    ];
                });
            }),

            // — Beneficiaries —
            'beneficiaries' => $this->whenLoaded('employeeBeneficiaries', function () {
                return $this->employeeBeneficiaries->map(function ($beneficiary) {
                    return [
                        'id' => $beneficiary->id,
                        'beneficiary_name' => $beneficiary->beneficiary_name,
                        'beneficiary_relationship' => $beneficiary->beneficiary_relationship,
                        'phone_number' => $beneficiary->phone_number,
                    ];
                });
            }),

            // — Identifications —
            'identifications' => $this->whenLoaded('employeeIdentification', function () {
                return $this->employeeIdentification->map(function ($identification) {
                    return [
                        'id' => $identification->id,
                        'id_type' => $identification->id_type,
                        'document_number' => $identification->document_number,
                        'issue_date' => $identification->issue_date,
                        'expiry_date' => $identification->expiry_date,
                    ];
                });
            }),

            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
