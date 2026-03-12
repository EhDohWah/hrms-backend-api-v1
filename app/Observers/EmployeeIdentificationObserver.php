<?php

namespace App\Observers;

use App\Models\EmployeeIdentification;

class EmployeeIdentificationObserver
{
    public function created(EmployeeIdentification $identification): void
    {
        if ($identification->is_primary) {
            $this->syncNamesToEmployee($identification);
        }
    }

    public function updated(EmployeeIdentification $identification): void
    {
        if ($identification->is_primary && $identification->wasChanged('is_primary')) {
            $this->syncNamesToEmployee($identification);

            return;
        }

        if ($identification->is_primary && $this->nameFieldsChanged($identification)) {
            $this->syncNamesToEmployee($identification);
        }
    }

    private function syncNamesToEmployee(EmployeeIdentification $identification): void
    {
        $updates = [];

        foreach (EmployeeIdentification::NAME_FIELDS as $field) {
            if ($identification->$field !== null) {
                $updates[$field] = $identification->$field;
            }
        }

        if (! empty($updates)) {
            $updates['updated_by'] = $identification->updated_by ?? $identification->created_by ?? 'System';

            // Use Eloquent model update (not relation query builder) so that
            // model events fire and LogsActivity captures old→new name changes
            $employee = $identification->employee;
            if ($employee) {
                $employee->update($updates);
            }
        }
    }

    private function nameFieldsChanged(EmployeeIdentification $identification): bool
    {
        foreach (EmployeeIdentification::NAME_FIELDS as $field) {
            if ($identification->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
