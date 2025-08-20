<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        // // Get the current year.
        $currentYear = date('Y');

        // Retrieve all leave types.
        $leaveTypes = LeaveType::all();

        // Loop through each leave type and create a corresponding leave balance.
        foreach ($leaveTypes as $leaveType) {
            // You can optionally calculate a prorated duration if the employee joins mid-year.
            $defaultDuration = $leaveType->default_duration; // Use default_duration from LeaveType

            LeaveBalance::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'remaining_days' => $defaultDuration,
                'year' => $currentYear,
            ]);
        }
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
