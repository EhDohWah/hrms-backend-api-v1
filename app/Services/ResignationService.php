<?php

namespace App\Services;

use App\Concerns\UsesQueryBuilder;
use App\Enums\FundingAllocationStatus;
use App\Enums\ResignationAcknowledgementStatus;
use App\Exceptions\Resignation\ResignationNoEmploymentException;
use App\Exceptions\Resignation\ResignationNotAcknowledgedException;
use App\Exceptions\Resignation\ResignationNotPendingException;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Resignation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class ResignationService
{
    use UsesQueryBuilder;

    /**
     * Build and execute paginated list query with filters and sorting.
     */
    public function list(array $validated): LengthAwarePaginator
    {
        return $this->buildQuery(Resignation::query()->withRelations(), $validated)
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('acknowledgement_status'),
                AllowedFilter::exact('department_id'),
                AllowedFilter::partial('reason'),
            ])
            ->allowedSorts(['resignation_date', 'last_working_date', 'created_at', 'acknowledgement_status'])
            ->defaultSort('-resignation_date')
            ->paginate($validated['per_page'] ?? 15);
    }

    /**
     * Load detailed relations on an existing resignation for display.
     */
    public function show(Resignation $resignation): Resignation
    {
        $resignation->loadMissing([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employee.employment:id,employee_id,department_id,position_id',
            'employee.employment.department:id,name',
            'employee.employment.position:id,title',
            'department:id,name',
            'position:id,title',
            'acknowledgedBy:id,name',
        ]);

        return $resignation;
    }

    /**
     * Create a new resignation record.
     */
    public function create(array $validated): Resignation
    {
        $validated['created_by'] = Auth::user()->name ?? 'System';
        $validated['acknowledgement_status'] = $validated['acknowledgement_status'] ?? 'Pending';

        return DB::transaction(function () use ($validated) {
            $resignation = Resignation::create($validated);

            $resignation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title',
                'acknowledgedBy:id,name',
            ]);

            return $resignation;
        });
    }

    /**
     * Update an existing resignation record.
     */
    public function update(Resignation $resignation, array $validated): Resignation
    {
        $validated['updated_by'] = Auth::user()->name ?? 'System';

        return DB::transaction(function () use ($resignation, $validated) {
            $resignation->update($validated);

            $resignation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title',
                'acknowledgedBy:id,name',
            ]);

            return $resignation;
        });
    }

    /**
     * Soft delete a resignation record.
     */
    public function delete(Resignation $resignation): void
    {
        $resignation->delete();
    }

    /**
     * Acknowledge or reject a resignation.
     *
     * When acknowledged:
     * - Sets employment.end_date to the resignation's last_working_date
     * - Closes all active funding allocations for that employment
     *
     * @throws ResignationNotPendingException
     * @throws ResignationNoEmploymentException
     */
    public function acknowledge(Resignation $resignation, string $action): Resignation
    {
        if ($resignation->acknowledgement_status !== ResignationAcknowledgementStatus::Pending) {
            throw new ResignationNotPendingException;
        }

        $user = Auth::user();

        DB::transaction(function () use ($resignation, $action, $user) {
            match ($action) {
                'acknowledge' => $resignation->acknowledge($user),
                'reject' => $resignation->reject($user),
            };

            if ($action === 'acknowledge') {
                $employment = Employment::where('employee_id', $resignation->employee_id)
                    ->whereNull('end_date')
                    ->first();

                if (! $employment) {
                    throw new ResignationNoEmploymentException;
                }

                // Set employment end date to last working date
                $employment->update([
                    'end_date' => $resignation->last_working_date,
                    'updated_by' => $user->name ?? 'System',
                ]);

                // Close all active funding allocations for this employment
                $employment->employeeFundingAllocations()
                    ->where('status', FundingAllocationStatus::Active->value)
                    ->update([
                        'status' => FundingAllocationStatus::Closed->value,
                        'updated_by' => $user->name ?? 'System',
                    ]);
            }
        });

        $resignation->load([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title',
            'acknowledgedBy:id,name',
        ]);

        return $resignation;
    }

    /**
     * Search employees for resignation assignment.
     */
    public function searchEmployees(array $validated): Collection
    {
        $limit = $validated['limit'] ?? 10;
        $search = $validated['search'] ?? '';

        $query = Employee::query()
            ->with(['employment.department:id,name', 'employment.position:id,title'])
            ->whereHas('employment', function ($q) {
                $q->whereNull('end_date');
            })
            ->whereDoesntHave('resignations', function ($q) {
                $q->whereIn('acknowledgement_status', [
                    ResignationAcknowledgementStatus::Pending->value,
                    ResignationAcknowledgementStatus::Acknowledged->value,
                ]);
            });

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('staff_id', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$search}%"]);
            });
        }

        return $query->limit($limit)->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'full_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
                'department' => $employee->employment?->department?->name,
                'position' => $employee->employment?->position?->title,
                'organization' => $employee->employment?->organization,
            ];
        });
    }

    /**
     * Generate a recommendation letter PDF for a resigned employee.
     *
     *
     * @return \Barryvdh\DomPDF\PDF
     *
     * @throws ResignationNotAcknowledgedException
     */
    public function generateRecommendationLetter(Resignation $resignation)
    {
        $resignation->loadMissing(['employee', 'department', 'position']);

        if ($resignation->acknowledgement_status !== ResignationAcknowledgementStatus::Acknowledged) {
            throw new ResignationNotAcknowledgedException;
        }

        $employee = $resignation->employee;

        // Get employment history for this employee
        $employmentHistory = Employment::where('employee_id', $employee->id)
            ->with(['department:id,name', 'position:id,title'])
            ->orderBy('start_date', 'asc')
            ->get();

        // Calculate total tenure
        $firstEmployment = $employmentHistory->first();
        $startDate = $firstEmployment?->start_date;
        $endDate = $resignation->last_working_date;

        $tenureText = $this->formatTenureText($startDate, $endDate);

        // Prepare data for the recommendation letter template
        $data = [
            'date' => now()->format('jS F, Y'),
            'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
            'staff_id' => $employee->staff_id,
            'organization' => $employee->employment?->organization ?? 'SMRU',
            'current_position' => $resignation->position?->title ?? 'N/A',
            'current_department' => $resignation->department?->name ?? 'N/A',
            'start_date' => $startDate ? Carbon::parse($startDate)->format('jS F, Y') : 'N/A',
            'end_date' => $endDate ? Carbon::parse($endDate)->format('jS F, Y') : 'N/A',
            'tenure_text' => $tenureText,
            'employment_history' => $employmentHistory->map(function ($emp) {
                return [
                    'position' => $emp->position?->title ?? 'N/A',
                    'department' => $emp->department?->name ?? 'N/A',
                    'start_date' => $emp->start_date ? Carbon::parse($emp->start_date)->format('M Y') : 'N/A',
                    'end_date' => $emp->end_date ? Carbon::parse($emp->end_date)->format('M Y') : 'Present',
                ];
            }),
        ];

        $pdf = Pdf::loadView('recommendationLetter', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'recommendation-letter-'.$employee->staff_id.'-'.date('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Format tenure text from start and end dates.
     */
    private function formatTenureText($startDate, $endDate): string
    {
        if (! $startDate || ! $endDate) {
            return 'N/A';
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $tenureYears = $start->diffInYears($end);
        $tenureMonths = $start->diffInMonths($end) % 12;

        $parts = [];
        if ($tenureYears > 0) {
            $parts[] = $tenureYears.' '.($tenureYears == 1 ? 'year' : 'years');
        }
        if ($tenureMonths > 0) {
            $parts[] = $tenureMonths.' '.($tenureMonths == 1 ? 'month' : 'months');
        }

        return ! empty($parts) ? implode(' and ', $parts) : 'less than a month';
    }
}
