<?php

namespace App\Services;

use App\Models\TaxSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaxSettingService
{
    /**
     * List tax settings with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $perPage = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;

        $totalCount = TaxSetting::count();

        $query = TaxSetting::query();

        if (! empty($params['search'])) {
            $query->where('setting_key', 'LIKE', '%'.$params['search'].'%');
        }

        if (! empty($params['filter_setting_type'])) {
            $types = explode(',', $params['filter_setting_type']);
            $query->whereIn('setting_type', $types);
        }

        if (! empty($params['filter_effective_year'])) {
            $years = array_map('intval', explode(',', $params['filter_effective_year']));
            $query->whereIn('effective_year', $years);
        }

        if (isset($params['filter_is_selected'])) {
            $isSelected = filter_var($params['filter_is_selected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isSelected !== null) {
                $query->where('is_selected', $isSelected);
            }
        }

        $sortBy = $params['sort_by'] ?? 'setting_type';
        $sortOrder = $params['sort_order'] ?? 'asc';

        if (in_array($sortBy, ['setting_key', 'setting_value', 'setting_type', 'effective_year'])) {
            $query->orderBy($sortBy, $sortOrder);
            if ($sortBy !== 'setting_key') {
                $query->orderBy('setting_key', 'asc');
            }
        } else {
            $query->orderBy('setting_type', 'asc')->orderBy('setting_key', 'asc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $appliedFilters = [];
        if (! empty($params['filter_setting_type'])) {
            $appliedFilters['setting_type'] = explode(',', $params['filter_setting_type']);
        }
        if (! empty($params['filter_effective_year'])) {
            $appliedFilters['effective_year'] = array_map('intval', explode(',', $params['filter_effective_year']));
        }
        if (isset($params['filter_is_selected'])) {
            $appliedFilters['is_selected'] = filter_var($params['filter_is_selected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'paginator' => $paginator,
            'applied_filters' => $appliedFilters,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Create a new tax setting.
     */
    public function store(array $data): TaxSetting
    {
        return TaxSetting::create($data);
    }

    /**
     * Get a specific tax setting.
     */
    public function show(TaxSetting $taxSetting): TaxSetting
    {
        return $taxSetting;
    }

    /**
     * Update a tax setting.
     */
    public function update(TaxSetting $taxSetting, array $data): TaxSetting
    {
        $taxSetting->update($data);

        return $taxSetting;
    }

    /**
     * Delete a tax setting.
     */
    public function destroy(TaxSetting $taxSetting): void
    {
        $taxSetting->delete();
    }

    /**
     * Get all tax settings for a specific year.
     */
    public function byYear(int $year): array
    {
        return TaxSetting::getSettingsForYear($year);
    }

    /**
     * Get a specific tax setting value by key.
     */
    public function value(string $key, int $year): array
    {
        $value = TaxSetting::getValue($key, $year);

        if ($value === null) {
            abort(404, 'Tax setting not found');
        }

        return [
            'key' => $key,
            'value' => $value,
            'year' => $year,
        ];
    }

    /**
     * Get all allowed tax setting keys organized by category.
     */
    public function allowedKeys(): array
    {
        return [
            'all_keys' => TaxSetting::getAllowedKeys(),
            'by_category' => TaxSetting::getKeysByCategory(),
        ];
    }

    /**
     * Bulk update multiple tax settings at once.
     */
    public function bulkUpdate(array $data): array
    {
        $updatedCount = 0;
        $effectiveYear = $data['effective_year'];
        $updatedBy = $data['updated_by'] ?? null;

        foreach ($data['settings'] as $settingData) {
            TaxSetting::updateOrCreate(
                [
                    'setting_key' => $settingData['setting_key'],
                    'effective_year' => $effectiveYear,
                ],
                [
                    'setting_value' => $settingData['setting_value'],
                    'setting_type' => $settingData['setting_type'],
                    'description' => $settingData['description'] ?? null,
                    'is_selected' => true,
                    'updated_by' => $updatedBy,
                ]
            );
            $updatedCount++;
        }

        return [
            'updated_count' => $updatedCount,
            'effective_year' => $effectiveYear,
        ];
    }

    /**
     * Toggle the is_selected status of a tax setting.
     */
    public function toggleSelection(TaxSetting $taxSetting): array
    {
        $oldStatus = $taxSetting->is_selected;

        $taxSetting->update([
            'is_selected' => ! $taxSetting->is_selected,
            'updated_by' => Auth::user()?->name ?? 'System',
        ]);

        try {
            Cache::tags(['tax_calculations'])->flush();
        } catch (\BadMethodCallException $e) {
            Cache::flush();
        }

        Log::info('Tax setting toggled', [
            'setting_id' => $taxSetting->id,
            'setting_key' => $taxSetting->setting_key,
            'old_status' => $oldStatus ? 'enabled' : 'disabled',
            'new_status' => $taxSetting->is_selected ? 'enabled' : 'disabled',
            'user' => Auth::user()?->name ?? 'System',
            'effective_year' => $taxSetting->effective_year,
        ]);

        return [
            'setting' => $taxSetting,
            'status' => $taxSetting->is_selected ? 'enabled' : 'disabled',
            'previous_status' => $oldStatus ? 'enabled' : 'disabled',
        ];
    }
}
