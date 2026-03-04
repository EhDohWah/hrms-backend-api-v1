<?php

namespace App\Services;

use App\Models\Training;
use App\Models\User;

class TrainingService
{
    /**
     * List trainings with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $perPage = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;

        $query = Training::query();

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('organizer', 'LIKE', "%{$searchTerm}%");
            });
        }

        if (! empty($params['filter_organizer'])) {
            $query->where('organizer', 'like', '%'.$params['filter_organizer'].'%');
        }

        if (! empty($params['filter_title'])) {
            $query->where('title', 'like', '%'.$params['filter_title'].'%');
        }

        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $appliedFilters = [];
        if (! empty($params['filter_organizer'])) {
            $appliedFilters['organizer'] = $params['filter_organizer'];
        }
        if (! empty($params['filter_title'])) {
            $appliedFilters['title'] = $params['filter_title'];
        }

        return [
            'paginator' => $paginator,
            'applied_filters' => $appliedFilters,
        ];
    }

    /**
     * Create a new training record.
     */
    public function store(array $data, User $performedBy): Training
    {
        $data['created_by'] = $performedBy->name ?? 'system';
        $data['updated_by'] = $performedBy->name ?? 'system';

        return Training::create($data);
    }

    /**
     * Update an existing training record.
     */
    public function update(Training $training, array $data, User $performedBy): Training
    {
        $data['updated_by'] = $performedBy->name ?? 'system';
        $training->update($data);

        return $training->fresh();
    }

    /**
     * Delete a training record.
     */
    public function destroy(Training $training): void
    {
        $training->delete();
    }
}
