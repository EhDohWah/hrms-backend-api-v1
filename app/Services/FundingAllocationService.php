<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\OrgFundedAllocation;
use App\Models\PositionSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FundingAllocationService
{
    /**
     * Allocate employee to funding sources with comprehensive validation
     */
    public function allocateEmployee(Employee $employee, Employment $employment, array $allocations): array
    {
        // Validate total effort equals 100%
        $this->validateTotalEffort($allocations);

        $createdAllocations = [];
        $createdOrgFunded = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($allocations as $index => $allocationData) {
                $result = $this->createAllocation($employee, $employment, $allocationData, $index);

                if (isset($result['error'])) {
                    $errors[] = $result['error'];

                    continue;
                }

                $createdAllocations[] = $result['allocation'];
                if (isset($result['org_funded'])) {
                    $createdOrgFunded[] = $result['org_funded'];
                }
            }

            if (! empty($errors)) {
                DB::rollBack();

                return ['success' => false, 'errors' => $errors];
            }

            DB::commit();

            return [
                'success' => true,
                'allocations' => $createdAllocations,
                'org_funded' => $createdOrgFunded,
                'summary' => [
                    'total_allocations' => count($createdAllocations),
                    'org_funded_created' => count($createdOrgFunded),
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'errors' => ['System error: '.$e->getMessage()]];
        }
    }

    /**
     * Create individual allocation with validation
     */
    private function createAllocation(Employee $employee, Employment $employment, array $data, int $index): array
    {
        $allocationType = $data['allocation_type'];
        $currentUser = Auth::user()->name ?? 'system';

        if ($allocationType === 'grant') {
            return $this->createGrantAllocation($employee, $employment, $data, $index, $currentUser);
        } elseif ($allocationType === 'org_funded') {
            return $this->createOrgFundedAllocation($employee, $employment, $data, $index, $currentUser);
        }

        return ['error' => "Allocation #{$index}: Invalid allocation type"];
    }

    /**
     * Create grant-based allocation
     */
    private function createGrantAllocation(Employee $employee, Employment $employment, array $data, int $index, string $currentUser): array
    {
        // Validate position slot exists
        $positionSlot = PositionSlot::with('grantItem')->find($data['position_slot_id']);
        if (! $positionSlot) {
            return ['error' => "Allocation #{$index}: Position slot not found"];
        }

        // Check grant capacity constraints
        $capacityCheck = $this->validateGrantCapacity($positionSlot, $employment->id);
        if (! $capacityCheck['valid']) {
            return ['error' => "Allocation #{$index}: ".$capacityCheck['message']];
        }

        // Create grant funding allocation
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'position_slot_id' => $data['position_slot_id'],
            'org_funded_id' => null,
            'level_of_effort' => $data['level_of_effort'] / 100, // Convert percentage to decimal
            'allocation_type' => 'grant',
            'allocated_amount' => $data['allocated_amount'] ?? null,
            'start_date' => $employment->start_date,
            'end_date' => $employment->end_date ?? null,
            'created_by' => $currentUser,
            'updated_by' => $currentUser,
        ]);

        return ['allocation' => $allocation];
    }

    /**
     * Create organization-funded allocation
     */
    private function createOrgFundedAllocation(Employee $employee, Employment $employment, array $data, int $index, string $currentUser): array
    {
        if (empty($data['grant_id'])) {
            return ['error' => "Allocation #{$index}: grant_id is required for org_funded allocations"];
        }

        // Create org_funded_allocation record first
        $orgFundedAllocation = OrgFundedAllocation::create([
            'grant_id' => $data['grant_id'],
            'department_position_id' => $employment->department_position_id,
            'description' => 'Auto-created for employment ID: '.$employment->id,
            'created_by' => $currentUser,
            'updated_by' => $currentUser,
        ]);

        // Create employee funding allocation
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'position_slot_id' => null,
            'org_funded_id' => $orgFundedAllocation->id,
            'level_of_effort' => $data['level_of_effort'] / 100, // Convert percentage to decimal
            'allocation_type' => 'org_funded',
            'allocated_amount' => $data['allocated_amount'] ?? null,
            'start_date' => $employment->start_date,
            'end_date' => $employment->end_date ?? null,
            'created_by' => $currentUser,
            'updated_by' => $currentUser,
        ]);

        return [
            'allocation' => $allocation,
            'org_funded' => $orgFundedAllocation,
        ];
    }

    /**
     * Validate grant capacity constraints
     */
    public function validateGrantCapacity(PositionSlot $positionSlot, ?int $excludeEmploymentId = null): array
    {
        $grantItem = $positionSlot->grantItem;
        if (! $grantItem || $grantItem->grant_position_number <= 0) {
            return ['valid' => true]; // No capacity constraints
        }

        $today = Carbon::today();
        $query = EmployeeFundingAllocation::whereHas('positionSlot', function ($q) use ($grantItem) {
            $q->where('grant_item_id', $grantItem->id);
        })
            ->where('allocation_type', 'grant')
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            });

        if ($excludeEmploymentId) {
            $query->where('employment_id', '!=', $excludeEmploymentId);
        }

        $currentAllocations = $query->count();

        if ($currentAllocations >= $grantItem->grant_position_number) {
            return [
                'valid' => false,
                'message' => "Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}",
            ];
        }

        return [
            'valid' => true,
            'available_slots' => $grantItem->grant_position_number - $currentAllocations,
            'total_slots' => $grantItem->grant_position_number,
        ];
    }

    /**
     * Calculate available slots for a grant
     */
    public function calculateAvailableSlots(Grant $grant): array
    {
        $grantItems = $grant->grantItems()->with('positionSlots')->get();
        $summary = [];

        foreach ($grantItems as $grantItem) {
            $totalSlots = $grantItem->grant_position_number ?? 0;
            $allocatedSlots = 0;

            foreach ($grantItem->positionSlots as $slot) {
                $capacityCheck = $this->validateGrantCapacity($slot);
                $allocatedSlots += ($capacityCheck['total_slots'] ?? 0) - ($capacityCheck['available_slots'] ?? 0);
            }

            $summary[] = [
                'grant_item_id' => $grantItem->id,
                'position' => $grantItem->grant_position,
                'total_slots' => $totalSlots,
                'allocated_slots' => $allocatedSlots,
                'available_slots' => max(0, $totalSlots - $allocatedSlots),
                'utilization_percentage' => $totalSlots > 0 ? round(($allocatedSlots / $totalSlots) * 100, 2) : 0,
            ];
        }

        return $summary;
    }

    /**
     * Validate total effort equals 100%
     */
    private function validateTotalEffort(array $allocations): void
    {
        $totalEffort = array_sum(array_column($allocations, 'level_of_effort'));
        if ($totalEffort != 100) {
            throw new \InvalidArgumentException("Total effort of all allocations must equal exactly 100%. Current total: {$totalEffort}%");
        }
    }

    /**
     * Update existing allocations
     */
    public function updateAllocations(Employment $employment, array $newAllocations): array
    {
        $this->validateTotalEffort($newAllocations);

        DB::beginTransaction();

        try {
            // Remove existing allocations and their org_funded records
            $existingAllocations = EmployeeFundingAllocation::where('employment_id', $employment->id)->get();
            $orgFundedIdsToDelete = $existingAllocations->whereNotNull('org_funded_id')->pluck('org_funded_id')->toArray();

            // Delete existing allocations
            EmployeeFundingAllocation::where('employment_id', $employment->id)->delete();

            // Delete orphaned org_funded_allocations
            if (! empty($orgFundedIdsToDelete)) {
                OrgFundedAllocation::whereIn('id', $orgFundedIdsToDelete)->delete();
            }

            // Create new allocations
            $result = $this->allocateEmployee($employment->employee, $employment, $newAllocations);

            if (! $result['success']) {
                DB::rollBack();

                return $result;
            }

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'errors' => ['Update failed: '.$e->getMessage()]];
        }
    }

    /**
     * Get allocation summary for an employee
     */
    public function getAllocationSummary(Employee $employee): array
    {
        $allocations = $employee->employeeFundingAllocations()
            ->with(['positionSlot.grantItem.grant', 'orgFunded.grant', 'employment'])
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->get();

        $summary = [
            'total_allocations' => $allocations->count(),
            'total_effort' => $allocations->sum('level_of_effort'),
            'funding_sources' => [],
            'by_type' => $allocations->groupBy('allocation_type')->map->count()->toArray(),
        ];

        foreach ($allocations as $allocation) {
            $fundingSource = [
                'id' => $allocation->id,
                'type' => $allocation->allocation_type,
                'effort_percentage' => $allocation->level_of_effort * 100,
                'allocated_amount' => $allocation->allocated_amount,
            ];

            if ($allocation->allocation_type === 'grant' && $allocation->positionSlot) {
                $fundingSource['grant'] = $allocation->positionSlot->grantItem->grant->name ?? 'Unknown Grant';
                $fundingSource['position'] = $allocation->positionSlot->grantItem->grant_position ?? 'Unknown Position';
            } elseif ($allocation->allocation_type === 'org_funded' && $allocation->orgFunded) {
                $fundingSource['grant'] = $allocation->orgFunded->grant->name ?? 'Unknown Grant';
                $fundingSource['description'] = $allocation->orgFunded->description;
            }

            $summary['funding_sources'][] = $fundingSource;
        }

        return $summary;
    }

    /**
     * Validate employment date overlaps
     */
    public function validateEmploymentDates(Employee $employee, Carbon $startDate, ?Carbon $endDate = null, ?int $excludeEmploymentId = null): array
    {
        $query = $employee->employments();

        if ($excludeEmploymentId) {
            $query->where('id', '!=', $excludeEmploymentId);
        }

        $existingEmployments = $query->get();

        foreach ($existingEmployments as $employment) {
            $existingStart = Carbon::parse($employment->start_date);
            $existingEnd = $employment->end_date ? Carbon::parse($employment->end_date) : null;

            // Check for overlap
            $hasOverlap = false;

            if ($endDate) {
                // New employment has end date
                if ($existingEnd) {
                    // Both have end dates
                    $hasOverlap = $startDate->lte($existingEnd) && $endDate->gte($existingStart);
                } else {
                    // Existing is ongoing
                    $hasOverlap = $startDate->lte($existingStart) || $endDate->gte($existingStart);
                }
            } else {
                // New employment is ongoing
                if ($existingEnd) {
                    // Existing has end date
                    $hasOverlap = $startDate->lte($existingEnd);
                } else {
                    // Both are ongoing - definitely overlap
                    $hasOverlap = true;
                }
            }

            if ($hasOverlap) {
                return [
                    'valid' => false,
                    'message' => "Employment dates overlap with existing employment (ID: {$employment->id}) from {$existingStart->format('Y-m-d')} to ".
                                ($existingEnd ? $existingEnd->format('Y-m-d') : 'ongoing'),
                ];
            }
        }

        return ['valid' => true];
    }
}
