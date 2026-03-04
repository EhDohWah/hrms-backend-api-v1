<?php

namespace App\Services;

use App\Models\Lookup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LookupService
{
    /**
     * Get all lookups with pagination and filtering.
     */
    public function list(array $params): LengthAwarePaginator
    {
        $query = Lookup::query();

        if (! empty($params['filter_type'])) {
            $types = array_map('trim', explode(',', $params['filter_type']));
            $query->whereIn('type', $types);
        }

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('type', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('value', 'LIKE', "%{$searchTerm}%");
            });
        }

        return $query->orderBy($params['sort_by'], $params['sort_order'])
            ->paginate($params['per_page'], ['*'], 'page', $params['page']);
    }

    /**
     * Get all lookups organized by type.
     */
    public function grouped(): array
    {
        return Lookup::getAllLookups();
    }

    /**
     * Search lookups with advanced filtering.
     */
    public function search(array $params): LengthAwarePaginator
    {
        $query = Lookup::query();

        if (! empty($params['types'])) {
            $types = array_map('trim', explode(',', $params['types']));
            $query->whereIn('type', $types);
        }

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('type', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('value', 'LIKE', "%{$searchTerm}%");
            });
        }

        if (! empty($params['value'])) {
            $valueTerm = trim($params['value']);
            $query->where('value', 'LIKE', "%{$valueTerm}%");
        }

        return $query->orderBy($params['sort_by'], $params['sort_order'])
            ->paginate($params['per_page'], ['*'], 'page', $params['page']);
    }

    /**
     * Create a new lookup value.
     */
    public function store(array $data, User $performedBy): Lookup
    {
        $data['created_by'] = $performedBy->name ?? 'System';

        return Lookup::create($data);
    }

    /**
     * Update an existing lookup value.
     */
    public function update(Lookup $lookup, array $data, User $performedBy): Lookup
    {
        $data['updated_by'] = $performedBy->name ?? 'System';

        $lookup->update($data);

        return $lookup->fresh();
    }

    /**
     * Delete a lookup value.
     */
    public function destroy(Lookup $lookup): void
    {
        $lookup->delete();
    }

    /**
     * Get all distinct lookup types.
     */
    public function types(): Collection
    {
        return Lookup::getAllTypes();
    }

    /**
     * Check if a lookup type exists.
     */
    public function typeExists(string $type): bool
    {
        return Lookup::typeExists($type);
    }

    /**
     * Get lookup values by type.
     */
    public function byType(string $type): Collection
    {
        return Lookup::getByType($type);
    }
}
