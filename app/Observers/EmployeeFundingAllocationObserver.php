<?php

namespace App\Observers;

use App\Enums\FundingAllocationStatus;
use App\Models\EmployeeFundingAllocation;
use App\Models\EmployeeFundingAllocationHistory;

/**
 * Observer to automatically create history records for funding allocation changes.
 *
 * This captures all allocation lifecycle events:
 * - Created: New allocation added
 * - Updated: Allocation modified (FTE, grant item, etc.)
 * - Closed/Inactive: Status changed (tracked via updated event)
 */
class EmployeeFundingAllocationObserver
{
    /**
     * Handle the "created" event.
     */
    public function created(EmployeeFundingAllocation $allocation): void
    {
        EmployeeFundingAllocationHistory::recordCreation(
            $allocation,
            'New allocation created'
        );
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(EmployeeFundingAllocation $allocation): void
    {
        // Check what changed
        $changes = $allocation->getChanges();

        // If status changed to closed or inactive, record appropriately
        if (isset($changes['status'])) {
            if ($changes['status'] === FundingAllocationStatus::Closed) {
                EmployeeFundingAllocationHistory::recordEnded(
                    $allocation,
                    'Allocation replaced with new allocation'
                );

                return;
            }

            if ($changes['status'] === FundingAllocationStatus::Inactive) {
                EmployeeFundingAllocationHistory::recordTermination(
                    $allocation,
                    'Allocation terminated'
                );

                return;
            }
        }

        // For regular updates (FTE change, grant item change, etc.)
        $oldValues = [];
        $trackableFields = ['fte', 'allocated_amount', 'grant_item_id', 'salary_type'];

        foreach ($trackableFields as $field) {
            if ($allocation->wasChanged($field)) {
                $oldValues[$field] = $allocation->getOriginal($field);
            }
        }

        if (! empty($oldValues)) {
            EmployeeFundingAllocationHistory::recordUpdate(
                $allocation,
                $oldValues,
                'Allocation updated'
            );
        }
    }
}
