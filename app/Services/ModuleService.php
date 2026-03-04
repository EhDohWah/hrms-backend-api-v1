<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ModuleService
{
    /**
     * Get all active modules with pagination and filtering.
     */
    public function list(array $params): LengthAwarePaginator
    {
        $query = Module::active()->with('children');

        if (! empty($params['category'])) {
            $query->byCategory($params['category']);
        }

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('display_name', 'LIKE', "%{$searchTerm}%");
            });
        }

        return $query->orderBy($params['sort_by'], $params['sort_order'])
            ->paginate($params['per_page'], ['*'], 'page', $params['page']);
    }

    /**
     * Get root modules with active children in hierarchical tree structure.
     */
    public function hierarchical(): Collection
    {
        return Module::active()
            ->ordered()
            ->rootModules()
            ->with('activeChildren')
            ->get();
    }

    /**
     * Get modules grouped by category.
     */
    public function byCategory(): Collection
    {
        return Module::active()
            ->ordered()
            ->get()
            ->groupBy('category');
    }

    /**
     * Get flat list of all permissions from all active modules.
     */
    public function permissions(): array
    {
        return Module::active()
            ->get()
            ->flatMap(fn (Module $module) => $module->getAllPermissions())
            ->unique()
            ->values()
            ->all();
    }
}
