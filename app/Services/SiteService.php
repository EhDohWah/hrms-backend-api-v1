<?php

namespace App\Services;

use App\Concerns\UsesQueryBuilder;
use App\Exceptions\DeletionBlockedException;
use App\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;

class SiteService
{
    use UsesQueryBuilder;

    /**
     * Get lightweight site list for dropdowns.
     */
    public function options(array $filters): Collection
    {
        $query = Site::query();

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query
            ->orderBy('name', 'asc')
            ->limit($filters['limit'])
            ->get(['id', 'name', 'code']);
    }

    /**
     * Get paginated site list with filtering and sorting.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->buildQuery(Site::withCounts(), $filters)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['name', 'code', 'created_at'])
            ->defaultSort('name')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get a single site with employment counts.
     */
    public function show(Site $site): Site
    {
        return $site->loadCount([
            'employments',
            'employments as active_employments_count' => function ($q) {
                $q->where('is_active', true);
            },
        ]);
    }

    /**
     * Create a new site.
     */
    public function create(array $data): Site
    {
        $data['created_by'] = Auth::id() ?? 'system';

        return Site::create($data);
    }

    /**
     * Update an existing site.
     */
    public function update(Site $site, array $data): Site
    {
        $data['updated_by'] = Auth::id() ?? 'system';

        $site->update($data);

        return $site->fresh();
    }

    /**
     * Delete a site after checking for active employments.
     *
     * @throws DeletionBlockedException
     */
    public function delete(Site $site): void
    {
        $activeEmploymentsCount = $site->employments()->where('is_active', true)->count();

        if ($activeEmploymentsCount > 0) {
            throw new DeletionBlockedException(
                ["active_employments: {$activeEmploymentsCount}"],
                "Cannot delete site with {$activeEmploymentsCount} active employments. Please reassign employments first."
            );
        }

        $site->delete();
    }
}
