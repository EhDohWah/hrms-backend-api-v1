<?php

namespace App\Http\Requests;

use App\Enums\HiredStatus;
use App\Enums\InterviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for storing Interviews.
 */
class StoreInterviewRequest extends FormRequest
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
            'interview_status' => [
                'nullable',
                'string',
                Rule::enum(InterviewStatus::class),
            ],
            'hired_status' => [
                'nullable',
                'string',
                Rule::enum(HiredStatus::class),
            ],
            'score' => 'nullable|numeric|between:0,100',
            'feedback' => 'nullable|string',
            'reference_info' => 'nullable|string',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'candidate_name.required' => 'The candidate name is required.',
            'job_position.required' => 'The job position is required.',
            'end_time.after' => 'The end time must be after the start time.',
            'score.between' => 'The score must be between 0 and 100.',
        ];
    }
}
