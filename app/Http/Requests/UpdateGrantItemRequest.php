<?php

namespace App\Http\Requests;

use App\Models\GrantItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateGrantItemRequest",
 *     title="Update Grant Item Request",
 *     description="Request for updating an existing grant item with duplicate validation",
 *
 *     @OA\Property(property="grant_id", type="integer", example=1, nullable=true),
 *     @OA\Property(property="grant_position", type="string", example="Project Manager", nullable=true),
 *     @OA\Property(property="grant_salary", type="number", format="float", example=75000, nullable=true),
 *     @OA\Property(property="grant_benefit", type="number", format="float", example=15000, nullable=true),
 *     @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75, nullable=true),
 *     @OA\Property(property="grant_position_number", type="integer", example=2, nullable=true),
 *     @OA\Property(property="budgetline_code", type="string", example="BL001", nullable=true)
 * )
 */
class UpdateGrantItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $grantItemId = $this->route('id'); // Get the ID from the route parameter

        return [
            'grant_id' => 'sometimes|required|exists:grants,id',
            'grant_position' => 'nullable|string|max:255',
            'grant_salary' => 'nullable|numeric|min:0',
            'grant_benefit' => 'nullable|numeric|min:0',
            'grant_level_of_effort' => 'nullable|numeric|between:0,1',
            'grant_position_number' => 'nullable|integer|min:1',
            'budgetline_code' => [
                'nullable',
                'string',
                'max:255',
                // Custom validation rule for uniqueness during updates
                function ($attribute, $value, $fail) use ($grantItemId) {
                    if ($value && $this->grant_position && $this->grant_id) {
                        $exists = GrantItem::where('grant_id', $this->grant_id)
                            ->where('grant_position', $this->grant_position)
                            ->where('budgetline_code', $value)
                            ->where('id', '!=', $grantItemId) // Exclude current record
                            ->exists();

                        if ($exists) {
                            $fail('The combination of grant position "'.$this->grant_position.
                                  '" and budget line code "'.$value.
                                  '" already exists for this grant.');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'grant_id.required' => 'Grant is required.',
            'grant_id.exists' => 'The selected grant does not exist.',
            'grant_position.string' => 'Grant position must be a string.',
            'grant_position.max' => 'Grant position cannot exceed 255 characters.',
            'grant_salary.numeric' => 'Grant salary must be a number.',
            'grant_salary.min' => 'Grant salary must be at least 0.',
            'grant_benefit.numeric' => 'Grant benefit must be a number.',
            'grant_benefit.min' => 'Grant benefit must be at least 0.',
            'grant_level_of_effort.numeric' => 'Grant level of effort must be a number.',
            'grant_level_of_effort.between' => 'Grant level of effort must be between 0 and 1.',
            'grant_position_number.integer' => 'Grant position number must be an integer.',
            'grant_position_number.min' => 'Grant position number must be at least 1.',
            'budgetline_code.string' => 'Budget line code must be a string.',
            'budgetline_code.max' => 'Budget line code cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'grant_id' => 'grant',
            'grant_position' => 'grant position',
            'grant_salary' => 'grant salary',
            'grant_benefit' => 'grant benefit',
            'grant_level_of_effort' => 'grant level of effort',
            'grant_position_number' => 'grant position number',
            'budgetline_code' => 'budget line code',
        ];
    }
}
