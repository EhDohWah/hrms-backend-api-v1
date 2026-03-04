<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ReorderWidgetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'widget_order' => 'required|array',
            'widget_order.*' => 'integer|exists:dashboard_widgets,id',
        ];
    }
}
