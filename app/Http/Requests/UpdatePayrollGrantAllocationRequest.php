<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdatePayrollGrantAllocationRequest",
 *     title="Update Payroll Grant Allocation Request",
 *     description="Request for updating a payroll grant allocation",
 *     required={"payroll_id", "employee_grant_allocation_id", "loe_snapshot", "amount", "is_advance"},
 *
 *     @OA\Property(property="payroll_id", type="integer", example=1),
 *     @OA\Property(property="employee_grant_allocation_id", type="integer", example=1),
 *     @OA\Property(property="loe_snapshot", type="number", format="float", example=50.00),
 *     @OA\Property(property="amount", type="number", format="float", example=5000.00),
 *     @OA\Property(property="is_advance", type="boolean", example=false),
 *     @OA\Property(property="description", type="string", nullable=true, maxLength=255, example="Salary allocation for research project")
 * )
 */
class UpdatePayrollGrantAllocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payroll_id' => 'required|exists:payrolls,id',
            'employee_grant_allocation_id' => 'required|exists:employee_grant_allocations,id',
            'loe_snapshot' => 'required|numeric|min:0|max:100',
            'amount' => 'required|numeric|min:0',
            'is_advance' => 'required|boolean',
            'description' => 'nullable|string|max:255',
        ];
    }
}
