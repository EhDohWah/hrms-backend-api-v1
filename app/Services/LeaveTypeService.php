<?php

namespace App\Services;

use App\Exceptions\DeletionBlockedException;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequestItem;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveTypeService
{
    /**
     * List leave types with search and pagination.
     */
    public function list(array $params): LengthAwarePaginator
    {
        $query = LeaveType::query();

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        return $query->orderBy($params['sort_by'], $params['sort_order'])
            ->paginate($params['per_page']);
    }

    /**
     * Get all leave types for dropdown selection (non-paginated).
     */
    public function options(): Collection
    {
        return LeaveType::orderBy('name', 'asc')->get();
    }

    /**
     * Create a new leave type and auto-apply to all employees.
     *
     * @return array{leave_type: LeaveType, balances_created: int}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::user()?->name ?? 'System';

            $leaveType = LeaveType::create($data);

            $employees = Employee::all();
            $currentYear = Carbon::now()->year;
            $balancesCreated = 0;
            $totalDays = $data['default_duration'] ?? 0;

            foreach ($employees as $employee) {
                $existingBalance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $currentYear)
                    ->exists();

                if (! $existingBalance) {
                    LeaveBalance::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'total_days' => $totalDays,
                        'used_days' => 0,
                        'remaining_days' => $totalDays,
                        'year' => $currentYear,
                        'created_by' => Auth::user()?->name ?? 'System',
                    ]);
                    $balancesCreated++;
                }
            }

            return [
                'leave_type' => $leaveType,
                'balances_created' => $balancesCreated,
            ];
        });
    }

    /**
     * Update a leave type.
     */
    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        $data['updated_by'] = Auth::user()?->name ?? 'System';

        $leaveType->update($data);

        return $leaveType;
    }

    /**
     * Delete a leave type if not in use.
     *
     * @throws DeletionBlockedException
     */
    public function delete(LeaveType $leaveType): void
    {
        $blockers = [];

        if (LeaveRequestItem::where('leave_type_id', $leaveType->id)->exists()) {
            $blockers[] = 'Leave type has associated leave requests';
        }

        if (LeaveBalance::where('leave_type_id', $leaveType->id)->exists()) {
            $blockers[] = 'Leave type has associated leave balances';
        }

        if (! empty($blockers)) {
            throw new DeletionBlockedException($blockers, 'Cannot delete leave type');
        }

        $leaveType->delete();
    }
}
