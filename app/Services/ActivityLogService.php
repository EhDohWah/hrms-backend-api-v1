<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogService
{
    /**
     * Get paginated activity logs with optional filters.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = ActivityLog::with('user:id,name,email')
            ->latest('created_at');

        if (! empty($filters['subject_id']) && ! empty($filters['subject_type'])) {
            $query->forSubject($filters['subject_type'], $filters['subject_id']);
        } elseif (! empty($filters['subject_type'])) {
            $query->bySubjectType($filters['subject_type']);
        }

        if (! empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $query->dateRange(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null
            );
        }

        return $query->paginate($filters['per_page']);
    }

    /**
     * Get paginated activity logs for a specific subject.
     */
    public function forSubject(string $type, int $id, int $perPage): LengthAwarePaginator
    {
        return ActivityLog::with('user:id,name,email')
            ->forSubject($type, $id)
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Get recent activity logs across all types.
     */
    public function recent(int $limit): Collection
    {
        return ActivityLog::with('user:id,name,email')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
