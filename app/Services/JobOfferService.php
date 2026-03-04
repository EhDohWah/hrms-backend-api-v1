<?php

namespace App\Services;

use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PDF;

class JobOfferService
{
    /**
     * Sort field mapping: maps user-friendly sort_by values to actual DB columns.
     */
    private const SORT_MAPPING = [
        'job_offer_id' => 'id',
        'candidate_name' => 'candidate_name',
        'position_name' => 'position_name',
        'date' => 'date',
        'status' => 'acceptance_status',
        'created_at' => 'created_at',
    ];

    /**
     * Retrieve paginated job offers with filters, search, and sorting.
     */
    public function list(array $validated): LengthAwarePaginator
    {
        $query = JobOffer::query();

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        if (! empty($validated['filter_position'])) {
            $query->filterByPosition($validated['filter_position']);
        }

        if (! empty($validated['filter_status'])) {
            $query->filterByStatus($validated['filter_status']);
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $sortColumn = self::SORT_MAPPING[$sortBy] ?? 'created_at';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->paginate(
            perPage: $validated['per_page'] ?? 10,
            page: $validated['page'] ?? 1,
        );
    }

    /**
     * Create a new job offer.
     */
    public function create(array $data, User $performedBy): JobOffer
    {
        $data['created_by'] = $performedBy->name ?? 'System';

        return JobOffer::create($data);
    }

    /**
     * Update an existing job offer.
     */
    public function update(JobOffer $jobOffer, array $data, User $performedBy): JobOffer
    {
        $data['updated_by'] = $performedBy->name ?? 'System';

        $jobOffer->update($data);

        return $jobOffer->fresh();
    }

    /**
     * Delete a job offer.
     */
    public function delete(JobOffer $jobOffer): void
    {
        $jobOffer->delete();
    }

    /**
     * Find a job offer by candidate name (case-insensitive, partial match).
     */
    public function findByCandidateName(string $candidateName): ?JobOffer
    {
        return JobOffer::whereRaw('LOWER(candidate_name) LIKE ?', ['%'.strtolower($candidateName).'%'])
            ->first();
    }

    /**
     * Generate a PDF job offer letter for the given custom offer ID.
     *
     * @return \Illuminate\Http\Response
     */
    public function generatePdf(string $customOfferId)
    {
        $jobOffer = JobOffer::where('custom_offer_id', $customOfferId)->first();

        if (! $jobOffer) {
            return null;
        }

        $data = [
            'date' => $jobOffer->date ? $this->formatDateWithSuperscript($jobOffer->date) : now()->format('dS F, Y'),
            'position' => $jobOffer->position_name,
            'subject' => 'Job Offer',
            'probation_salary' => $jobOffer->probation_salary ? number_format($jobOffer->probation_salary, 2) : 'N/A',
            'pass_probation_salary' => $jobOffer->pass_probation_salary ? number_format($jobOffer->pass_probation_salary, 2) : 'N/A',
            'acceptance_deadline' => $jobOffer->acceptance_deadline ? $this->formatDateWithSuperscript($jobOffer->acceptance_deadline) : 'N/A',
            'employee_name' => $jobOffer->candidate_name,
        ];

        $pdf = PDF::loadView('jobOffer', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'job-offer-'.$jobOffer->candidate_name.'.pdf';

        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Format a date with superscript ordinal suffix (e.g. 1<sup>st</sup> January, 2024).
     */
    private function formatDateWithSuperscript($date): string
    {
        if (! $date) {
            return 'N/A';
        }

        $day = date('j', strtotime($date));
        $monthYear = date('F, Y', strtotime($date));

        if ($day % 10 == 1 && $day != 11) {
            $suffix = 'st';
        } elseif ($day % 10 == 2 && $day != 12) {
            $suffix = 'nd';
        } elseif ($day % 10 == 3 && $day != 13) {
            $suffix = 'rd';
        } else {
            $suffix = 'th';
        }

        return $day.'<sup>'.$suffix.'</sup> '.$monthYear;
    }
}
