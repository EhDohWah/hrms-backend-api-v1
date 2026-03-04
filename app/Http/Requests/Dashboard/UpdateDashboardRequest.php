<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|exists:dashboard_widgets,id',
            'widgets.*.order' => 'integer|min:0',
            'widgets.*.is_visible' => 'boolean',
            'widgets.*.is_collapsed' => 'boolean',
            'widgets.*.user_config' => 'nullable|array',
        ];
    }
}
