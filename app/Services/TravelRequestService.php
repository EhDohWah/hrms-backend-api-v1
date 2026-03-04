<?php

namespace App\Services;

use App\Enums\AccommodationType;
use App\Enums\TransportationType;
use App\Exceptions\Employment\EmployeeNotFoundException;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class TravelRequestService
{
    /**
     * Build and execute paginated list query with filters and sorting.
     */
    public function list(array $validated): LengthAwarePaginator
    {
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $query = TravelRequest::query()->withRelations();

        // Apply search (employee staff ID, first name, last name, or full name)
        if (! empty($validated['search'])) {
            $searchTerm = trim($validated['search']);
            $query->whereHas('employee', function ($q) use ($searchTerm) {
                $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // Apply department filter (comma-separated names)
        if (! empty($validated['filter_department'])) {
            $departments = array_map('trim', explode(',', $validated['filter_department']));
            $query->whereHas('department', function ($q) use ($departments) {
                $q->whereIn('name', $departments);
            });
        }

        // Apply destination filter
        if (! empty($validated['filter_destination'])) {
            $query->where('destination', 'LIKE', "%{$validated['filter_destination']}%");
        }

        // Apply transportation filter
        if (! empty($validated['filter_transportation'])) {
            $query->where('transportation', $validated['filter_transportation']);
        }

        // Apply sorting
        match ($sortBy) {
            'employee_name' => $query->whereHas('employee', function ($q) use ($sortOrder) {
                $q->orderByRaw("CONCAT(first_name_en, ' ', last_name_en) {$sortOrder}");
            }),
            'department' => $query->whereHas('department', function ($q) use ($sortOrder) {
                $q->orderBy('name', $sortOrder);
            }),
            default => $query->orderBy($sortBy, $sortOrder),
        };

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Build the applied filters array from validated parameters.
     */
    public function buildAppliedFilters(array $validated): array
    {
        $appliedFilters = [];

        if (! empty($validated['search'])) {
            $appliedFilters['search'] = $validated['search'];
        }
        if (! empty($validated['filter_department'])) {
            $appliedFilters['department'] = explode(',', $validated['filter_department']);
        }
        if (! empty($validated['filter_destination'])) {
            $appliedFilters['destination'] = $validated['filter_destination'];
        }
        if (! empty($validated['filter_transportation'])) {
            $appliedFilters['transportation'] = $validated['filter_transportation'];
        }

        return $appliedFilters;
    }

    /**
     * Create a new travel request.
     */
    public function create(array $validated): TravelRequest
    {
        if (! isset($validated['created_by'])) {
            $validated['created_by'] = Auth::user()->name ?? 'System';
        }

        $travelRequest = TravelRequest::create($validated);

        $travelRequest->loadMissing([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title,department_id',
        ]);

        return $travelRequest;
    }

    /**
     * Load relations on an existing travel request for display.
     */
    public function show(TravelRequest $travelRequest): TravelRequest
    {
        $travelRequest->loadMissing([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title,department_id',
        ]);

        return $travelRequest;
    }

    /**
     * Update an existing travel request.
     */
    public function update(TravelRequest $travelRequest, array $validated): TravelRequest
    {
        $validated['updated_by'] = Auth::user()->name ?? 'System';

        $travelRequest->update($validated);

        $travelRequest->load([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title,department_id',
        ]);

        return $travelRequest;
    }

    /**
     * Delete a travel request.
     */
    public function delete(TravelRequest $travelRequest): void
    {
        $travelRequest->delete();
    }

    /**
     * Get available options for transportation and accommodation from enums.
     */
    public function getOptions(): array
    {
        return [
            'transportation' => collect(TransportationType::cases())->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->all(),
            'accommodation' => collect(AccommodationType::cases())->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->all(),
        ];
    }

    /**
     * Search travel requests by employee staff ID with pagination.
     *
     *
     * @return array{employee: Employee, paginator: LengthAwarePaginator}
     *
     * @throws EmployeeNotFoundException
     */
    public function searchByStaffId(string $staffId, array $validated): array
    {
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;

        $employee = Employee::query()
            ->where('staff_id', $staffId)
            ->select('id', 'staff_id', 'first_name_en', 'last_name_en')
            ->first();

        if (! $employee) {
            throw new EmployeeNotFoundException($staffId);
        }

        $paginator = TravelRequest::query()
            ->withRelations()
            ->where('employee_id', $employee->id)
            ->orderBy('start_date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'employee' => $employee,
            'paginator' => $paginator,
        ];
    }
}
