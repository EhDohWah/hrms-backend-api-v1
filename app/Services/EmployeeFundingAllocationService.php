<?php

namespace App\Services;

use App\Enums\FundingAllocationStatus;
use App\Exceptions\FundingAllocation\FundingAllocationException;
use App\Exceptions\FundingAllocation\MissingSalaryException;
use App\Exports\EmployeeFundingAllocationTemplateExport;
use App\Exports\GrantItemsReferenceExport;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeFundingAllocationService
{
    /**
     * List employee funding allocations with optional filters.
     */
    public function list(array $filters): array
    {
        $query = EmployeeFundingAllocation::with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employment:id,start_date,end_probation_date,department_id,position_id',
            'employment.department:id,name',
            'employment.position:id,title',
            'grantItem.grant:id,name,code',
        ]);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['active'])) {
            if (filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN)) {
                $query->where('status', FundingAllocationStatus::Active);
            } else {
                $query->whereIn('status', [FundingAllocationStatus::Inactive, FundingAllocationStatus::Closed]);
            }
        }

        return [
            'paginator' => $query->orderByDesc('id')->paginate(20),
        ];
    }

    /**
     * Get allocations by grant item ID with active count.
     */
    public function byGrantItem(int $grantItemId): array
    {
        $allocations = EmployeeFundingAllocation::with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employment:id,start_date,end_probation_date',
            'grantItem.grant:id,name,code',
        ])
            ->where('grant_item_id', $grantItemId)
            ->orderByDesc('id')
            ->get();

        $activeCount = $allocations->filter(function ($allocation) {
            return $allocation->status === FundingAllocationStatus::Active;
        })->count();

        return [
            'allocations' => $allocations,
            'total_allocations' => $allocations->count(),
            'active_allocations' => $activeCount,
        ];
    }

    /**
     * Create funding allocations with smart FTE validation and capacity checking.
     */
    public function store(array $validated, User $performedBy): array
    {
        $currentUser = $performedBy->name;

        $employment = Employment::findOrFail($validated['employment_id']);

        if (is_null($employment->pass_probation_salary) && is_null($employment->probation_salary)) {
            throw new MissingSalaryException;
        }

        $today = Carbon::today();
        $effectiveDate = $today;

        // Get currently active allocations for this employment
        $existingAllocations = EmployeeFundingAllocation::where('employee_id', $validated['employee_id'])
            ->where('employment_id', $validated['employment_id'])
            ->where('status', FundingAllocationStatus::Active)
            ->get();

        // Validate replace_allocation_ids belong to this employment
        $replaceIds = $validated['replace_allocation_ids'] ?? [];
        if (! empty($replaceIds)) {
            $invalidIds = collect($replaceIds)->diff($existingAllocations->pluck('id'));
            if ($invalidIds->isNotEmpty()) {
                throw new FundingAllocationException(
                    'Some replace_allocation_ids do not belong to this employment or are not active',
                    ['invalid_ids' => $invalidIds->values()]
                );
            }
        }

        // Smart FTE validation: projected_total = existing_to_keep + new_allocations
        $allocationsToKeep = $existingAllocations->whereNotIn('id', $replaceIds);
        $existingFteToKeep = $allocationsToKeep->sum('fte') * 100;
        $newFte = array_sum(array_column($validated['allocations'], 'fte'));
        $projectedTotal = $existingFteToKeep + $newFte;

        $tolerance = 0.01;
        if (abs($projectedTotal - 100) > $tolerance) {
            throw new FundingAllocationException(
                'Total FTE must equal 100% after this operation',
                [
                    'breakdown' => [
                        'existing_allocations_count' => $existingAllocations->count(),
                        'allocations_being_replaced' => count($replaceIds),
                        'existing_fte_to_keep' => round($existingFteToKeep, 2),
                        'new_fte_being_added' => round($newFte, 2),
                        'projected_total' => round($projectedTotal, 2),
                        'required_total' => 100.00,
                        'difference' => round($projectedTotal - 100, 2),
                    ],
                ]
            );
        }

        // ── Phase 1: Validate ALL allocations before creating any ──
        // This prevents partial creates where some succeed and others fail,
        // which would leave FTE totals in an inconsistent state.
        $errors = [];
        $validatedItems = [];

        foreach ($validated['allocations'] as $index => $allocationData) {
            if (empty($allocationData['grant_item_id'])) {
                $errors[] = "Allocation #".($index + 1).": grant_item_id is required";

                continue;
            }

            $grantItem = GrantItem::find($allocationData['grant_item_id']);
            if (! $grantItem) {
                $errors[] = "Allocation #".($index + 1).": Grant item not found";

                continue;
            }

            if ($grantItem->grant_position_number > 0) {
                $currentAllocations = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
                    ->where('status', FundingAllocationStatus::Active)
                    ->count();

                if ($currentAllocations >= $grantItem->grant_position_number) {
                    $errors[] = "Allocation #".($index + 1).": Grant position '{$grantItem->grant_position}' has reached its maximum capacity ({$currentAllocations}/{$grantItem->grant_position_number})";

                    continue;
                }
            }

            // Check for duplicate allocation
            $isDuplicate = EmployeeFundingAllocation::where([
                'employee_id' => $validated['employee_id'],
                'employment_id' => $validated['employment_id'],
            ])
                ->where('status', FundingAllocationStatus::Active)
                ->where('grant_item_id', $allocationData['grant_item_id'])
                ->exists();

            if ($isDuplicate) {
                $errors[] = "Allocation #".($index + 1).": Already exists for grant position '{$grantItem->grant_position}'";

                continue;
            }

            $validatedItems[] = [
                'data' => $allocationData,
                'grantItem' => $grantItem,
            ];
        }

        // If any allocation failed validation, reject the entire batch
        if (! empty($errors)) {
            throw new FundingAllocationException(
                'Cannot create allocations. Please fix the following issues:',
                ['errors' => $errors]
            );
        }

        // ── Phase 2: All validations passed — create everything ──
        DB::beginTransaction();

        // Mark replaced allocations as 'closed'
        if (! empty($replaceIds)) {
            EmployeeFundingAllocation::whereIn('id', $replaceIds)
                ->update([
                    'status' => FundingAllocationStatus::Closed,
                    'updated_by' => $currentUser,
                ]);
        }

        $createdAllocations = [];

        foreach ($validatedItems as $item) {
            $fteDecimal = $item['data']['fte'] / 100;
            $salaryContext = $this->deriveSalaryContext($employment, $fteDecimal, $effectiveDate);

            $allocation = EmployeeFundingAllocation::create(array_merge([
                'employee_id' => $validated['employee_id'],
                'employment_id' => $validated['employment_id'],
                'grant_item_id' => $item['data']['grant_item_id'],
                'fte' => $fteDecimal,
                'status' => FundingAllocationStatus::Active,
                'created_by' => $currentUser,
                'updated_by' => $currentUser,
            ], $salaryContext));

            $createdAllocations[] = $allocation;
        }

        DB::commit();

        // Load with relationships
        $allocationsWithRelations = EmployeeFundingAllocation::with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employment:id,start_date',
            'grantItem.grant:id,name,code',
        ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

        return [
            'allocations' => $allocationsWithRelations,
            'total_created' => count($createdAllocations),
            'salary_info' => [
                'salary_type_used' => $employment->getSalaryTypeForDate($effectiveDate),
                'salary_amount_used' => $employment->getSalaryAmountForDate($effectiveDate),
                'is_probation_period' => $employment->pass_probation_date
                    ? $effectiveDate->lt(Carbon::parse($employment->pass_probation_date))
                    : false,
                'pass_probation_date' => $employment->pass_probation_date,
            ],
            'warnings' => null,
        ];
    }

    /**
     * Calculate allocation amount preview (no persistence).
     */
    public function calculatePreview(array $validated): array
    {
        $employment = Employment::findOrFail($validated['employment_id']);

        if (is_null($employment->pass_probation_salary) && is_null($employment->probation_salary)) {
            throw new MissingSalaryException('Employment must have a salary defined before allocation can be calculated.');
        }

        $effectiveDate = isset($validated['effective_date'])
            ? Carbon::parse($validated['effective_date'])
            : Carbon::today();

        $fteDecimal = $validated['fte'] / 100;
        $salaryContext = $this->deriveSalaryContext($employment, $fteDecimal, $effectiveDate);

        $isProbationPeriod = $employment->pass_probation_date
            ? $effectiveDate->lt(Carbon::parse($employment->pass_probation_date))
            : false;

        return [
            'fte_decimal' => $fteDecimal,
            'fte_percentage' => $validated['fte'],
            'allocated_amount' => $salaryContext['allocated_amount'],
            'salary_type' => $salaryContext['salary_type'],
            'salary_amount' => $employment->getSalaryAmountForDate($effectiveDate),
            'is_probation_period' => $isProbationPeriod,
            'pass_probation_date' => $employment->pass_probation_date,
            'effective_date' => $effectiveDate->toDateString(),
        ];
    }

    /**
     * Update a single funding allocation.
     */
    public function update(EmployeeFundingAllocation $allocation, array $validated, User $performedBy): EmployeeFundingAllocation
    {
        $currentUser = $performedBy->name;

        DB::beginTransaction();

        if (empty($validated['grant_item_id'])) {
            DB::rollBack();
            throw new FundingAllocationException('grant_item_id is required for grant allocations');
        }

        $grantItem = GrantItem::find($validated['grant_item_id']);
        if (! $grantItem) {
            DB::rollBack();
            throw new FundingAllocationException('Grant item not found');
        }

        if ($grantItem->grant_position_number > 0) {
            $currentAllocations = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
                ->where('id', '!=', $allocation->id)
                ->where('status', FundingAllocationStatus::Active)
                ->count();

            if ($currentAllocations >= $grantItem->grant_position_number) {
                DB::rollBack();
                throw new FundingAllocationException(
                    "Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}"
                );
            }
        }

        // Recalculate if FTE changed
        if (isset($validated['fte'])) {
            $newFteDecimal = $validated['fte'] / 100;

            $otherAllocations = EmployeeFundingAllocation::where('employment_id', $allocation->employment_id)
                ->where('id', '!=', $allocation->id)
                ->where('status', FundingAllocationStatus::Active)
                ->sum('fte');

            $projectedTotal = ($otherAllocations + $newFteDecimal) * 100;
            $tolerance = 0.01;

            if (abs($projectedTotal - 100) > $tolerance) {
                DB::rollBack();
                throw new FundingAllocationException(
                    'Total FTE must equal 100% after this update',
                    [
                        'breakdown' => [
                            'other_allocations_fte' => round($otherAllocations * 100, 2),
                            'new_fte_for_this_allocation' => round($newFteDecimal * 100, 2),
                            'projected_total' => round($projectedTotal, 2),
                            'required_total' => 100.00,
                        ],
                    ]
                );
            }

            $validated['fte'] = $newFteDecimal;

            $employment = $allocation->employment ?? Employment::find($allocation->employment_id);
            if ($employment) {
                $today = Carbon::today();
                $validated['allocated_amount'] = round($employment->getSalaryAmountForDate($today) * $newFteDecimal, 2);
                $validated['salary_type'] = $employment->getSalaryTypeForDate($today);
            }
        }

        $validated['updated_by'] = $currentUser;
        $allocation->update($validated);

        DB::commit();

        return $allocation->fresh([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employment:id,start_date',
            'grantItem.grant:id,name,code',
        ]);
    }

    /**
     * Delete a funding allocation.
     */
    public function destroy(EmployeeFundingAllocation $allocation, ?User $performedBy = null): void
    {
        $allocation->update([
            'status' => FundingAllocationStatus::Inactive,
            'updated_by' => $performedBy?->name ?? 'system',
        ]);
    }

    /**
     * Batch update: atomically process updates, creates, and deletes.
     */
    public function batchUpdate(array $validated, User $performedBy): array
    {
        $employeeId = $validated['employee_id'];
        $employmentId = $validated['employment_id'];
        $updates = $validated['updates'] ?? [];
        $creates = $validated['creates'] ?? [];
        $deletes = $validated['deletes'] ?? [];

        // Get current allocations (active + inactive for re-activation)
        $currentAllocations = EmployeeFundingAllocation::where('employee_id', $employeeId)
            ->where('employment_id', $employmentId)
            ->whereIn('status', [FundingAllocationStatus::Active, FundingAllocationStatus::Inactive])
            ->get()
            ->keyBy('id');

        $updateIds = collect($updates)->pluck('id')->toArray();
        $currentAllocationIds = $currentAllocations->keys()->toArray();

        // Validate IDs belong to this employee/employment
        $invalidUpdateIds = array_diff($updateIds, $currentAllocationIds);
        if (! empty($invalidUpdateIds)) {
            throw new FundingAllocationException(
                'Some allocation IDs to update do not belong to this employee/employment',
                ['invalid_ids' => array_values($invalidUpdateIds)]
            );
        }

        $invalidDeleteIds = array_diff($deletes, $currentAllocationIds);
        if (! empty($invalidDeleteIds)) {
            throw new FundingAllocationException(
                'Some allocation IDs to delete do not belong to this employee/employment',
                ['invalid_ids' => array_values($invalidDeleteIds)]
            );
        }

        // FTE validation: untouched + updates (active only) + creates = 100%
        $untouchedFte = 0;
        foreach ($currentAllocations as $allocation) {
            $isBeingUpdated = in_array($allocation->id, $updateIds);
            $isBeingDeleted = in_array($allocation->id, $deletes);
            if (! $isBeingUpdated && ! $isBeingDeleted && $allocation->status === FundingAllocationStatus::Active) {
                $untouchedFte += (float) $allocation->fte * 100;
            }
        }

        $updatesFte = collect($updates)
            ->filter(function ($u) {
                $status = $u['status'] ?? FundingAllocationStatus::Active->value;

                return $status === FundingAllocationStatus::Active->value;
            })
            ->sum(function ($u) use ($currentAllocations) {
                if (isset($u['fte'])) {
                    return $u['fte'];
                }
                // Status-only toggle: use existing FTE value
                $existing = $currentAllocations->get($u['id']);

                return $existing ? (float) $existing->fte * 100 : 0;
            });
        $createsFte = collect($creates)->sum('fte');
        $projectedTotal = $untouchedFte + $updatesFte + $createsFte;

        $tolerance = 0.01;
        $hasActiveAllocations = $projectedTotal > $tolerance;

        if ($hasActiveAllocations && abs($projectedTotal - 100) > $tolerance) {
            throw new FundingAllocationException(
                'Total FTE must equal 100%',
                [
                    'breakdown' => [
                        'untouched_fte' => round($untouchedFte, 2),
                        'updates_fte' => round($updatesFte, 2),
                        'creates_fte' => round($createsFte, 2),
                        'projected_total' => round($projectedTotal, 2),
                        'required_total' => 100,
                    ],
                ]
            );
        }

        // Get employment for salary calculation
        $employment = Employment::findOrFail($employmentId);

        $today = Carbon::today();
        $salaryType = $employment->getSalaryTypeForDate($today);
        $baseSalary = $employment->getSalaryAmountForDate($today);
        $userName = $performedBy->name;

        DB::beginTransaction();

        $deletedCount = 0;
        $updatedCount = 0;
        $createdCount = 0;

        // Process deletes — mark as closed for audit trail
        foreach ($deletes as $deleteId) {
            $allocation = $currentAllocations->get($deleteId);
            if ($allocation) {
                $allocation->update([
                    'status' => FundingAllocationStatus::Closed,
                    'updated_by' => $userName,
                ]);
                $deletedCount++;
            }
        }

        // Process updates
        foreach ($updates as $updateData) {
            $allocation = $currentAllocations->get($updateData['id']);
            if ($allocation) {
                // Fall back to existing values when fields not provided (status-only toggle)
                $newFte = isset($updateData['fte'])
                    ? (float) $updateData['fte'] / 100
                    : (float) $allocation->fte;
                $newGrantItemId = $updateData['grant_item_id'] ?? $allocation->grant_item_id;
                $allocatedAmount = $baseSalary * $newFte;
                $newStatus = FundingAllocationStatus::from($updateData['status'] ?? FundingAllocationStatus::Active->value);

                $updateFields = [
                    'grant_item_id' => $newGrantItemId,
                    'fte' => $newFte,
                    'allocated_amount' => $allocatedAmount,
                    'salary_type' => $salaryType,
                    'status' => $newStatus,
                    'updated_by' => $userName,
                ];

                $allocation->update($updateFields);
                $updatedCount++;
            }
        }

        // Process creates
        foreach ($creates as $createData) {
            $fteDecimal = (float) $createData['fte'] / 100;
            $allocatedAmount = $baseSalary * $fteDecimal;

            EmployeeFundingAllocation::create([
                'employee_id' => $employeeId,
                'employment_id' => $employmentId,
                'grant_item_id' => $createData['grant_item_id'],
                'fte' => $fteDecimal,
                'allocated_amount' => $allocatedAmount,
                'salary_type' => $salaryType,
                'status' => FundingAllocationStatus::Active,
                'created_by' => $userName,
                'updated_by' => $userName,
            ]);
            $createdCount++;
        }

        DB::commit();

        $freshAllocations = EmployeeFundingAllocation::with(['grantItem.grant'])
            ->where('employee_id', $employeeId)
            ->where('employment_id', $employmentId)
            ->whereIn('status', [FundingAllocationStatus::Active, FundingAllocationStatus::Inactive])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();

        return [
            'allocations' => $freshAllocations,
            'summary' => [
                'deleted_count' => $deletedCount,
                'updated_count' => $updatedCount,
                'created_count' => $createdCount,
                'total_fte' => round(
                    $freshAllocations->where('status', FundingAllocationStatus::Active)->sum('fte') * 100,
                    2
                ),
            ],
        ];
    }

    /**
     * Get all active allocations for a specific employee.
     */
    public function employeeAllocations(int $employeeId): array
    {
        $employee = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en')
            ->findOrFail($employeeId);

        $allocations = EmployeeFundingAllocation::with([
            'employment:id,start_date,end_probation_date,department_id,position_id',
            'employment.department:id,name',
            'employment.position:id,title',
            'grantItem.grant:id,name,code',
        ])
            ->where('employee_id', $employeeId)
            ->where('status', FundingAllocationStatus::Active)
            ->orderBy('updated_at', 'desc')
            ->get();

        return [
            'employee' => $employee,
            'allocations' => $allocations,
            'total_allocations' => $allocations->count(),
            'total_effort' => $allocations->sum('fte') * 100,
        ];
    }

    /**
     * Get the grant structure for allocation forms.
     */
    public function grantStructure(): array
    {
        $grants = Grant::with(['grantItems'])
            ->select('id', 'name', 'code', 'organization')
            ->get();

        return [
            'grants' => $grants->map(function ($grant) {
                return [
                    'id' => $grant->id,
                    'name' => $grant->name,
                    'code' => $grant->code,
                    'organization' => $grant->organization,
                    'grant_items' => $grant->grantItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->grant_position,
                            'grant_salary' => $item->grant_salary,
                            'grant_benefit' => $item->grant_benefit,
                            'grant_fte' => $item->grant_fte,
                            'budgetline_code' => $item->budgetline_code,
                            'grant_position_number' => $item->grant_position_number,
                        ];
                    }),
                ];
            }),
        ];
    }

    /**
     * Bulk deactivate allocations by setting status to Closed.
     */
    public function bulkDeactivate(array $allocationIds, User $performedBy): int
    {
        $currentUser = $performedBy->name;

        return EmployeeFundingAllocation::whereIn('id', $allocationIds)
            ->where('status', FundingAllocationStatus::Active)
            ->update([
                'status' => FundingAllocationStatus::Closed,
                'updated_by' => $currentUser,
            ]);
    }

    /**
     * Replace all active allocations for an employee/employment with new ones.
     */
    public function updateEmployeeAllocations(int $employeeId, array $validated, User $performedBy): array
    {
        $currentUser = $performedBy->name;

        $employee = Employee::findOrFail($employeeId);

        // Validate total effort = 100%
        $totalEffort = array_sum(array_column($validated['allocations'], 'fte'));
        if ($totalEffort != 100) {
            throw new FundingAllocationException(
                'Total effort must equal 100%',
                ['current_total' => $totalEffort]
            );
        }

        DB::beginTransaction();

        // Close all existing active allocations
        EmployeeFundingAllocation::where('employee_id', $employeeId)
            ->where('employment_id', $validated['employment_id'])
            ->where('status', FundingAllocationStatus::Active)
            ->update([
                'status' => FundingAllocationStatus::Closed,
                'updated_by' => $currentUser,
                'updated_at' => now(),
            ]);

        // Create new allocations
        $createdAllocations = [];
        foreach ($validated['allocations'] as $allocationData) {
            $allocation = EmployeeFundingAllocation::create([
                'employee_id' => $employeeId,
                'employment_id' => $validated['employment_id'],
                'grant_item_id' => $allocationData['grant_item_id'],
                'fte' => $allocationData['fte'] / 100,
                'allocated_amount' => $allocationData['allocated_amount'] ?? null,
                'status' => FundingAllocationStatus::Active,
                'created_by' => $currentUser,
                'updated_by' => $currentUser,
            ]);
            $createdAllocations[] = $allocation;
        }

        DB::commit();

        // Load with relationships
        $allocationsWithRelations = EmployeeFundingAllocation::with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employment:id,start_date,end_probation_date,department_id,position_id',
            'employment.department:id,name',
            'employment.position:id,title',
            'grantItem.grant:id,name,code',
        ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

        return [
            'allocations' => $allocationsWithRelations,
            'total_created' => count($createdAllocations),
        ];
    }

    /**
     * Process funding allocation file upload.
     */
    public function upload(object $file, int $userId): array
    {
        $importId = 'funding_allocation_import_'.uniqid();

        $import = new \App\Imports\EmployeeFundingAllocationsImport($importId, $userId);
        $import->queue($file);

        return [
            'import_id' => $importId,
            'status' => 'processing',
        ];
    }

    /**
     * Download grant items reference file.
     */
    public function downloadGrantItemsReference(): BinaryFileResponse
    {
        $export = new GrantItemsReferenceExport;
        $tempFile = $export->generate();
        $filename = $export->getFilename();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Download funding allocation import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $export = new EmployeeFundingAllocationTemplateExport;
        $tempFile = $export->generate();
        $filename = $export->getFilename();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Determine salary metadata for an allocation context.
     */
    public function deriveSalaryContext(
        Employment $employment,
        float $fte,
        ?Carbon $effectiveDate = null
    ): array {
        if (is_null($employment->pass_probation_salary) && is_null($employment->probation_salary)) {
            throw new InvalidArgumentException('Employment must define a salary before allocations can be created.');
        }

        $effectiveDate ??= $employment->start_date instanceof Carbon
            ? $employment->start_date
            : Carbon::parse($employment->start_date ?? Carbon::now());

        $salaryType = $employment->getSalaryTypeForDate($effectiveDate);
        $salaryAmount = $employment->getSalaryAmountForDate($effectiveDate);

        return [
            'salary_type' => $salaryType,
            'allocated_amount' => round($salaryAmount * $fte, 2),
        ];
    }

    /**
     * Apply salary metadata directly to an allocation instance.
     */
    public function applySalaryContext(
        EmployeeFundingAllocation $allocation,
        ?Carbon $effectiveDate = null
    ): EmployeeFundingAllocation {
        $employment = $allocation->employment ?? $allocation->employment()->first();

        if (! $employment) {
            throw new InvalidArgumentException('EmployeeFundingAllocation must belong to an employment.');
        }

        if (! $effectiveDate) {
            $effectiveDate = $employment->start_date instanceof Carbon
                ? $employment->start_date
                : Carbon::parse($employment->start_date ?? Carbon::now());
        }

        $allocation->fill(
            $this->deriveSalaryContext($employment, (float) $allocation->fte, $effectiveDate)
        );

        return $allocation;
    }
}
