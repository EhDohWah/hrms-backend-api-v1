<?php

namespace App\Services;

use App\Models\Employment;
use App\Models\PersonnelAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonnelActionService
{
    private const EAGER_LOAD = [
        'employment.employee',
        'creator',
        'currentDepartment',
        'currentPosition',
        'currentSite',
        'newDepartment',
        'newPosition',
        'newSite',
    ];

    public function __construct(
        private ?CacheManagerService $cacheManager = null
    ) {
        $this->cacheManager = $cacheManager ?? app(CacheManagerService::class);
    }

    /**
     * List personnel actions with filtering and pagination.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return PersonnelAction::with(self::EAGER_LOAD)
            ->when(isset($filters['dept_head_approved']), fn ($q) => $q->where('dept_head_approved', (bool) $filters['dept_head_approved']))
            ->when(isset($filters['coo_approved']), fn ($q) => $q->where('coo_approved', (bool) $filters['coo_approved']))
            ->when(isset($filters['hr_approved']), fn ($q) => $q->where('hr_approved', (bool) $filters['hr_approved']))
            ->when(isset($filters['accountant_approved']), fn ($q) => $q->where('accountant_approved', (bool) $filters['accountant_approved']))
            ->when($filters['action_type'] ?? null, fn ($q, $v) => $q->where('action_type', $v))
            ->when($filters['employment_id'] ?? null, fn ($q, $v) => $q->where('employment_id', $v))
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get personnel action constants for dropdowns.
     */
    public function constants(): array
    {
        return [
            'action_types' => PersonnelAction::ACTION_TYPES,
            'action_subtypes' => PersonnelAction::ACTION_SUBTYPES,
            'statuses' => PersonnelAction::STATUSES,
        ];
    }

    /**
     * Show a single personnel action with eager-loaded relationships.
     */
    public function show(PersonnelAction $personnelAction): PersonnelAction
    {
        return $personnelAction->load(self::EAGER_LOAD);
    }

    /**
     * Store a new personnel action.
     */
    public function store(array $data): PersonnelAction
    {
        $personnelAction = $this->createPersonnelAction($data);

        return $personnelAction->load(self::EAGER_LOAD);
    }

    /**
     * Update a personnel action.
     */
    public function update(PersonnelAction $personnelAction, array $data): PersonnelAction
    {
        $personnelAction->update($data);

        return $personnelAction->fresh()->load(self::EAGER_LOAD);
    }

    /**
     * Update approval status and return refreshed action.
     */
    public function approve(PersonnelAction $personnelAction, string $approvalType, bool $approved): PersonnelAction
    {
        $this->updateApproval($personnelAction, $approvalType, $approved);

        return $personnelAction->fresh()->load(self::EAGER_LOAD);
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

            // Apply employment changes immediately on save
            $this->applyToEmployment($personnelAction);
            $personnelAction->update(['implemented_at' => now()]);

            // Add employment history entry
            $employment = $personnelAction->employment->fresh();
            $employment->addHistoryEntry(
                "Personnel Action {$personnelAction->reference_number} applied: {$personnelAction->action_type}",
                $personnelAction->comments
            );

            $this->clearEmploymentCaches($employment->id);

            return $personnelAction->fresh();
        });
    }

    public function updateApproval(PersonnelAction $personnelAction, string $approvalType, bool $approved, ?string $approvalDate = null): bool
    {
        return DB::transaction(function () use ($personnelAction, $approvalType, $approved, $approvalDate) {
            $validApprovalTypes = ['dept_head', 'coo', 'hr', 'accountant'];
            if (! in_array($approvalType, $validApprovalTypes)) {
                throw new \InvalidArgumentException("Invalid approval type: {$approvalType}");
            }

            $updateData = [
                $approvalType.'_approved' => $approved,
                $approvalType.'_approved_date' => $approved ? ($approvalDate ?? now()->toDateString()) : null,
                'updated_by' => Auth::id(),
            ];

            $personnelAction->update($updateData);

            return true;
        });
    }

    private function applyToEmployment(PersonnelAction $action): void
    {
        $emp = $action->employment;
        $updatedBy = Auth::user()?->name ?? 'Personnel Action';

        match ($action->action_type) {
            'appointment' => $emp->update(array_filter([
                'position_id' => $action->new_position_id,
                'department_id' => $action->new_department_id,
                'site_id' => $action->new_site_id,
                'pass_probation_salary' => $action->new_salary,
                'updated_by' => $updatedBy,
            ], fn ($v) => $v !== null)),

            'fiscal_increment', 're_evaluated_pay' => $emp->update(array_filter([
                'previous_year_salary' => $emp->pass_probation_salary,
                'pass_probation_salary' => $action->new_salary,
                'updated_by' => $updatedBy,
            ], fn ($v) => $v !== null)),

            'promotion', 'demotion' => $emp->update(array_filter([
                'position_id' => $action->new_position_id,
                'department_id' => $action->new_department_id,
                'previous_year_salary' => $emp->pass_probation_salary,
                'pass_probation_salary' => $action->new_salary ?? $emp->pass_probation_salary,
                'updated_by' => $updatedBy,
            ], fn ($v) => $v !== null)),

            'position_change' => $emp->update(array_filter([
                'position_id' => $action->new_position_id,
                'department_id' => $action->new_department_id,
                'pass_probation_salary' => $action->new_salary,
                'updated_by' => $updatedBy,
            ], fn ($v) => $v !== null)),

            'title_change' => $action->new_position_id ? $emp->update([
                'position_id' => $action->new_position_id,
                'updated_by' => $updatedBy,
            ]) : null,

            'voluntary_separation', 'end_of_contract' => $emp->update([
                'end_date' => $action->effective_date,
                'updated_by' => $updatedBy,
            ]),

            'work_allocation' => $emp->update(array_filter([
                'department_id' => $action->new_department_id,
                'site_id' => $action->new_site_id,
                'updated_by' => $updatedBy,
            ], fn ($v) => $v !== null)),

            'transfer' => match ($action->action_subtype) {
                'internal_department' => $emp->update(array_filter([
                    'department_id' => $action->new_department_id,
                    'position_id' => $action->new_position_id,
                    'updated_by' => $updatedBy,
                ], fn ($v) => $v !== null)),
                'site_to_site' => $emp->update(array_filter([
                    'site_id' => $action->new_site_id,
                    'updated_by' => $updatedBy,
                ], fn ($v) => $v !== null)),
                default => null,
            },

            default => Log::warning("Unknown personnel action type: {$action->action_type}"),
        };
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
