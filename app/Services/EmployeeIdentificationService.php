<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeIdentification;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeIdentificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function listByEmployee(int $employeeId): Collection
    {
        return EmployeeIdentification::where('employee_id', $employeeId)
            ->with('employee:id,staff_id,first_name_en,last_name_en')
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();
    }

    public function show(EmployeeIdentification $identification): EmployeeIdentification
    {
        return $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
    }

    public function create(array $data): EmployeeIdentification
    {
        $user = Auth::user();
        $data['created_by'] = $user->name ?? 'System';
        $data['updated_by'] = $user->name ?? 'System';

        $existingCount = EmployeeIdentification::where('employee_id', $data['employee_id'])->count();
        if ($existingCount === 0) {
            $data['is_primary'] = true;
        }

        $identification = EmployeeIdentification::create($data);
        $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');

        $this->notifyAction($identification);

        return $identification;
    }

    public function update(EmployeeIdentification $identification, array $data): EmployeeIdentification
    {
        $data['updated_by'] = Auth::user()->name ?? 'System';

        $identification->update($data);
        $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');

        $this->notifyAction($identification);

        return $identification;
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function delete(EmployeeIdentification $identification): array
    {
        if ($identification->is_primary) {
            $otherCount = EmployeeIdentification::where('employee_id', $identification->employee_id)
                ->where('id', '!=', $identification->id)
                ->count();

            if ($otherCount === 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete the only identification record. Add another identification first.',
                ];
            }
        }

        $wasPrimary = $identification->is_primary;
        $employeeId = $identification->employee_id;

        $identification->delete();

        if ($wasPrimary) {
            $nextPrimary = EmployeeIdentification::where('employee_id', $employeeId)
                ->orderByDesc('created_at')
                ->first();

            if ($nextPrimary) {
                $nextPrimary->update([
                    'is_primary' => true,
                    'updated_by' => Auth::user()->name ?? 'System',
                ]);
            }
        }

        return ['success' => true];
    }

    public function setPrimary(EmployeeIdentification $identification): EmployeeIdentification
    {
        if ($identification->is_primary) {
            return $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
        }

        DB::transaction(function () use (&$identification) {
            $identification = EmployeeIdentification::where('id', $identification->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Lock employee record to prevent race conditions
            Employee::where('id', $identification->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            EmployeeIdentification::where('employee_id', $identification->employee_id)
                ->where('id', '!=', $identification->id)
                ->lockForUpdate()
                ->update(['is_primary' => false]);

            $identification->update([
                'is_primary' => true,
                'updated_by' => Auth::user()->name ?? 'System',
            ]);
        });

        $identification->refresh();
        $identification->load('employee:id,staff_id,first_name_en,last_name_en');

        $this->notifyAction($identification);

        return $identification;
    }

    private function notifyAction(EmployeeIdentification $identification): void
    {
        $performedBy = Auth::user();
        if (! $performedBy) {
            return;
        }

        $employee = $identification->employee;
        if (! $employee) {
            return;
        }

        $this->notificationService->notifyByModule(
            'employees',
            new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
            'updated'
        );
    }
}
