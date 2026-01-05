<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateInterOrganizationAdvanceRequest",
 *     title="Update Inter Organization Advance Request",
 *     description="Request for updating an inter-organization advance",
 *     required={"from_organization", "to_organization", "via_grant_id", "amount", "advance_date"},
 *
 *     @OA\Property(property="payroll_grant_allocation_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="from_organization", type="string", maxLength=5, example="ORG1"),
 *     @OA\Property(property="to_organization", type="string", maxLength=5, example="ORG2"),
 *     @OA\Property(property="via_grant_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="amount", type="number", format="float", example=10000.00),
 *     @OA\Property(property="advance_date", type="string", format="date", example="2023-01-15"),
 *     @OA\Property(property="notes", type="string", maxLength=255, nullable=true, example="Advance for project expenses"),
 *     @OA\Property(property="settlement_date", type="string", format="date", nullable=true, example="2023-06-15")
 * )
 */
class UpdateInterOrganizationAdvanceRequest extends FormRequest
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
            'payroll_grant_allocation_id' => 'required|exists:payroll_grant_allocations,id',
            'from_organization' => 'required|string|max:5',
            'to_organization' => 'required|string|max:5',
            'via_grant_id' => 'required|exists:grants,id',
            'amount' => 'required|numeric|min:0',
            'advance_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
            'settlement_date' => 'nullable|date',
        ];
    }
}
