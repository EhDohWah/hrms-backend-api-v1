<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Exceptions\Leave\AttachmentRequiredException;
use App\Exceptions\Leave\DuplicateLeaveTypeException;
use App\Exceptions\Leave\InsufficientLeaveBalanceException;
use App\Exceptions\Leave\LeaveOverlapException;
use App\Exceptions\Leave\NegativeLeaveBalanceException;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestItem;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveRequestService
{
    public function __construct(
        protected LeaveCalculationService $calculationService
    ) {}

    /**
     * Build and execute paginated list query with filters and sorting.
     */
    public function list(array $validated): LengthAwarePaginator
    {
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        $sortBy = $validated['sort_by'] ?? 'recently_added';

        $query = LeaveRequest::query()->withRelations();

        // Apply search filter
        if (! empty($validated['search'])) {
            $searchTerm = trim($validated['search']);
            $query->whereHas('employee', function ($q) use ($searchTerm) {
                $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // Apply date range filter
        if (! empty($validated['from'])) {
            $query->where('start_date', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('end_date', '<=', $validated['to']);
        }

        // Apply leave types filter
        if (! empty($validated['leave_types'])) {
            $leaveTypeIds = explode(',', $validated['leave_types']);
            $query->whereHas('items', function ($q) use ($leaveTypeIds) {
                $q->whereIn('leave_type_id', $leaveTypeIds);
            });
        }

        // Apply status filter
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Apply approval filters
        if (isset($validated['supervisor_approved'])) {
            $query->where('supervisor_approved', $validated['supervisor_approved']);
        }
        if (isset($validated['hr_site_admin_approved'])) {
            $query->where('hr_site_admin_approved', $validated['hr_site_admin_approved']);
        }

        // Apply sorting
        match ($sortBy) {
            'ascending' => $query->orderBy('start_date', 'asc'),
            'descending' => $query->orderBy('start_date', 'desc'),
            'last_month' => $query->where('created_at', '>=', Carbon::now()->subMonth())
                ->orderBy('created_at', 'desc'),
            'last_7_days' => $query->where('created_at', '>=', Carbon::now()->subDays(7))
                ->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'), // recently_added
        };

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get statistics for the index view.
     */
    public function getStatistics(): array
    {
        return LeaveRequest::getStatistics();
    }

    /**
     * Load detailed relations on an existing leave request for display.
     */
    public function show(LeaveRequest $leaveRequest): LeaveRequest
    {
        $leaveRequest->loadMissing([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employee.employment:id,employee_id,department_id,position_id',
            'employee.employment.department:id,name',
            'employee.employment.position:id,title',
            'items.leaveType:id,name,default_duration,description,requires_attachment',
        ]);

        return $leaveRequest;
    }

    /**
     * Create a new leave request with items, overlap checking, and balance management.
     *
     * @throws DuplicateLeaveTypeException
     * @throws LeaveOverlapException
     * @throws AttachmentRequiredException
     * @throws InsufficientLeaveBalanceException
     */
    public function create(array $validated): LeaveRequest
    {
        // Validate no duplicate leave types in items
        $leaveTypeIds = array_column($validated['items'], 'leave_type_id');
        if (count($leaveTypeIds) !== count(array_unique($leaveTypeIds))) {
            throw new DuplicateLeaveTypeException;
        }

        // Check for overlapping leave requests
        $this->guardAgainstOverlap(
            $validated['employee_id'],
            $validated['start_date'],
            $validated['end_date']
        );

        // Check if any leave type requires attachment
        $leaveTypes = LeaveType::whereIn('id', $leaveTypeIds)->get()->keyBy('id');
        $requiresAttachment = $leaveTypes->contains('requires_attachment', true);

        if ($requiresAttachment && empty($validated['attachment_notes'])) {
            $requiringTypes = $leaveTypes->filter(fn ($type) => $type->requires_attachment)->pluck('name')->join(', ');
            throw new AttachmentRequiredException($requiringTypes);
        }

        $status = LeaveRequestStatus::from($validated['status'] ?? 'pending');

        // Only check balance if status is approved
        if ($status === LeaveRequestStatus::Approved) {
            $this->guardBalanceForItems($validated['employee_id'], $validated['items'], $leaveTypes);
        }

        // Calculate total days from items
        $totalDays = array_sum(array_column($validated['items'], 'days'));

        return DB::transaction(function () use ($validated, $status, $totalDays) {
            // Create leave request
            $leaveRequest = LeaveRequest::create([
                'employee_id' => $validated['employee_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $totalDays,
                'reason' => $validated['reason'] ?? null,
                'status' => $status,
                'supervisor_approved' => $validated['supervisor_approved'] ?? false,
                'supervisor_approved_date' => $validated['supervisor_approved_date'] ?? null,
                'hr_site_admin_approved' => $validated['hr_site_admin_approved'] ?? false,
                'hr_site_admin_approved_date' => $validated['hr_site_admin_approved_date'] ?? null,
                'attachment_notes' => $validated['attachment_notes'] ?? null,
                'created_by' => Auth::user()->name ?? 'System',
            ]);

            // Create leave request items
            foreach ($validated['items'] as $item) {
                LeaveRequestItem::create([
                    'leave_request_id' => $leaveRequest->id,
                    'leave_type_id' => $item['leave_type_id'],
                    'days' => $item['days'],
                ]);
            }

            // Deduct balances if approved
            if ($status === LeaveRequestStatus::Approved) {
                foreach ($validated['items'] as $item) {
                    $this->deductLeaveBalance($validated['employee_id'], $item['leave_type_id'], $item['days']);
                }
            }

            $this->clearStatisticsCache();

            // Load relationships for response
            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'items.leaveType:id,name',
            ]);

            return $leaveRequest;
        });
    }

    /**
     * Update a leave request with items, overlap checking, and balance management.
     *
     * @throws DuplicateLeaveTypeException
     * @throws LeaveOverlapException
     * @throws InsufficientLeaveBalanceException
     */
    public function update(LeaveRequest $leaveRequest, array $validated): LeaveRequest
    {
        $leaveRequest->loadMissing('items');
        $oldStatus = $leaveRequest->status;
        $newStatus = isset($validated['status'])
            ? LeaveRequestStatus::from($validated['status'])
            : $oldStatus;

        // Check for overlapping leave requests if dates are being changed
        $datesChanged = (isset($validated['start_date']) && $validated['start_date'] !== $leaveRequest->start_date->format('Y-m-d'))
            || (isset($validated['end_date']) && $validated['end_date'] !== $leaveRequest->end_date->format('Y-m-d'));

        if ($datesChanged) {
            $checkStartDate = $validated['start_date'] ?? $leaveRequest->start_date->format('Y-m-d');
            $checkEndDate = $validated['end_date'] ?? $leaveRequest->end_date->format('Y-m-d');

            $this->guardAgainstOverlap(
                $leaveRequest->employee_id,
                $checkStartDate,
                $checkEndDate,
                $leaveRequest->id
            );
        }

        return DB::transaction(function () use ($leaveRequest, $validated, $oldStatus, $newStatus) {
            // If items are provided, handle item updates
            if (isset($validated['items'])) {
                // Validate no duplicate leave types
                $leaveTypeIds = array_column($validated['items'], 'leave_type_id');
                if (count($leaveTypeIds) !== count(array_unique($leaveTypeIds))) {
                    throw new DuplicateLeaveTypeException;
                }

                // If currently approved, restore old balances first
                if ($oldStatus === LeaveRequestStatus::Approved) {
                    foreach ($leaveRequest->items as $item) {
                        $this->restoreLeaveBalanceForItem($leaveRequest->employee_id, $item->leave_type_id, $item->days);
                    }
                }

                // Delete old items and create new ones
                $leaveRequest->items()->delete();

                foreach ($validated['items'] as $item) {
                    LeaveRequestItem::create([
                        'leave_request_id' => $leaveRequest->id,
                        'leave_type_id' => $item['leave_type_id'],
                        'days' => $item['days'],
                    ]);
                }

                // Recalculate total days
                $leaveRequest->total_days = array_sum(array_column($validated['items'], 'days'));
            }

            // Check balance if status is changing to approved
            if ($newStatus === LeaveRequestStatus::Approved) {
                $items = isset($validated['items'])
                    ? $validated['items']
                    : $leaveRequest->items->map(fn ($item) => [
                        'leave_type_id' => $item->leave_type_id,
                        'days' => $item->days,
                    ])->toArray();

                $leaveTypes = LeaveType::whereIn('id', array_column($items, 'leave_type_id'))->get()->keyBy('id');
                $this->guardBalanceForItems($leaveRequest->employee_id, $items, $leaveTypes, $leaveRequest->id);
            }

            // Handle status change for balance updates
            if ($oldStatus !== $newStatus) {
                if ($oldStatus === LeaveRequestStatus::Approved && in_array($newStatus, [LeaveRequestStatus::Declined, LeaveRequestStatus::Cancelled, LeaveRequestStatus::Pending])) {
                    // Restore balances (if not already done via items update)
                    if (! isset($validated['items'])) {
                        foreach ($leaveRequest->items as $item) {
                            $this->restoreLeaveBalanceForItem($leaveRequest->employee_id, $item->leave_type_id, $item->days);
                        }
                    }
                } elseif ($oldStatus !== LeaveRequestStatus::Approved && $newStatus === LeaveRequestStatus::Approved) {
                    // Deduct balances
                    foreach ($leaveRequest->fresh()->items as $item) {
                        $this->deductLeaveBalance($leaveRequest->employee_id, $item->leave_type_id, $item->days);
                    }
                }
            }

            // Update the leave request with all validated data (except items)
            $updateData = collect($validated)->except(['items'])->toArray();
            $leaveRequest->update(array_merge($updateData, [
                'updated_by' => Auth::user()->name ?? 'System',
            ]));

            $this->clearStatisticsCache();

            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'items.leaveType:id,name',
            ]);

            return $leaveRequest;
        });
    }

    /**
     * Delete a leave request, restoring balance if it was approved.
     */
    public function delete(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing('items');

        DB::transaction(function () use ($leaveRequest) {
            // Restore balance if the request was approved
            if ($leaveRequest->status === LeaveRequestStatus::Approved) {
                foreach ($leaveRequest->items as $item) {
                    $this->restoreLeaveBalanceForItem($leaveRequest->employee_id, $item->leave_type_id, $item->days);
                }
            }

            // Delete will cascade to items automatically
            $leaveRequest->delete();
        });

        $this->clearStatisticsCache();
    }

    /**
     * Calculate working days for a date range (preview before submission).
     */
    public function calculateDays(array $validated): array
    {
        $detailed = $validated['detailed'] ?? false;

        if ($detailed) {
            return $this->calculationService->calculateWorkingDaysDetailed(
                $validated['start_date'],
                $validated['end_date']
            );
        }

        $workingDays = $this->calculationService->calculateWorkingDays(
            $validated['start_date'],
            $validated['end_date']
        );

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $start->diffInDays($end) + 1;

        return [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'working_days' => $workingDays,
            'total_calendar_days' => $totalDays,
        ];
    }

    /**
     * Check for overlapping leave requests (preview endpoint).
     */
    public function checkOverlap(array $validated): array
    {
        return $this->findOverlaps(
            $validated['employee_id'],
            $validated['start_date'],
            $validated['end_date'],
            $validated['exclude_request_id'] ?? null
        );
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Guard: throw LeaveOverlapException if overlapping requests exist.
     *
     * @throws LeaveOverlapException
     */
    private function guardAgainstOverlap(int $employeeId, string $startDate, string $endDate, ?int $excludeRequestId = null): void
    {
        $overlapCheck = $this->findOverlaps($employeeId, $startDate, $endDate, $excludeRequestId);

        if ($overlapCheck['has_overlap']) {
            throw new LeaveOverlapException($overlapCheck['message'], $overlapCheck['conflicts']);
        }
    }

    /**
     * Find overlapping leave requests for the same employee.
     *
     * @return array{has_overlap: bool, conflicts: array, message: string}
     */
    private function findOverlaps(int $employeeId, string $startDate, string $endDate, ?int $excludeRequestId = null): array
    {
        $query = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })
            ->with(['items.leaveType:id,name']);

        if ($excludeRequestId) {
            $query->where('id', '!=', $excludeRequestId);
        }

        $overlappingRequests = $query->get();

        if ($overlappingRequests->isEmpty()) {
            return [
                'has_overlap' => false,
                'conflicts' => [],
                'message' => 'No overlapping leave requests found',
            ];
        }

        $conflicts = $overlappingRequests->map(function ($request) {
            $leaveTypes = $request->items->map(fn ($item) => $item->leaveType->name ?? 'Unknown')->join(', ');

            return [
                'id' => $request->id,
                'start_date' => $request->start_date->format('Y-m-d'),
                'end_date' => $request->end_date->format('Y-m-d'),
                'total_days' => $request->total_days,
                'leave_types' => $leaveTypes,
                'status' => $request->status->value,
                'reason' => $request->reason,
            ];
        })->toArray();

        $conflictDescriptions = array_map(function ($conflict) {
            return sprintf(
                '%s (%s to %s, %s days, Status: %s)',
                $conflict['leave_types'],
                $conflict['start_date'],
                $conflict['end_date'],
                $conflict['total_days'],
                ucfirst($conflict['status'])
            );
        }, $conflicts);

        return [
            'has_overlap' => true,
            'conflicts' => $conflicts,
            'message' => 'Your leave request overlaps with existing request(s): '.implode('; ', $conflictDescriptions),
        ];
    }

    /**
     * Guard: check balance for all items, throw if any is insufficient.
     *
     * @throws InsufficientLeaveBalanceException
     */
    private function guardBalanceForItems(int $employeeId, array $items, $leaveTypes, ?int $excludeRequestId = null): void
    {
        foreach ($items as $item) {
            $balanceCheck = $this->checkLeaveBalance($employeeId, $item['leave_type_id'], $item['days'], $excludeRequestId);

            if (! $balanceCheck['valid']) {
                $leaveTypeName = $leaveTypes[$item['leave_type_id']]->name ?? 'Unknown';

                throw new InsufficientLeaveBalanceException(
                    "Insufficient balance for {$leaveTypeName}: {$balanceCheck['message']}",
                    array_merge($balanceCheck, ['leave_type' => $leaveTypeName])
                );
            }
        }
    }

    /**
     * Check if employee has sufficient leave balance for the requested days.
     */
    private function checkLeaveBalance(int $employeeId, int $leaveTypeId, float $requestedDays, ?int $excludeRequestId = null): array
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $currentYear)
            ->first();

        if (! $leaveBalance) {
            return [
                'valid' => false,
                'message' => 'No leave balance found for this employee and leave type for the current year',
                'available_days' => 0,
                'requested_days' => $requestedDays,
            ];
        }

        // Calculate current used days excluding the request being updated (if any)
        $currentUsedDays = $leaveBalance->used_days;
        if ($excludeRequestId) {
            $excludedRequest = LeaveRequest::where('id', $excludeRequestId)
                ->where('status', LeaveRequestStatus::Approved)
                ->first();
            if ($excludedRequest) {
                $currentUsedDays -= $excludedRequest->total_days;
            }
        }

        $availableDays = $leaveBalance->total_days - $currentUsedDays;

        if ($availableDays < $requestedDays) {
            return [
                'valid' => false,
                'message' => 'Insufficient leave balance. You cannot request more days than available.',
                'available_days' => $availableDays,
                'requested_days' => $requestedDays,
                'shortfall' => $requestedDays - $availableDays,
            ];
        }

        return [
            'valid' => true,
            'message' => 'Sufficient leave balance available',
            'available_days' => $availableDays,
            'requested_days' => $requestedDays,
            'remaining_after_request' => $availableDays - $requestedDays,
        ];
    }

    /**
     * Deduct leave balance for a specific leave type.
     *
     * @throws NegativeLeaveBalanceException
     */
    private function deductLeaveBalance(int $employeeId, int $leaveTypeId, float $days): void
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $currentYear)
            ->first();

        if ($leaveBalance) {
            $newUsedDays = $leaveBalance->used_days + $days;
            $newRemainingDays = $leaveBalance->total_days - $newUsedDays;

            if ($newRemainingDays < 0) {
                Log::warning('Attempted to create negative balance for leave type', [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'days' => $days,
                ]);
                throw new NegativeLeaveBalanceException;
            }

            $leaveBalance->used_days = $newUsedDays;
            $leaveBalance->remaining_days = $newRemainingDays;
            $leaveBalance->save();
        }
    }

    /**
     * Restore leave balance for a specific leave type.
     */
    private function restoreLeaveBalanceForItem(int $employeeId, int $leaveTypeId, float $days): void
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $currentYear)
            ->first();

        if ($leaveBalance) {
            $leaveBalance->used_days = max(0, $leaveBalance->used_days - $days);
            $leaveBalance->remaining_days = $leaveBalance->total_days - $leaveBalance->used_days;
            $leaveBalance->save();
        }
    }

    /**
     * Clear leave request statistics cache.
     */
    private function clearStatisticsCache(): void
    {
        Cache::forget('leave_request_statistics');
    }
}
