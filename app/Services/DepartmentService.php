<?php

namespace App\Services;

use App\Concerns\UsesQueryBuilder;
use App\Exceptions\DeletionBlockedException;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;

class DepartmentService
{
    use UsesQueryBuilder;

    /**
     * Get lightweight department list for dropdowns.
     */
    public function options(array $filters): Collection
    {
        $query = Department::query();

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query
            ->orderBy($filters['order_by'], $filters['order_direction'])
            ->limit($filters['limit'])
            ->get(['id', 'name']);
    }

    /**
     * Get paginated department list with filtering and sorting.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->buildQuery(Department::withPositionsCount(), $filters)
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['name', 'created_at'])
            ->defaultSort('name')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get a single department with full details (positions, managers).
     */
    public function show(Department $department): Department
    {
        return $department->loadCount(['positions', 'activePositions'])
            ->load(['positions' => function ($query) {
                $query->active()->with('reportsTo');
            }]);
    }

    /**
     * Create a new department.
     */
    public function create(array $data): Department
    {
        $data['created_by'] = Auth::id() ?? 'system';

        return Department::create($data);
    }

    /**
     * Update an existing department.
     */
    public function update(Department $department, array $data): Department
    {
        $data['updated_by'] = Auth::id() ?? 'system';

        $department->update($data);

        return $department->fresh();
    }

    /**
     * Delete a department after checking for blockers.
     *
     * @throws DeletionBlockedException
     */
    public function delete(Department $department): void
    {
        $blockers = $department->getDeletionBlockers();

        if (! empty($blockers)) {
            throw new DeletionBlockedException($blockers, 'Cannot delete department');
        }

        $department->delete();
    }

    /**
     * Get positions for a department with optional filters.
     */
    public function positions(Department $department, array $filters): Collection
    {
        $query = $department->positions()
            ->with(['reportsTo', 'subordinates'])
            ->withSubordinatesCount();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_manager'])) {
            $query->where('is_manager', $filters['is_manager']);
        }

        return $query->orderBy('level')->orderBy('title')->get();
    }

    /**
     * Get manager positions for a department.
     */
    public function managers(Department $department): Collection
    {
        return $department->managerPositions()
            ->with(['reportsTo', 'subordinates'])
            ->withSubordinatesCount()
            ->orderBy('level')
            ->get();
    }
}
