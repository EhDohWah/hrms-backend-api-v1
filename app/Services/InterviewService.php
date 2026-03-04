<?php

namespace App\Services;

use App\Concerns\UsesQueryBuilder;
use App\Models\Interview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;

class InterviewService
{
    use UsesQueryBuilder;

    /**
     * Build and execute paginated list query with filters and sorting.
     */
    public function list(array $validated): LengthAwarePaginator
    {
        return $this->buildQuery(Interview::class, $validated)
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('job_position'),
                AllowedFilter::exact('hired_status'),
            ])
            ->allowedSorts(['created_at', 'candidate_name', 'interview_date', 'job_position', 'hired_status'])
            ->defaultSort('-created_at')
            ->paginate($validated['per_page'] ?? 10);
    }

    /**
     * Create a new interview record.
     */
    public function create(array $validated): Interview
    {
        $validated['created_by'] = Auth::user()->name ?? 'System';

        return Interview::create($validated);
    }

    /**
     * Update an existing interview record.
     */
    public function update(Interview $interview, array $validated): Interview
    {
        $validated['updated_by'] = Auth::user()->name ?? 'System';

        $interview->update($validated);

        return $interview;
    }

    /**
     * Delete an interview record.
     */
    public function delete(Interview $interview): void
    {
        $interview->delete();
    }

    /**
     * Find an interview by candidate name (case-insensitive).
     */
    public function findByCandidateName(string $candidateName): ?Interview
    {
        return Interview::whereRaw('LOWER(candidate_name) = ?', [strtolower($candidateName)])
            ->first();
    }
}
