<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PayrollGrantAllocationResource",
 *     title="Payroll Grant Allocation Resource",
 *     description="Payroll Grant Allocation resource representation",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="payroll_id", type="integer", example=1),
 *     @OA\Property(property="grant_id", type="integer", example=1),
 *     @OA\Property(property="amount", type="number", format="float", example=5000.00),
 *     @OA\Property(property="is_advance", type="boolean", example=false),
 *     @OA\Property(property="description", type="string", nullable=true, example="Salary allocation"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class PayrollGrantAllocationResource extends JsonResource
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
            'payroll_id' => $this->payroll_id,
            'grant_id' => $this->grant_id,
            'amount' => $this->amount,
            'is_advance' => $this->is_advance,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
