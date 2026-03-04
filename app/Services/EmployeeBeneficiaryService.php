<?php

namespace App\Services;

use App\Models\EmployeeBeneficiary;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EmployeeBeneficiaryService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Retrieve all employee beneficiaries with their employee relationship.
     */
    public function list(): Collection
    {
        return EmployeeBeneficiary::with('employee')->get();
    }

    /**
     * Show a single employee beneficiary with its employee relationship.
     */
    public function show(EmployeeBeneficiary $employeeBeneficiary): EmployeeBeneficiary
    {
        return $employeeBeneficiary->load('employee');
    }

    /**
     * Create a new employee beneficiary and send notification.
     */
    public function create(array $data): EmployeeBeneficiary
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $beneficiary = EmployeeBeneficiary::create($data);
        $beneficiary->load('employee');

        $this->sendNotification($beneficiary);

        return $beneficiary;
    }

    /**
     * Update an existing employee beneficiary and send notification.
     */
    public function update(EmployeeBeneficiary $employeeBeneficiary, array $data): EmployeeBeneficiary
    {
        $data['updated_by'] = Auth::id();

        $employeeBeneficiary->update($data);
        $employeeBeneficiary->load('employee');

        $this->sendNotification($employeeBeneficiary);

        return $employeeBeneficiary;
    }

    /**
     * Delete an employee beneficiary and send notification.
     */
    public function delete(EmployeeBeneficiary $employeeBeneficiary): void
    {
        // Store employee reference before deletion
        $employee = $employeeBeneficiary->employee;
        $performedBy = Auth::user();

        $employeeBeneficiary->delete();

        if ($performedBy && $employee) {
            $this->notificationService->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                'updated'
            );
        }
    }

    /**
     * Send employee update notification after beneficiary change.
     */
    private function sendNotification(EmployeeBeneficiary $beneficiary): void
    {
        $performedBy = Auth::user();

        if ($performedBy && $beneficiary->employee) {
            $this->notificationService->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $beneficiary->employee, $performedBy, 'employees'),
                'updated'
            );
        }
    }
}
