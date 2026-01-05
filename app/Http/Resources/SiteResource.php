<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiteResource",
 *     title="Site Resource",
 *     description="Site API response resource",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Site ID"),
 *     @OA\Property(property="name", type="string", description="Site name"),
 *     @OA\Property(property="code", type="string", description="Site code"),
 *     @OA\Property(property="description", type="string", nullable=true, description="Site description"),
 *     @OA\Property(property="address", type="string", nullable=true, description="Site address"),
 *     @OA\Property(property="contact_person", type="string", nullable=true, description="Contact person name"),
 *     @OA\Property(property="contact_phone", type="string", nullable=true, description="Contact phone number"),
 *     @OA\Property(property="contact_email", type="string", nullable=true, description="Contact email address"),
 *     @OA\Property(property="is_active", type="boolean", description="Whether the site is active"),
 *     @OA\Property(property="employments_count", type="integer", description="Total number of employments at this site"),
 *     @OA\Property(property="active_employments_count", type="integer", description="Number of active employments at this site"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date")
 * )
 */
class SiteResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'is_active' => $this->is_active,
            'employments_count' => $this->whenCounted('employments'),
            'active_employments_count' => $this->active_employments_count ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
