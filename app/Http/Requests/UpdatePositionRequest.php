<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
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
        $positionId = $this->route('id');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'reports_to_position_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:positions,id',
                function ($attribute, $value, $fail) use ($positionId) {
                    if ($value) {
                        // Cannot report to self
                        if ($value == $positionId) {
                            $fail('Position cannot report to itself.');
                        }

                        $supervisor = Position::find($value);
                        if ($supervisor && ! $supervisor->is_active) {
                            $fail('Cannot report to an inactive position.');
                        }

                        // Check if supervisor is in the same department
                        $departmentId = $this->input('department_id');
                        if (! $departmentId) {
                            // Get current department if not being updated
                            $currentPosition = Position::find($positionId);
                            $departmentId = $currentPosition ? $currentPosition->department_id : null;
                        }

                        if ($supervisor && $supervisor->department_id != $departmentId) {
                            $fail('Position cannot report to someone from a different department.');
                        }

                        // Prevent circular references
                        if ($this->wouldCreateCircularReference($positionId, $value)) {
                            $fail('This would create a circular reporting relationship.');
                        }
                    }
                },
            ],
            'level' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_manager' => ['sometimes', 'boolean'],
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
            'title.required' => 'Position title is required.',
            'title.max' => 'Position title cannot exceed 255 characters.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'Selected department does not exist.',
            'reports_to_position_id.exists' => 'Selected supervisor position does not exist.',
            'level.min' => 'Level must be at least 1.',
            'level.max' => 'Level cannot exceed 10.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $positionId = $this->route('id');
            $position = Position::find($positionId);

            if (! $position) {
                return;
            }

            // Additional validation: if reports_to_position_id is set, auto-calculate level
            if ($this->has('reports_to_position_id') && $this->input('reports_to_position_id')) {
                $supervisor = Position::find($this->input('reports_to_position_id'));
                if ($supervisor) {
                    // Set level to supervisor's level + 1
                    $this->merge(['level' => $supervisor->level + 1]);
                }
            }

            // Validate hierarchy constraints
            $level = $this->input('level', $position->level);
            $reportsTo = $this->input('reports_to_position_id', $position->reports_to_position_id);

            if ($level == 1 && $reportsTo) {
                $validator->errors()->add('level', 'Level 1 positions cannot report to another position.');
            }

            // Validate manager constraint for level 1
            $isManager = $this->input('is_manager', $position->is_manager);
            if ($level == 1 && ! $isManager) {
                $validator->errors()->add('is_manager', 'Level 1 positions must be managers.');
            }

            // Check if deactivating this position would affect subordinates
            if ($this->has('is_active') && ! $this->input('is_active')) {
                $activeSubordinatesCount = $position->activeSubordinates()->count();
                if ($activeSubordinatesCount > 0) {
                    $validator->errors()->add('is_active', "Cannot deactivate position with {$activeSubordinatesCount} active subordinates. Please reassign subordinates first.");
                }
            }
        });
    }

    /**
     * Check if the proposed reporting relationship would create a circular reference.
     */
    private function wouldCreateCircularReference($positionId, $supervisorId): bool
    {
        $current = Position::find($supervisorId);
        $visited = [];

        while ($current && ! in_array($current->id, $visited)) {
            if ($current->id == $positionId) {
                return true; // Circular reference found
            }

            $visited[] = $current->id;
            $current = $current->reportsTo;
        }

        return false;
    }
}
