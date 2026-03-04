<?php

namespace App\Http\Requests\FundingAllocation;

use Illuminate\Foundation\Http\FormRequest;

class UploadFundingAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }
}
