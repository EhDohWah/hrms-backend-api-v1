<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="InterSubsidiaryAdvanceResource",
 *     title="Inter Subsidiary Advance Resource",
 *     description="Inter Subsidiary Advance resource representation",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="from_subsidiary", type="string", example="SUB1"),
 *     @OA\Property(property="to_subsidiary", type="string", example="SUB2"),
 *     @OA\Property(property="via_grant_id", type="integer", example=1),
 *     @OA\Property(property="amount", type="number", format="float", example=10000.00),
 *     @OA\Property(property="advance_date", type="string", format="date", example="2023-01-15"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Advance for project expenses"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class InterSubsidiaryAdvanceResource extends JsonResource
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
            'from_subsidiary' => $this->from_subsidiary,
            'to_subsidiary' => $this->to_subsidiary,
            'via_grant_id' => $this->via_grant_id,
            'amount' => $this->amount,
            'advance_date' => $this->advance_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
