<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferService
{
    private const EAGER_LOAD = [
        'employee:id,staff_id,first_name_en,last_name_en',
        'creator:id,name',
    ];

    public function list(array $filters): LengthAwarePaginator
    {
        return Transfer::with(self::EAGER_LOAD)
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['from_organization'] ?? null, fn ($q, $v) => $q->where('from_organization', $v))
            ->when($filters['to_organization'] ?? null, fn ($q, $v) => $q->where('to_organization', $v))
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function show(Transfer $transfer): Transfer
    {
        return $transfer->load(self::EAGER_LOAD);
    }

    public function store(array $data): Transfer
    {
        return DB::transaction(function () use ($data) {
            $employee = Employee::findOrFail($data['employee_id']);
            $employment = $employee->employment;

            if (! $employment) {
                throw new \InvalidArgumentException('Employee does not have an active employment record.');
            }

            $fromOrganization = $employment->organization;

            // Update employments.organization
            $employment->update([
                'organization' => $data['to_organization'],
                'updated_by' => Auth::user()?->name ?? 'System',
            ]);

            // Store the transfer record
            $transfer = Transfer::create([
                'employee_id' => $employee->id,
                'from_organization' => $fromOrganization,
                'to_organization' => $data['to_organization'],
                'from_start_date' => $employment->start_date,
                'to_start_date' => $data['to_start_date'],
                'reason' => $data['reason'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return $transfer->load(self::EAGER_LOAD);
        });
    }

    public function destroy(Transfer $transfer): void
    {
        $transfer->delete();
    }
}
