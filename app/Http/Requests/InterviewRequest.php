<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewRequest extends FormRequest
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
        return [
            'candidate_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:10',
            'job_position' => 'required|string|max:255',
            'interviewer_name' => 'nullable|string',
            'interview_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after:start_time',
            'interview_mode' => 'nullable|string',
            'interview_status' => 'nullable|string',
            'hired_status' => 'nullable|string',
            'score' => 'nullable|numeric|between:0,100',
            'feedback' => 'nullable|string',
            'reference_info' => 'nullable|string',
            'created_by' => 'nullable|string',
            'updated_by' => 'nullable|string'
        ];
    }
}
