<?php

namespace App\Services;

use App\Models\PayrollPolicySetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class PayrollPolicySettingService
{
    /**
     * List all payroll policy settings ordered by effective date.
     *
     * @return array{policies: Collection, active_policy: PayrollPolicySetting|null}
     */
    public function list(): array
    {
        return [
            'policies' => PayrollPolicySetting::query()
                ->orderBy('effective_date', 'desc')
                ->get(),
            'active_policy' => PayrollPolicySetting::getActivePolicy(),
        ];
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
