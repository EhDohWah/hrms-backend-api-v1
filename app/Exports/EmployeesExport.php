<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;

class EmployeesExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Eager load related models for efficiency
        $employees = Employee::with([
            'employeeIdentification', // assuming relation name
            'employeeBeneficiaries',   // assuming relation name
        ])->get();

        // Map each employee to an exportable array
        return $employees->map(function ($employee) {
            // Get first and second beneficiary if exist
            $kin1 = $employee->employeeBeneficiaries[0] ?? null;
            $kin2 = $employee->employeeBeneficiaries[1] ?? null;
            // Get first identification if exist
            $identification = $employee->employeeIdentification[0] ?? null;

            return [
                'org' => $employee->subsidiary,
                'staff_id' => $employee->staff_id,
                'initial' => $employee->initial_en,
                'first_name' => $employee->first_name_en,
                'last_name' => $employee->last_name_en,
                'initial_th' => $employee->initial_th,
                'first_name_th' => $employee->first_name_th,
                'last_name_th' => $employee->last_name_th,
                'gender' => $employee->gender,
                'date_of_birth' => $employee->date_of_birth,
                'status' => $employee->status,
                'nationality' => $employee->nationality,
                'religion' => $employee->religion,
                'social_security_no' => $employee->social_security_number,
                'tax_no' => $employee->tax_number,
                'driver_license' => $employee->driver_license_number,
                'bank_name' => $employee->bank_name,
                'bank_branch' => $employee->bank_branch,
                'bank_acc_name' => $employee->bank_account_name,
                'bank_acc_no' => $employee->bank_account_number,
                'mobile_no' => $employee->mobile_phone,
                'current_address' => $employee->current_address,
                'permanent_address' => $employee->permanent_address,
                'marital_status' => $employee->marital_status,
                'spouse_name' => $employee->spouse_name,
                'spouse_mobile_no' => $employee->spouse_phone_number,
                'emergency_name' => $employee->emergency_contact_person_name,
                'relationship' => $employee->emergency_contact_person_relationship,
                'emergency_mobile_no' => $employee->emergency_contact_person_phone,
                'father_name' => $employee->father_name,
                'father_occupation' => $employee->father_occupation,
                'father_mobile_no' => $employee->father_phone_number,
                'mother_name' => $employee->mother_name,
                'mother_occupation' => $employee->mother_occupation,
                'mother_mobile_no' => $employee->mother_phone_number,
                'military_status' => $employee->military_status,
                'remark' => $employee->remark,
                // Identification
                'id_type' => $identification->id_type ?? null,
                'id_no' => $identification->document_number ?? null,
                // Beneficiary 1
                'kin1_name' => $kin1->beneficiary_name ?? null,
                'kin1_relationship' => $kin1->beneficiary_relationship ?? null,
                'kin1_mobile' => $kin1->phone_number ?? null,
                // Beneficiary 2
                'kin2_name' => $kin2->beneficiary_name ?? null,
                'kin2_relationship' => $kin2->beneficiary_relationship ?? null,
                'kin2_mobile' => $kin2->phone_number ?? null,
            ];
        });
    }
}
