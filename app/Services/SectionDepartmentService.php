<?php

namespace App\Services;

use App\Exceptions\DeletionBlockedException;
use App\Models\SectionDepartment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SectionDepartmentService
{
    /**
     * Get lightweight section department options for dropdowns.
     */
    public function options(array $filters): Collection
    {
        $query = SectionDepartment::query()->with('department');

        if (isset($filters['department_id'])) {
            $query->byDepartment($filters['department_id']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $sections = $query->orderBy('name', 'asc')
            ->limit($filters['limit'])
            ->get(['id', 'name', 'department_id']);

        return $sections->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'department_id' => $s->department_id,
            'department_name' => $s->department?->name,
        ]);
    }

    /**
     * Get paginated list of section departments with filtering.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = SectionDepartment::with('department')->withCounts();

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%")
                    ->orWhereHas('department', function ($dq) use ($filters) {
                        $dq->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if (isset($filters['department_id'])) {
            $query->byDepartment($filters['department_id']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $query->orderBy($filters['sort_by'], $filters['sort_direction']);

        return $query->paginate($filters['per_page']);
    }

    /**
     * Get active section departments for a specific department.
     */
    public function byDepartment(int $departmentId): Collection
    {
        return SectionDepartment::with('department')
            ->withCounts()
            ->byDepartment($departmentId)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a single section department with counts.
     */
    public function show(SectionDepartment $sectionDepartment): SectionDepartment
    {
        return $sectionDepartment->load('department')->loadCount([
            'employments',
            'employments as active_employments_count' => function ($q) {
                $q->where('is_active', true);
            },
        ]);
    }

    /**
     * Create a new section department.
     */
    public function create(array $data): SectionDepartment
    {
        $data['created_by'] = Auth::id() ?? 'system';
        $sectionDepartment = SectionDepartment::create($data);

        return $sectionDepartment->load('department');
    }

    /**
     * Update a section department.
     */
    public function update(SectionDepartment $sectionDepartment, array $data): SectionDepartment
    {
        $data['updated_by'] = Auth::id() ?? 'system';
        $sectionDepartment->update($data);

        return $sectionDepartment->fresh(['department']);
    }

    /**
     * Delete a section department (blocks if active employments exist).
     */
    public function delete(SectionDepartment $sectionDepartment): void
    {
        $activeEmploymentsCount = $sectionDepartment->employments()->where('is_active', true)->count();

        if ($activeEmploymentsCount > 0) {
            throw new DeletionBlockedException(
                ["active_employments: {$activeEmploymentsCount}"],
                "Cannot delete section department with {$activeEmploymentsCount} active employments. Please reassign employments first."
            );
        }

        $sectionDepartment->delete();
    }
}
