<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $siteId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('sites', 'name')->ignore($siteId)],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('sites', 'code')->ignore($siteId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A site with this name already exists.',
            'name.max' => 'Site name cannot exceed 100 characters.',
            'code.unique' => 'A site with this code already exists.',
            'code.max' => 'Site code cannot exceed 20 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'contact_email.email' => 'Please provide a valid email address.',
        ];
    }
}
