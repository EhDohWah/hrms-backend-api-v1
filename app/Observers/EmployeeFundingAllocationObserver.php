<?php

namespace App\Observers;

use App\Models\EmployeeFundingAllocation;
use App\Models\EmployeeFundingAllocationHistory;

/**
 * Observer to automatically create history records for funding allocation changes.
 *
 * This captures all allocation lifecycle events:
 * - Created: New allocation added
 * - Updated: Allocation modified (FTE, grant item, etc.)
 * - Historical/Terminated: Status changed (tracked via updated event)
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

        // If status changed to historical or terminated, record appropriately
        if (isset($changes['status'])) {
            if ($changes['status'] === 'historical') {
                EmployeeFundingAllocationHistory::recordEnded(
                    $allocation,
                    'Allocation replaced with new allocation'
                );

                return;
            }

            if ($changes['status'] === 'terminated') {
                EmployeeFundingAllocationHistory::recordTermination(
                    $allocation,
                    'Allocation terminated'
                );

                return;
            }
        }

        // For regular updates (FTE change, grant item change, etc.)
        $oldValues = [];
        $trackableFields = ['fte', 'allocated_amount', 'grant_item_id', 'salary_type', 'start_date', 'end_date'];

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
