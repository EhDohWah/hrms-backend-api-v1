<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class AttachGrantItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'grant_item_id' => ['required', 'exists:grant_items,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric'],
            'currency' => ['required', 'string'],
            'payment_method' => ['required', 'string'],
            'payment_account' => ['required', 'string'],
            'payment_account_name' => ['required', 'string'],
        ];
    }
}
