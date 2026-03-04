<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'integer|min:1|max:100',
            'category' => ['nullable', 'string', Rule::enum(NotificationCategory::class)],
            'read_status' => 'nullable|string|in:read,unread',
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:created_at,read_at',
            'order' => 'nullable|string|in:asc,desc',
        ];
    }
}
