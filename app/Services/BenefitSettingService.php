<?php

namespace App\Services;

use App\Models\BenefitSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BenefitSettingService
{
    /**
     * Retrieve all benefit settings with optional filters, ordered by category then key.
     */
    public function list(array $filters): Collection
    {
        $query = BenefitSetting::query();

        // Filter by is_active (boolean already cast in prepareForValidation)
        if (array_key_exists('filter_is_active', $filters)) {
            $query->where('is_active', $filters['filter_is_active']);
        }

        // Filter by setting_type
        if (! empty($filters['filter_setting_type'])) {
            $query->where('setting_type', $filters['filter_setting_type']);
        }

        // Filter by category (uses model scope)
        if (! empty($filters['filter_category'])) {
            $query->byCategory($filters['filter_category']);
        }

        return $query->orderBy('category', 'asc')
            ->orderBy('setting_key', 'asc')
            ->get();
    }

    /**
     * Show a single benefit setting.
     */
    public function show(BenefitSetting $benefitSetting): BenefitSetting
    {
        return $benefitSetting;
    }

    /**
     * Create a new benefit setting.
     */
    public function create(array $data): BenefitSetting
    {
        $data['created_by'] = Auth::user()?->name ?? 'system';
        $data['updated_by'] = Auth::user()?->name ?? 'system';

        return BenefitSetting::create($data);
    }

    /**
     * Update an existing benefit setting.
     */
    public function update(BenefitSetting $benefitSetting, array $data): BenefitSetting
    {
        $data['updated_by'] = Auth::user()?->name ?? 'system';

        $benefitSetting->update($data);

        return $benefitSetting->fresh();
    }

    /**
     * Delete (soft-delete) a benefit setting.
     */
    public function delete(BenefitSetting $benefitSetting): void
    {
        $benefitSetting->delete();
    }
}
