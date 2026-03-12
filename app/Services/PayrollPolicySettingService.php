<?php

namespace App\Services;

use App\Models\PayrollPolicySetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class PayrollPolicySettingService
{
    /**
     * List all payroll policy settings with optional filters.
     */
    public function list(array $filters = []): Collection
    {
        $query = PayrollPolicySetting::query();

        if (array_key_exists('filter_is_active', $filters)) {
            $query->where('is_active', $filters['filter_is_active']);
        }

        if (! empty($filters['filter_category'])) {
            $query->where('category', $filters['filter_category']);
        }

        return $query->orderBy('policy_key')->get();
    }

    /**
     * Get a specific payroll policy setting by ID.
     */
    public function show(int $id): PayrollPolicySetting
    {
        return PayrollPolicySetting::findOrFail($id);
    }

    /**
     * Create a new payroll policy setting.
     */
    public function store(array $data): PayrollPolicySetting
    {
        $data['created_by'] = Auth::user()?->name ?? 'system';
        $data['updated_by'] = Auth::user()?->name ?? 'system';

        return PayrollPolicySetting::create($data);
    }

    /**
     * Update an existing payroll policy setting.
     */
    public function update(int $id, array $data): PayrollPolicySetting
    {
        $policy = PayrollPolicySetting::findOrFail($id);

        $data['updated_by'] = Auth::user()?->name ?? 'system';
        $policy->update($data);

        return $policy->fresh();
    }

    /**
     * Delete a payroll policy setting.
     */
    public function destroy(int $id): void
    {
        $policy = PayrollPolicySetting::findOrFail($id);
        $policy->delete();
    }
}
