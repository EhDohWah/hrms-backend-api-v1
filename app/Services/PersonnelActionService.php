<?php

namespace App\Services;

use App\Models\Employment;
use App\Models\PersonnelAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonnelActionService
{
    public function __construct(
        private ?CacheManagerService $cacheManager = null
    ) {
        $this->cacheManager = $cacheManager ?? app(CacheManagerService::class);
    }

    public function createPersonnelAction(array $data): PersonnelAction
    {
        return DB::transaction(function () use ($data) {
            $personnelAction = PersonnelAction::create($data);

            // Auto-populate current employment data if not provided
            if (! $personnelAction->current_department_id) {
                $personnelAction->populateCurrentEmploymentData();
                $personnelAction->save();
            }

            // Generate reference number after creation
            $personnelAction->update([
                'reference_number' => $personnelAction->generateReferenceNumber(),
            ]);

            // Add employment history entry
            $employment = $personnelAction->employment;
            $employment->addHistoryEntry(
                "Personnel Action {$personnelAction->reference_number} created: {$personnelAction->action_type}",
                $personnelAction->comments
            );

            return $personnelAction->fresh();
        });
    }

    public function updateApproval(PersonnelAction $personnelAction, string $approvalType, bool $approved): bool
    {
        return DB::transaction(function () use ($personnelAction, $approvalType, $approved) {
            // Validate approval type
            $validApprovalTypes = ['dept_head', 'coo', 'hr', 'accountant'];
            if (! in_array($approvalType, $validApprovalTypes)) {
                throw new \InvalidArgumentException("Invalid approval type: {$approvalType}");
            }

            // Update the specific approval field
            $personnelAction->update([
                $approvalType.'_approved' => $approved,
                'updated_by' => Auth::id(),
            ]);

            // Check if all approvals are complete and implement if so
            if ($personnelAction->fresh()->isFullyApproved()) {
                $this->implementAction($personnelAction);
            }

            return true;
        });
    }

    public function implementAction(PersonnelAction $personnelAction): bool
    {
        return DB::transaction(function () use ($personnelAction) {
            $employment = $personnelAction->employment;

            // Update employment record based on action type
            switch ($personnelAction->action_type) {
                case 'appointment':
                    $this->handleAppointment($employment, $personnelAction);
                    break;
                case 'fiscal_increment':
                case 'position_change':
                    $this->handlePositionChange($employment, $personnelAction);
                    break;
                case 'transfer':
                    $this->handleTransfer($employment, $personnelAction);
                    break;
                case 'voluntary_separation':
                    $this->handleSeparation($employment, $personnelAction);
                    break;
                case 'title_change':
                    $this->handleTitleChange($employment, $personnelAction);
                    break;
                default:
                    Log::warning("Unknown personnel action type: {$personnelAction->action_type}");
            }

            // Clear relevant caches
            $this->clearEmploymentCaches($employment->id);

            return true;
        });
    }

    private function handleAppointment(Employment $employment, PersonnelAction $action): void
    {
        // For appointments, update position, department, salary, and location
        $updateData = array_filter([
            'position_id' => $action->new_position_id,
            'department_id' => $action->new_department_id,
            'pass_probation_salary' => $action->new_salary,
            'work_location_id' => $action->new_work_location_id,
            'updated_by' => Auth::user()?->name ?? 'Personnel Action',
        ], fn ($value) => $value !== null);

        if (! empty($updateData)) {
            $employment->update($updateData);
        }
    }

    private function handlePositionChange(Employment $employment, PersonnelAction $action): void
    {
        // For position changes and fiscal increments, update position, department, and salary
        $updateData = array_filter([
            'position_id' => $action->new_position_id,
            'department_id' => $action->new_department_id,
            'pass_probation_salary' => $action->new_salary,
            'updated_by' => Auth::user()?->name ?? 'Personnel Action',
        ], fn ($value) => $value !== null);

        if (! empty($updateData)) {
            $employment->update($updateData);
        }
    }

    private function handleTransfer(Employment $employment, PersonnelAction $action): void
    {
        // For transfers, update department, location, and optionally position
        $updateData = array_filter([
            'department_id' => $action->new_department_id,
            'work_location_id' => $action->new_work_location_id,
            'position_id' => $action->new_position_id,
            'updated_by' => Auth::user()?->name ?? 'Personnel Action',
        ], fn ($value) => $value !== null);

        if (! empty($updateData)) {
            $employment->update($updateData);
        }
    }

    private function handleSeparation(Employment $employment, PersonnelAction $action): void
    {
        // Handle separation - set end date
        $employment->update([
            'end_date' => $action->effective_date,
            'updated_by' => Auth::user()?->name ?? 'Personnel Action',
        ]);
    }

    private function handleTitleChange(Employment $employment, PersonnelAction $action): void
    {
        // For title changes, only update position if provided
        if ($action->new_position_id) {
            $employment->update([
                'position_id' => $action->new_position_id,
                'updated_by' => Auth::user()?->name ?? 'Personnel Action',
            ]);
        }
    }

    private function clearEmploymentCaches(int $employmentId): void
    {
        if ($this->cacheManager) {
            $this->cacheManager->clearModelCaches('employments', $employmentId);
        } else {
            Cache::tags(['employments', "employment.{$employmentId}"])->flush();
        }
    }

    public function getPersonnelActionsByEmployee(int $employmentId): \Illuminate\Database\Eloquent\Collection
    {
        return PersonnelAction::where('employment_id', $employmentId)
            ->with(['employment.employee', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPendingApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        return PersonnelAction::where(function ($query) {
            $query->where('dept_head_approved', false)
                ->orWhere('coo_approved', false)
                ->orWhere('hr_approved', false)
                ->orWhere('accountant_approved', false);
        })
            ->with(['employment.employee', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
