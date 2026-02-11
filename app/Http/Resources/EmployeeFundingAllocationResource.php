<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeFundingAllocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employment_id' => $this->employment_id,
            'grant_item_id' => $this->grant_item_id,
            // Always include grant_id for frontend consumption (null if relationship not loaded)
            // This ensures consistent data structure regardless of eager loading
            'grant_id' => $this->relationLoaded('grantItem') && $this->grantItem !== null
                ? $this->grantItem->grant_id
                : null,
            'fte' => $this->fte * 100, // Convert decimal to percentage for UI
            'status' => $this->status, // Include status so frontend can filter if needed
            'allocated_amount' => $this->allocated_amount,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationship data
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                    'full_name' => $this->employee->first_name_en.' '.$this->employee->last_name_en,
                ];
            }),

            'employment' => $this->whenLoaded('employment', function () {
                return [
                    'id' => $this->employment->id,
                    'start_date' => $this->employment->start_date,
                    'end_probation_date' => $this->employment->end_probation_date,
                ];
            }),

            // Direct grant item relationship (for grant allocations)
            'grant_item' => $this->when(
                $this->relationLoaded('grantItem') && $this->grantItem !== null,
                function () {
                    return [
                        'id' => $this->grantItem->id,
                        'grant_position' => $this->grantItem->grant_position,
                        'grant_salary' => $this->grantItem->grant_salary,
                        'grant_benefit' => $this->grantItem->grant_benefit,
                        'budgetline_code' => $this->grantItem->budgetline_code,
                        'grant_position_number' => $this->grantItem->grant_position_number,
                        'grant' => $this->when(
                            $this->grantItem->relationLoaded('grant') && $this->grantItem->grant !== null,
                            function () {
                                return [
                                    'id' => $this->grantItem->grant->id,
                                    'name' => $this->grantItem->grant->name,
                                    'code' => $this->grantItem->grant->code,
                                ];
                            }
                        ),
                    ];
                }
            ),

            // Flattened data for easier UI consumption
            'grant_name' => $this->when(
                $this->relationLoaded('grantItem') &&
                $this->grantItem !== null &&
                $this->grantItem->relationLoaded('grant') &&
                $this->grantItem->grant !== null,
                function () {
                    return $this->grantItem->grant->name;
                }
            ),

            'grant_code' => $this->when(
                $this->relationLoaded('grantItem') &&
                $this->grantItem !== null &&
                $this->grantItem->relationLoaded('grant') &&
                $this->grantItem->grant !== null,
                function () {
                    return $this->grantItem->grant->code;
                }
            ),

            'grant_position' => $this->when(
                $this->relationLoaded('grantItem') &&
                $this->grantItem !== null,
                function () {
                    return $this->grantItem->grant_position;
                }
            ),

            'budgetline_code' => $this->when(
                $this->relationLoaded('grantItem') &&
                $this->grantItem !== null,
                function () {
                    return $this->grantItem->budgetline_code;
                }
            ),

            // Computed fields
            'is_active' => $this->start_date <= now() && (! $this->end_date || $this->end_date >= now()),
        ];
    }
}
