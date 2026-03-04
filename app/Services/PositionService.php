<?php

namespace App\Services;

use App\Concerns\UsesQueryBuilder;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DeletionBlockedException;
use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;

class PositionService
{
    use UsesQueryBuilder;

    /**
     * Get lightweight position list for dropdowns.
     */
    public function options(array $filters): \Illuminate\Support\Collection
    {
        $query = Position::query()->with('department');

        if (isset($filters['department_id'])) {
            $query->inDepartment($filters['department_id']);
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $positions = $query
            ->orderBy($filters['order_by'], $filters['order_direction'])
            ->limit($filters['limit'])
            ->get(['id', 'title', 'department_id']);

        return $positions->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'department_id' => $p->department_id,
            'department_name' => $p->department?->name,
        ]);
    }

    /**
     * Get paginated position list with filtering and sorting.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $baseQuery = Position::with(['department', 'manager'])->withDirectReportsCount();

        $query = $this->buildQuery($baseQuery, $filters)
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::scope('department_id', 'inDepartment'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_manager'),
                AllowedFilter::scope('level', 'atLevel'),
            ])
            ->allowedSorts(['title', 'level', 'is_active', 'is_manager', 'created_at'])
            ->defaultSort('title');

        // Secondary sort by title for consistency
        if (($filters['sort_by'] ?? 'title') !== 'title') {
            $query->orderBy('title', 'asc');
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get a single position with full details.
     */
    public function show(Position $position): Position
    {
        return $position
            ->load(['department', 'manager', 'directReports'])
            ->loadCount('directReports');
    }

    /**
     * Create a new position.
     */
    public function create(array $data): Position
    {
        $data['created_by'] = Auth::id() ?? 'system';

        $position = Position::create($data);

        return $position->load(['department', 'reportsTo']);
    }

    /**
     * Update an existing position.
     */
    public function update(Position $position, array $data): Position
    {
        $data['updated_by'] = Auth::id() ?? 'system';

        $position->update($data);

        return $position->fresh(['department', 'reportsTo']);
    }

    /**
     * Delete a position after checking for active subordinates.
     *
     * @throws DeletionBlockedException
     */
    public function delete(Position $position): void
    {
        $activeSubordinatesCount = $position->activeSubordinates()->count();

        if ($activeSubordinatesCount > 0) {
            throw new DeletionBlockedException(
                ["active_subordinates: {$activeSubordinatesCount}"],
                "Cannot delete position with {$activeSubordinatesCount} active subordinates. Please reassign subordinates first."
            );
        }

        $position->delete();
    }

    /**
     * Get direct reports for a manager position.
     *
     * @throws BusinessRuleException
     */
    public function directReports(Position $position): Collection
    {
        if (! $position->is_manager) {
            throw new BusinessRuleException('This position is not a manager');
        }

        return $position->directReports()
            ->with(['department'])
            ->orderBy('title')
            ->get();
    }
}
