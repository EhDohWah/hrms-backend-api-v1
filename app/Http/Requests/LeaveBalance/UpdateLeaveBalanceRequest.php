<?php

namespace App\Http\Requests\LeaveBalance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'total_days' => 'sometimes|numeric|min:0',
            'used_days' => 'sometimes|numeric|min:0',
        ];
    }
}
