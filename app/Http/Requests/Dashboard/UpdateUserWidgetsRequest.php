<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserWidgetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'widget_ids' => 'required|array',
            'widget_ids.*' => 'integer|exists:dashboard_widgets,id',
        ];
    }
}
