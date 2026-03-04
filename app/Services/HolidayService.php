<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class HolidayService
{
    /**
     * Get paginated holidays with filtering and sorting.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;
        $sortBy = $filters['sort_by'] ?? 'date_asc';

        $query = Holiday::query();

        // Apply search filter
        if (! empty($filters['search'])) {
            $searchTerm = trim($filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('name_th', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Apply year filter
        if (! empty($filters['year'])) {
            $query->forYear($filters['year']);
        }

        // Apply active status filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Apply date range filter
        if (! empty($filters['from'])) {
            $query->where('date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('date', '<=', $filters['to']);
        }

        // Apply sorting using match
        [$column, $direction] = match ($sortBy) {
            'date_desc' => ['date', 'desc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'recently_added' => ['created_at', 'desc'],
            default => ['date', 'asc'],
        };
        $query->orderBy($column, $direction);

        return $query->paginate($perPage);
    }

    /**
     * Create a new holiday.
     */
    public function create(array $data, User $performedBy): Holiday
    {
        $data['created_by'] = $performedBy->name ?? 'System';

        return Holiday::create($data);
    }

    /**
     * Update an existing holiday.
     */
    public function update(Holiday $holiday, array $data, User $performedBy): Holiday
    {
        $data['updated_by'] = $performedBy->name ?? 'System';

        $holiday->update($data);

        return $holiday->fresh();
    }

    /**
     * Delete a holiday if it has no compensation records.
     *
     * Returns ['success' => bool, 'message' => string, 'status' => int].
     */
    public function delete(Holiday $holiday): array
    {
        if ($holiday->compensationRecords()->exists()) {
            return [
                'success' => false,
                'message' => 'Cannot delete holiday with existing compensation records. Deactivate it instead.',
                'status' => 422,
            ];
        }

        $holiday->delete();

        return [
            'success' => true,
            'message' => 'Holiday deleted successfully',
            'status' => 200,
        ];
    }

    /**
     * Get holidays for dropdown options.
     */
    public function options(array $filters): Collection
    {
        $query = Holiday::query()->orderBy('date', 'asc');

        if (! empty($filters['year'])) {
            $query->forYear($filters['year']);
        }

        if ($filters['active_only'] ?? true) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Bulk create holidays, skipping dates that already exist.
     *
     * Returns ['created' => Collection, 'created_count' => int, 'skipped_dates' => array, 'skipped_count' => int].
     */
    public function storeBatch(array $holidays, User $performedBy): array
    {
        $createdBy = $performedBy->name ?? 'System';
        $createdHolidays = [];
        $skippedDates = [];

        foreach ($holidays as $holidayData) {
            // Skip if a holiday already exists on this date
            if (Holiday::where('date', $holidayData['date'])->exists()) {
                $skippedDates[] = $holidayData['date'];

                continue;
            }

            $createdHolidays[] = Holiday::create([
                'name' => $holidayData['name'],
                'name_th' => $holidayData['name_th'] ?? null,
                'date' => $holidayData['date'],
                'year' => date('Y', strtotime($holidayData['date'])),
                'description' => $holidayData['description'] ?? null,
                'is_active' => true,
                'created_by' => $createdBy,
            ]);
        }

        return [
            'created' => collect($createdHolidays),
            'created_count' => count($createdHolidays),
            'skipped_dates' => $skippedDates,
            'skipped_count' => count($skippedDates),
        ];
    }

    /**
     * Get active holidays within a date range.
     */
    public function inRange(string $startDate, string $endDate): Collection
    {
        return Holiday::active()
            ->betweenDates($startDate, $endDate)
            ->orderBy('date', 'asc')
            ->get();
    }
}
