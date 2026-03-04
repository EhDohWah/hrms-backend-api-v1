<?php

namespace App\Http\Requests\LeaveCalculation;

use Illuminate\Foundation\Http\FormRequest;

class YearStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['year' => $this->route('year')]);
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2000|max:2100',
        ];
    }
}
