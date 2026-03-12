<?php

namespace App\Services;

use App\DTOs\EmploymentListResult;
use App\DTOs\EmploymentUpdateResult;
use App\Exceptions\Employment\ActiveEmploymentExistsException;
use App\Exceptions\Employment\EmployeeNotFoundException;
use App\Exceptions\Employment\InvalidDateConstraintException;
use App\Exceptions\Employment\InvalidDepartmentPositionException;
use App\Exceptions\Employment\ProbationTransitionFailedException;
use App\Models\BenefitSetting;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Position;
use App\Models\TaxSetting;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmploymentService
{
    public function __construct(
        private readonly ProbationTransitionService $probationTransitionService,
        private readonly ProbationRecordService $probationRecordService,
        private readonly CacheManagerService $cacheManager,
    ) {}

    /**
     * Build, paginate, cache, and return employment list with applied filters.
     */
    public function list(array $validated): EmploymentListResult
    {
        $perPage = $validated['per_page'] ?? 10;
        $sortBy = $validated['sort_by'] ?? 'start_date';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $query = Employment::select([
            'id',
            'employee_id',
            'position_id',
            'department_id',
            'section_department_id',
            'site_id',
            'pay_method',
            'start_date',
            'end_date',
            'pass_probation_date',
            'end_probation_date',
            'probation_salary',
            'pass_probation_salary',
            'health_welfare',
            'pvd',
            'saving_fund',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
        ])->with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title,department_id',
            'site:id,name',
        ]);

        // Conditionally load allocations
        if ($validated['include_allocations'] ?? false) {
            $query->with([
                'employeeFundingAllocations:id,employment_id,fte,allocated_amount,grant_item_id',
                'employeeFundingAllocations.grantItem:id,grant_id,grant_position',
                'employeeFundingAllocations.grantItem.grant:id,name',
            ]);
        }

        // Apply organization filter
        if (! empty($validated['filter_organization'])) {
            $subsidiaries = array_map('trim', explode(',', $validated['filter_organization']));
            $query->whereIn('organization', $subsidiaries);
        }

        // Apply site filter
        if (! empty($validated['filter_site'])) {
            $sites = array_map('trim', explode(',', $validated['filter_site']));
            $query->where(function ($q) use ($sites) {
                $q->whereHas('site', function ($sq) use ($sites) {
                    $sq->whereIn('name', $sites);
                })->orWhereIn('site_id', array_filter($sites, 'is_numeric'));
            });
        }

        // Apply department filter
        if (! empty($validated['filter_department'])) {
            $departments = array_map('trim', explode(',', $validated['filter_department']));
            $query->whereHas('department', function ($dq) use ($departments) {
                $dq->whereIn('name', $departments);
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'staff_id':
                $query->addSelect([
                    'sort_staff_id' => Employee::select('staff_id')
                        ->whereColumn('employees.id', 'employments.employee_id')
                        ->limit(1),
                ])->orderBy('sort_staff_id', $sortOrder);
                break;

            case 'employee_name':
                $query->addSelect([
                    'sort_employee_name' => Employee::selectRaw("CONCAT(COALESCE(first_name_en, ''), ' ', COALESCE(last_name_en, ''))")
                        ->whereColumn('employees.id', 'employments.employee_id')
                        ->limit(1),
                ])->orderBy('sort_employee_name', $sortOrder);
                break;

            case 'site':
                $query->addSelect([
                    'sort_site_name' => DB::table('sites')
                        ->select('name')
                        ->whereColumn('sites.id', 'employments.site_id')
                        ->limit(1),
                ])->orderBy('sort_site_name', $sortOrder);
                break;

            case 'start_date':
            default:
                $query->orderBy('start_date', $sortOrder);
                break;
        }

        // Build cache filter keys
        $filters = array_filter([
            'filter_organization' => $validated['filter_organization'] ?? null,
            'filter_site' => $validated['filter_site'] ?? null,
            'filter_department' => $validated['filter_department'] ?? null,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'include_allocations' => $validated['include_allocations'] ?? false,
        ]);

        // Build applied filters for response
        $appliedFilters = [];
        if (! empty($validated['filter_organization'])) {
            $appliedFilters['organization'] = explode(',', $validated['filter_organization']);
        }
        if (! empty($validated['filter_site'])) {
            $appliedFilters['site'] = explode(',', $validated['filter_site']);
        }
        if (! empty($validated['filter_department'])) {
            $appliedFilters['department'] = explode(',', $validated['filter_department']);
        }

        // Cache and paginate
        $page = request('page', 1);
        $cacheParams = array_merge($filters, ['page' => $page, 'per_page' => $perPage]);
        if (Auth::check()) {
            $cacheParams['user_id'] = Auth::id();
        }
        $cacheKey = $this->cacheManager->generateKey('employment_list', $cacheParams);

        $paginator = $this->cacheManager->remember(
            $cacheKey,
            fn () => $query->paginate($perPage),
            CacheManagerService::SHORT_TTL,
            [CacheManagerService::CACHE_TAGS['employments'] ?? 'employment']
        );

        // Append global benefit percentages to each item
        $globalBenefits = $this->getGlobalBenefitPercentages();
        foreach ($paginator->items() as $item) {
            $item->health_welfare_percentage = $globalBenefits['health_welfare_percentage'];
            $item->pvd_percentage = $globalBenefits['pvd_percentage'];
            $item->saving_fund_percentage = $globalBenefits['saving_fund_percentage'];
        }

        return new EmploymentListResult($paginator, $appliedFilters);
    }

    /**
     * Get global benefit percentages from settings.
     */
    public function getGlobalBenefitPercentages(): array
    {
        return [
            'health_welfare_percentage' => BenefitSetting::getActiveSetting('health_welfare_percentage'),
            'pvd_percentage' => TaxSetting::getValue('PVD_FUND_RATE'),
            'saving_fund_percentage' => TaxSetting::getValue('SAVING_FUND_RATE'),
        ];
    }

    /**
     * Search employment records by employee staff ID.
     *
     * @throws EmployeeNotFoundException
     */
    public function searchByStaffId(string $staffId, bool $includeInactive): array
    {
        $employee = Employee::where('staff_id', $staffId)
            ->select('id', 'staff_id', 'first_name_en', 'last_name_en')
            ->first();

        if (! $employee) {
            throw new EmployeeNotFoundException($staffId);
        }

        $employmentsQuery = Employment::with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title,department_id',
            'site:id,name',
            'employeeFundingAllocations',
        ])->where('employee_id', $employee->id);

        if (! $includeInactive) {
            $employmentsQuery->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            });
        }

        $employments = $employmentsQuery->orderBy('start_date', 'desc')->get();

        $totalEmployments = $employments->count();
        $activeEmployments = $employments->filter(function ($employment) {
            return $employment->end_date === null;
        })->count();

        return [
            'employments' => $employments,
            'employee_summary' => [
                'staff_id' => $employee->staff_id,
                'full_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
                'organization' => $employee->employment?->organization,
            ],
            'statistics' => [
                'total_employments' => $totalEmployments,
                'active_employments' => $activeEmployments,
                'inactive_employments' => $totalEmployments - $activeEmployments,
            ],
        ];
    }

    /**
     * Create a new employment record.
     *
     * @throws ActiveEmploymentExistsException
     */
    public function create(array $validated): Employment
    {
        $currentUser = Auth::user()->name ?? 'system';

        // When probation is not required, clear all probation-related fields
        if (isset($validated['probation_required']) && $validated['probation_required'] === false) {
            $validated['pass_probation_date'] = null;
            $validated['end_probation_date'] = null;
            $validated['probation_salary'] = null;
        } elseif (! isset($validated['pass_probation_date']) && isset($validated['start_date'])) {
            // Auto-calculate pass_probation_date if not provided
            $validated['pass_probation_date'] = Carbon::parse($validated['start_date'])->addMonths(3)->format('Y-m-d');
        }

        // Check if employee already has active employment
        $today = Carbon::today();
        $existingActiveEmployment = Employment::where('employee_id', $validated['employee_id'])
            ->where('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_probation_date')
                    ->orWhere('end_probation_date', '>=', $today);
            })
            ->exists();

        if ($existingActiveEmployment) {
            throw new ActiveEmploymentExistsException;
        }

        DB::beginTransaction();

        try {
            $employmentData = array_merge($validated, [
                'created_by' => $currentUser,
                'updated_by' => $currentUser,
            ]);

            $employment = Employment::create($employmentData);

            // Create initial probation record (skip when probation is not required)
            if ($employment->probation_required !== false && $employment->pass_probation_date) {
                $this->probationRecordService->createInitialRecord($employment);
            }

            DB::commit();

            $this->invalidateCache($employment->id);

            // Load relationships for response
            return Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
                'site:id,name',
            ])->find($employment->id);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get a single employment with relations and benefit percentages.
     */
    public function show(Employment $employment): Employment
    {
        $employment->load(['employee', 'department', 'position', 'site']);

        $globalBenefits = $this->getGlobalBenefitPercentages();
        $employment->health_welfare_percentage = $globalBenefits['health_welfare_percentage'];
        $employment->pvd_percentage = $globalBenefits['pvd_percentage'];
        $employment->saving_fund_percentage = $globalBenefits['saving_fund_percentage'];

        return $employment;
    }

    /**
     * Update an existing employment record.
     *
     * @throws InvalidDepartmentPositionException
     * @throws InvalidDateConstraintException
     */
    public function update(Employment $employment, array $validated): EmploymentUpdateResult
    {
        $currentUser = Auth::user()->name ?? 'system';
        $oldStartDate = $employment->start_date;

        // Handle probation_required changes
        if (isset($validated['probation_required'])) {
            if ($validated['probation_required'] === false) {
                // Switching to no-probation: clear all probation fields
                $validated['pass_probation_date'] = null;
                $validated['end_probation_date'] = null;
                $validated['probation_salary'] = null;
            } elseif ($validated['probation_required'] === true && $employment->probation_required === false) {
                // Switching back to probation-required: auto-calculate dates from start_date
                $startDate = Carbon::parse($validated['start_date'] ?? $employment->start_date);
                $validated['pass_probation_date'] = $startDate->copy()->addMonths(3)->format('Y-m-d');
            }
        }

        // Recalculate pass_probation_date if start_date changes (only when probation is required)
        $probationRequired = $validated['probation_required'] ?? $employment->probation_required;
        if ($probationRequired !== false && isset($validated['start_date']) && ! isset($validated['pass_probation_date'])) {
            $newStartDate = Carbon::parse($validated['start_date']);
            if (! $oldStartDate || ! $newStartDate->eq(Carbon::parse($oldStartDate))) {
                $validated['pass_probation_date'] = $newStartDate->copy()->addMonths(3)->format('Y-m-d');
            }
        }

        // Validate department-position relationship
        $departmentId = $validated['department_id'] ?? $employment->department_id;
        $positionId = $validated['position_id'] ?? $employment->position_id;

        if ($departmentId && $positionId) {
            $position = Position::find($positionId);
            if ($position && $position->department_id != $departmentId) {
                throw new InvalidDepartmentPositionException;
            }
        }

        // Validate date constraints
        if (isset($validated['start_date']) && isset($validated['end_probation_date'])) {
            if ($validated['end_probation_date'] && $validated['start_date'] > $validated['end_probation_date']) {
                throw new InvalidDateConstraintException;
            }
        }

        DB::beginTransaction();

        try {
            $original = $employment->getOriginal();

            $employmentData = $validated;
            if (! empty($employmentData)) {
                $employmentData['updated_by'] = $currentUser;
                $employment->update($employmentData);
            }

            $employment = $employment->fresh();

            // Handle probation extension
            if (isset($validated['pass_probation_date']) &&
                isset($original['pass_probation_date']) &&
                $validated['pass_probation_date'] !== $original['pass_probation_date']) {
                $this->probationTransitionService->handleProbationExtension(
                    $employment,
                    $original['pass_probation_date'],
                    $validated['pass_probation_date']
                );
            }

            // Handle early termination
            if (isset($validated['end_probation_date']) &&
                $employment->pass_probation_date &&
                Carbon::parse($validated['end_probation_date'])->lt($employment->pass_probation_date)) {

                $probationResult = $this->probationTransitionService->handleEarlyTermination($employment);
                DB::commit();

                $this->invalidateCache($employment->id);

                return new EmploymentUpdateResult(
                    employment: $employment->fresh(['activeAllocations', 'inactiveAllocations']),
                    earlyTermination: true,
                    probationResult: $probationResult,
                );
            }

            DB::commit();

            $this->invalidateCache($employment->id);

            $employmentWithRelations = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
                'site:id,name',
            ])->find($employment->id);

            return new EmploymentUpdateResult(
                employment: $employmentWithRelations,
            );
        } catch (InvalidDepartmentPositionException|InvalidDateConstraintException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete an employment record.
     */
    public function delete(Employment $employment): void
    {
        $employmentId = $employment->id;
        $employment->delete();
        $this->invalidateCache($employmentId);
    }

    /**
     * Complete probation for an employment record.
     *
     * @throws ProbationTransitionFailedException
     */
    public function completeProbation(Employment $employment): array
    {
        $employment->load('employeeFundingAllocations');

        $result = $this->probationTransitionService->handleProbationCompletion($employment, now());

        if (! $result['success']) {
            throw new ProbationTransitionFailedException($result['message'], 400);
        }

        return [
            'employment' => $result['employment'],
            'updated_allocations' => $result['employment']->employeeFundingAllocations,
        ];
    }

    /**
     * Update probation status (passed/failed).
     *
     * @throws ProbationTransitionFailedException
     */
    public function updateProbationStatus(Employment $employment, array $validated): array
    {
        $employment->load(['activeAllocations', 'probationHistory']);

        $action = $validated['action'];
        $decisionDate = isset($validated['decision_date'])
            ? Carbon::parse($validated['decision_date'])
            : now();

        if ($action === 'passed') {
            $result = $this->probationTransitionService->handleProbationCompletion($employment, $decisionDate);
        } else {
            $result = $this->probationTransitionService->handleManualProbationFailure(
                $employment,
                $decisionDate,
                $validated['reason'] ?? null,
                $validated['notes'] ?? null
            );
        }

        if (! $result['success']) {
            throw new ProbationTransitionFailedException(
                $result['message'] ?? 'Unable to update probation status',
                422
            );
        }

        $employment->refresh();

        return [
            'employment' => $employment,
            'probation_history' => $this->probationRecordService->getHistory($employment),
            'message' => $action === 'passed'
                ? 'Probation marked as passed successfully.'
                : 'Probation marked as failed successfully.',
        ];
    }

    /**
     * Get probation history for an employment.
     */
    public function getProbationHistory(Employment $employment): array
    {
        $employment->load('probationHistory');

        return $this->probationRecordService->getHistory($employment);
    }

    /**
     * Queue employment import from uploaded file.
     */
    public function uploadEmployments(UploadedFile $file): array
    {
        $importId = 'employment_import_'.uniqid();
        $userId = auth()->id();

        $import = new \App\Imports\EmploymentsImport($importId, $userId);
        $import->queue($file);

        return [
            'import_id' => $importId,
            'status' => 'processing',
        ];
    }

    /**
     * Generate employment template for download.
     */
    public function downloadTemplate(): array
    {
        $export = new \App\Exports\EmploymentTemplateExport;

        return [
            'file' => $export->generate(),
            'filename' => $export->getFilename(),
        ];
    }

    /**
     * Invalidate all employment-related caches.
     */
    private function invalidateCache(?int $modelId = null): void
    {
        $this->cacheManager->clearModelCaches('employments', $modelId);
        $this->cacheManager->clearListCaches('employments');
        $this->cacheManager->clearReportCaches();
    }
}
