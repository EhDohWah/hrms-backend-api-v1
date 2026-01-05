<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="InterOrganizationAdvanceResource",
 *     title="Inter Organization Advance Resource",
 *     description="Inter Organization Advance resource representation",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="from_organization", type="string", example="ORG1"),
 *     @OA\Property(property="to_organization", type="string", example="ORG2"),
 *     @OA\Property(property="via_grant_id", type="integer", example=1),
 *     @OA\Property(property="amount", type="number", format="float", example=10000.00),
 *     @OA\Property(property="advance_date", type="string", format="date", example="2023-01-15"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Advance for project expenses"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class InterOrganizationAdvanceResource extends JsonResource
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
            'payroll_grant_allocation_id' => $this->payroll_grant_allocation_id,
            'from_organization' => $this->from_organization,
            'to_organization' => $this->to_organization,
            'via_grant_id' => $this->via_grant_id,
            'amount' => $this->amount,
            'advance_date' => $this->advance_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
