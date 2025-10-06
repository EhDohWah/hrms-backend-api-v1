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
            'position_slot_id' => $this->position_slot_id,
            'org_funded_id' => $this->org_funded_id,
            'fte' => $this->fte * 100, // Convert decimal to percentage for UI
            'allocation_type' => $this->allocation_type,
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
                    'employment_type' => $this->employment->employment_type,
                    'start_date' => $this->employment->start_date,
                    'end_date' => $this->employment->end_date,
                ];
            }),

            'position_slot' => $this->whenLoaded('positionSlot', function () {
                return [
                    'id' => $this->positionSlot->id,
                    'slot_number' => $this->positionSlot->slot_number,
                    'grant_item' => $this->whenLoaded('positionSlot.grantItem', function () {
                        return [
                            'id' => $this->positionSlot->grantItem->id,
                            'grant_position' => $this->positionSlot->grantItem->grant_position,
                            'grant_salary' => $this->positionSlot->grantItem->grant_salary,
                            'grant_benefit' => $this->positionSlot->grantItem->grant_benefit,
                            'budgetline_code' => $this->positionSlot->grantItem->budgetline_code,
                            'grant' => $this->whenLoaded('positionSlot.grantItem.grant', function () {
                                return [
                                    'id' => $this->positionSlot->grantItem->grant->id,
                                    'name' => $this->positionSlot->grantItem->grant->name,
                                    'code' => $this->positionSlot->grantItem->grant->code,
                                ];
                            }),
                        ];
                    }),
                ];
            }),

            'org_funded' => $this->whenLoaded('orgFunded', function () {
                return [
                    'id' => $this->orgFunded->id,
                    'description' => $this->orgFunded->description,
                    'grant' => $this->whenLoaded('orgFunded.grant', function () {
                        return [
                            'id' => $this->orgFunded->grant->id,
                            'name' => $this->orgFunded->grant->name,
                            'code' => $this->orgFunded->grant->code,
                        ];
                    }),
                    'department' => $this->whenLoaded('orgFunded.department', function () {
                        return [
                            'id' => $this->orgFunded->department->id,
                            'name' => $this->orgFunded->department->name,
                        ];
                    }),
                    'position' => $this->whenLoaded('orgFunded.position', function () {
                        return [
                            'id' => $this->orgFunded->position->id,
                            'name' => $this->orgFunded->position->name,
                        ];
                    }),
                ];
            }),

            // Flattened data for easier UI consumption
            'grant_name' => $this->when(
                $this->allocation_type === 'grant' &&
                $this->relationLoaded('positionSlot') &&
                $this->positionSlot->relationLoaded('grantItem') &&
                $this->positionSlot->grantItem->relationLoaded('grant'),
                $this->positionSlot->grantItem->grant->name
            ) ?: $this->when(
                $this->allocation_type === 'org_funded' &&
                $this->relationLoaded('orgFunded') &&
                $this->orgFunded->relationLoaded('grant'),
                $this->orgFunded->grant->name
            ),

            'grant_code' => $this->when(
                $this->allocation_type === 'grant' &&
                $this->relationLoaded('positionSlot') &&
                $this->positionSlot->relationLoaded('grantItem') &&
                $this->positionSlot->grantItem->relationLoaded('grant'),
                $this->positionSlot->grantItem->grant->code
            ) ?: $this->when(
                $this->allocation_type === 'org_funded' &&
                $this->relationLoaded('orgFunded') &&
                $this->orgFunded->relationLoaded('grant'),
                $this->orgFunded->grant->code
            ),

            'grant_position' => $this->when(
                $this->allocation_type === 'grant' &&
                $this->relationLoaded('positionSlot') &&
                $this->positionSlot->relationLoaded('grantItem'),
                $this->positionSlot->grantItem->grant_position
            ),

            'budgetline_code' => $this->when(
                $this->allocation_type === 'grant' &&
                $this->relationLoaded('positionSlot') &&
                $this->positionSlot->relationLoaded('grantItem'),
                $this->positionSlot->grantItem->budgetline_code
            ),

            // Computed fields
            'is_active' => $this->start_date <= now() && (! $this->end_date || $this->end_date >= now()),
        ];
    }
}
