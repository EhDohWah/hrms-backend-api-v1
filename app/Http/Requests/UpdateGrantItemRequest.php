<?php

namespace App\Http\Requests;

use App\Models\GrantItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrantItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $grantItemId = $this->route('grantItem')?->id ?? $this->route('grantItem');

        return [
            'grant_id' => ['sometimes', 'required', 'integer', 'exists:grants,id'],
            'grant_position' => ['nullable', 'string', 'max:255'],
            'grant_salary' => ['nullable', 'numeric', 'min:0'],
            'grant_benefit' => ['nullable', 'numeric', 'min:0'],
            'grant_level_of_effort' => ['nullable', 'numeric', 'between:0,1'],
            'grant_position_number' => ['nullable', 'integer', 'min:1'],
            'budgetline_code' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($grantItemId) {
                    if ($value && $this->grant_position && $this->grant_id) {
                        $exists = GrantItem::where('grant_id', $this->grant_id)
                            ->where('grant_position', $this->grant_position)
                            ->where('budgetline_code', $value)
                            ->where('id', '!=', $grantItemId)
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

    public function messages(): array
    {
        return [
            'grant_id.required' => 'Grant is required.',
            'grant_id.exists' => 'The selected grant does not exist.',
            'grant_salary.min' => 'Grant salary must be at least 0.',
            'grant_benefit.min' => 'Grant benefit must be at least 0.',
            'grant_level_of_effort.between' => 'Grant level of effort must be between 0 and 1.',
            'grant_position_number.min' => 'Grant position number must be at least 1.',
        ];
    }
}
