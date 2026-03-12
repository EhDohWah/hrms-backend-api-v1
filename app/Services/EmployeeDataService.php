<?php

namespace App\Services;

use App\Exceptions\DeletionBlockedException;
use App\Imports\EmployeesImport;
use App\Models\Employee;
use App\Models\EmployeeIdentification;
use App\Models\GrantItem;
use App\Models\Site;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeDataService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Retrieve paginated employee list with filters, search, sorting, and statistics.
     */
    public function list(array $validated): array
    {
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;

        $query = Employee::forPagination()->withOptimizedRelations();

        // Search
        if (! empty($validated['search'])) {
            $searchTerm = trim($validated['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // Filters
        if (! empty($validated['filter_organization'])) {
            $query->byOrganization($validated['filter_organization']);
        }
        if (! empty($validated['filter_status'])) {
            $query->byStatus($validated['filter_status']);
        }
        if (! empty($validated['filter_gender'])) {
            $query->byGender($validated['filter_gender']);
        }
        if (! empty($validated['filter_age'])) {
            $query->byAge($validated['filter_age']);
        }
        if (! empty($validated['filter_identification_type'])) {
            $query->byIdType($validated['filter_identification_type']);
        }
        if (! empty($validated['filter_staff_id'])) {
            $query->where('staff_id', 'like', '%'.$validated['filter_staff_id'].'%');
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        if ($sortBy === 'organization') {
            $query->join('employments', function ($join) {
                $join->on('employees.id', '=', 'employments.employee_id')
                    ->whereNull('employments.end_date');
            })->orderBy('employments.organization', $sortOrder)->select('employees.*');
        } elseif (in_array($sortBy, ['staff_id', 'first_name_en', 'last_name_en', 'gender', 'date_of_birth', 'status'])) {
            $query->orderBy('employees.'.$sortBy, $sortOrder);
        } elseif ($sortBy === 'age') {
            $query->orderBy('employees.date_of_birth', $sortOrder === 'asc' ? 'desc' : 'asc');
        } elseif ($sortBy === 'identification_type') {
            $query->leftJoin('employee_identifications', function ($join) {
                $join->on('employees.id', '=', 'employee_identifications.employee_id')
                    ->where('employee_identifications.is_primary', true);
            })
                ->orderBy('employee_identifications.identification_type', $sortOrder)
                ->select('employees.*');
        } else {
            $query->orderBy('employees.created_at', 'desc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Build applied filters
        $appliedFilters = $this->buildAppliedFilters($validated);

        return [
            'paginator' => $paginator,
            'statistics' => Employee::getStatistics(),
            'applied_filters' => $appliedFilters,
        ];
    }

    /**
     * Retrieve single employee with all related data.
     */
    public function show(Employee $employee): Employee
    {
        return $employee->load([
            'employment',
            'employment.department',
            'employment.position',
            'employment.site',
            'employeeFundingAllocations',
            'employeeFundingAllocations.grantItem',
            'employeeFundingAllocations.grantItem.grant',
            'employeeFundingAllocations.employment',
            'employeeFundingAllocations.employment.department',
            'employeeFundingAllocations.employment.position',
            'employeeBeneficiaries',
            'employeeEducation',
            'employeeChildren',
            'employeeLanguages',
            'leaveBalances',
            'leaveBalances.leaveType',
            'identifications',
            'primaryIdentification',
        ]);
    }

    /**
     * Create a new employee record.
     */
    public function store(array $validated): Employee
    {
        $identificationData = $this->extractIdentificationData($validated);

        $employee = Employee::create($validated);

        if (! empty($identificationData)) {
            $createdBy = $validated['created_by'] ?? auth()->user()->name ?? 'System';
            $identificationData['employee_id'] = $employee->id;
            $identificationData['is_primary'] = true;
            $identificationData['created_by'] = $createdBy;
            $identificationData['updated_by'] = $createdBy;

            foreach (EmployeeIdentification::NAME_FIELDS as $field) {
                $identificationData[$field] = $validated[$field] ?? null;
            }

            EmployeeIdentification::create($identificationData);
        }

        $this->invalidateCache();
        $this->notifyAction('created', $employee);

        return $employee;
    }

    /**
     * Full update of an employee record.
     */
    public function fullUpdate(Employee $employee, array $validated): Employee
    {
        $identificationData = $this->extractIdentificationData($validated);

        $employee->update($validated + [
            'updated_by' => auth()->user()->name ?? 'system',
        ]);

        if (! empty($identificationData)) {
            $this->upsertPrimaryIdentification($employee, $identificationData);
        }

        $this->invalidateCache();
        $employee->refresh();
        $this->notifyAction('updated', $employee);

        return $employee;
    }

    /**
     * Soft-delete an employee after checking for deletion blockers.
     *
     * @throws DeletionBlockedException
     */
    public function destroy(Employee $employee): void
    {
        $blockers = $employee->getDeletionBlockers();
        if (! empty($blockers)) {
            throw new DeletionBlockedException($blockers, 'Cannot delete employee');
        }

        // Store data before deletion for notification
        $employeeData = (object) [
            'id' => $employee->id,
            'staff_id' => $employee->staff_id,
            'first_name_en' => $employee->first_name_en,
            'last_name_en' => $employee->last_name_en,
        ];

        $employee->delete();

        $this->invalidateCache();
        $this->notifyAction('deleted', $employeeData);
    }

    /**
     * Batch soft-delete employees, returning succeeded/failed lists.
     */
    public function destroyBatch(array $ids): array
    {
        $succeeded = [];
        $failed = [];

        $employees = Employee::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $employee = $employees->get($id);
            if (! $employee) {
                $failed[] = ['id' => $id, 'blockers' => ['Employee not found']];

                continue;
            }

            $blockers = $employee->getDeletionBlockers();
            if (! empty($blockers)) {
                $failed[] = ['id' => $id, 'blockers' => $blockers];

                continue;
            }

            $employee->delete();
            $succeeded[] = [
                'id' => $id,
                'display_name' => $employee->getActivityLogName(),
            ];
        }

        $this->invalidateCache();

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * Import employees from an uploaded Excel file.
     */
    public function uploadEmployeeData(UploadedFile $file): array
    {
        $importId = uniqid('import_', true);
        $userId = auth()->id();
        $import = new EmployeesImport($importId, $userId);

        Excel::import($import, $file);

        $result = Cache::get("import_result_{$importId}", []);
        $processedCount = $result['processed'] ?? 0;
        $errors = $result['errors'] ?? [];
        $warnings = $result['warnings'] ?? [];

        $responseData = ['import_id' => $importId, 'processed_count' => $processedCount];

        if (! empty($errors)) {
            $responseData['errors'] = $errors;
        }
        if (! empty($warnings)) {
            $responseData['warnings'] = $warnings;
        }

        $message = ! empty($errors)
            ? "Import completed with {$processedCount} employees processed and ".count($errors).' errors.'
            : "Import completed successfully. {$processedCount} employees processed.";

        return ['data' => $responseData, 'message' => $message];
    }

    /**
     * Get all employees grouped by organization for tree search.
     */
    public function searchForOrgTree(): array
    {
        $employees = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en', 'status')
            ->with([
                'employment:id,employee_id,department_id,position_id,organization',
                'employment.department:id,name',
                'employment.position:id,title',
            ])
            ->get();

        return $employees->groupBy(fn ($emp) => $emp->employment?->organization ?? 'Unassigned')->map(function ($organizationEmployees, $organization) {
            return [
                'key' => "organization-{$organization}",
                'title' => $organization,
                'value' => "organization-{$organization}",
                'children' => $organizationEmployees->map(function ($emp) {
                    $fullName = $emp->first_name_en;
                    if ($emp->last_name_en && $emp->last_name_en !== '-') {
                        $fullName .= ' '.$emp->last_name_en;
                    }

                    $data = [
                        'key' => "{$emp->id}",
                        'title' => "{$emp->staff_id} - {$fullName}",
                        'status' => $emp->status,
                        'value' => "{$emp->id}",
                        'department_id' => null,
                        'position_id' => null,
                        'employment' => null,
                    ];

                    if ($emp->employment) {
                        $data['department_id'] = $emp->employment->department_id;
                        $data['position_id'] = $emp->employment->position_id;
                        $data['employment'] = [
                            'department' => $emp->employment->department ? [
                                'id' => $emp->employment->department->id,
                                'name' => $emp->employment->department->name,
                            ] : null,
                            'position' => $emp->employment->position ? [
                                'id' => $emp->employment->position->id,
                                'title' => $emp->employment->position->title,
                            ] : null,
                        ];
                    }

                    return $data;
                })->values()->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Retrieve employee(s) by staff ID.
     * Aborts with 404 if no employee found.
     */
    public function showByStaffId(string $staffId)
    {
        $employees = Employee::select([
            'id', 'staff_id', 'initial_en',
            'first_name_en', 'last_name_en', 'gender', 'date_of_birth',
            'status', 'social_security_number', 'tax_number', 'mobile_phone',
        ])
            ->with([
                'employment:id,employee_id,organization,start_date,end_probation_date',
                'employeeEducation:id,employee_id,school_name,degree,start_date,end_date',
            ])
            ->where('staff_id', $staffId)
            ->get();

        if ($employees->isEmpty()) {
            abort(404, "No employee found with staff_id = {$staffId}");
        }

        return $employees;
    }

    /**
     * Filter employees by criteria.
     * Aborts with 404 if no employees found.
     */
    public function filterEmployees(array $validated)
    {
        $query = Employee::query();

        if (! empty($validated['staff_id'])) {
            $query->where('staff_id', 'like', '%'.$validated['staff_id'].'%');
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['organization'])) {
            $query->whereHas('employment', fn ($q) => $q->where('organization', $validated['organization']));
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            abort(404, 'No employees found.');
        }

        return $employees;
    }

    /**
     * Upload a profile picture for an employee.
     */
    public function uploadProfilePicture(Employee $employee, UploadedFile $file): array
    {
        // Delete old profile picture if exists
        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
        }

        $path = $file->store('employee/profile_pictures', 'public');
        $employee->profile_picture = $path;
        $employee->save();

        return [
            'profile_picture' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * Attach a grant item to an employee.
     */
    public function attachGrantItem(array $validated): Employee
    {
        $employee = Employee::findOrFail($validated['employee_id']);
        $grantItem = GrantItem::findOrFail($validated['grant_item_id']);

        $employee->grant_items()->attach($grantItem, [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'payment_method' => $validated['payment_method'],
            'payment_account' => $validated['payment_account'],
            'payment_account_name' => $validated['payment_account_name'],
        ]);

        return $employee;
    }

    /**
     * Update employee basic information.
     */
    public function updateBasicInfo(Employee $employee, array $validated): Employee
    {
        $employee->update($validated);

        $this->invalidateCache();
        $employee->refresh();
        $this->notifyAction('updated', $employee);

        return $employee;
    }

    /**
     * Update employee personal information including identification and languages.
     */
    public function updatePersonalInfo(Employee $employee, array $data, ?array $languages = null): Employee
    {
        DB::beginTransaction();

        try {
            $identificationData = $this->extractIdentificationData($data);

            if (isset($data['employee_identification'])) {
                $legacyData = $data['employee_identification'];
                if (isset($legacyData['id_type'])) {
                    $identificationData['identification_type'] = $legacyData['id_type'];
                }
                if (isset($legacyData['document_number'])) {
                    $identificationData['identification_number'] = $legacyData['document_number'];
                }
            }

            unset($data['employee_identification'], $data['languages']);

            $employee->update($data);

            if (! empty($identificationData)) {
                $this->upsertPrimaryIdentification($employee, $identificationData, isset($identificationData['identification_type']));
            }

            // Update languages
            if ($languages !== null) {
                $employee->employeeLanguages()->delete();
                foreach ($languages as $lang) {
                    $employee->employeeLanguages()->create([
                        'language' => is_array($lang) ? ($lang['language'] ?? '') : $lang,
                        'proficiency_level' => is_array($lang) ? ($lang['proficiency_level'] ?? null) : null,
                        'created_by' => auth()->id() ?? 'system',
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }

        $this->invalidateCache();
        $employee->load('employeeLanguages');
        $this->notifyAction('updated', $employee);

        return $employee;
    }

    /**
     * Update employee family and emergency contact information.
     */
    public function updateFamilyInfo(Employee $employee, array $validated): array
    {
        $fieldMapping = [
            'father_name' => 'father_name',
            'father_occupation' => 'father_occupation',
            'father_phone' => 'father_phone_number',
            'mother_name' => 'mother_name',
            'mother_occupation' => 'mother_occupation',
            'mother_phone' => 'mother_phone_number',
            'spouse_name' => 'spouse_name',
            'spouse_phone_number' => 'spouse_phone_number',
            'emergency_contact_name' => 'emergency_contact_person_name',
            'emergency_contact_relationship' => 'emergency_contact_person_relationship',
            'emergency_contact_phone' => 'emergency_contact_person_phone',
        ];

        $updateData = [];
        foreach ($fieldMapping as $inputKey => $column) {
            if (array_key_exists($inputKey, $validated)) {
                $updateData[$column] = $validated[$inputKey];
            }
        }

        $updateData['updated_by'] = auth()->user()->name ?? 'system';

        if (! empty($updateData)) {
            $employee->update($updateData);
        }

        $this->invalidateCache();
        $this->notifyAction('updated', $employee);

        return $employee->only([
            'father_name', 'father_occupation', 'father_phone_number',
            'mother_name', 'mother_occupation', 'mother_phone_number',
            'spouse_name', 'spouse_phone_number',
            'emergency_contact_person_name',
            'emergency_contact_person_relationship',
            'emergency_contact_person_phone',
        ]);
    }

    /**
     * Update employee bank information.
     */
    public function updateBankInfo(Employee $employee, array $validated): array
    {
        $validated['updated_by'] = auth()->user()->name ?? 'system';
        $employee->update($validated);

        $this->invalidateCache();
        $this->notifyAction('updated', $employee);

        return $employee->only([
            'bank_name', 'bank_branch',
            'bank_account_name', 'bank_account_number',
        ]);
    }

    /**
     * Check the status of an employee import job.
     * Aborts with 404 if import not found.
     */
    public function importStatus(string $importId): array
    {
        $stats = Cache::get("import_{$importId}_stats");

        if (! $stats) {
            abort(404, 'Import not found');
        }

        return $stats;
    }

    /**
     * Get all site records.
     */
    public function getSiteRecords()
    {
        return Site::all();
    }

    /**
     * Transfer an employee between organizations (SMRU <-> BHF).
     * Updates employments.organization (not employees).
     */
    public function transfer(Employee $employee, array $validated): Employee
    {
        $employment = $employee->employment;
        $oldOrganization = $employment?->organization;
        $newOrganization = $validated['new_organization'];

        if ($employment) {
            $employment->update([
                'organization' => $newOrganization,
                'updated_by' => auth()->user()->name ?? 'system',
            ]);
        }

        $employee->logActivity('transferred', [
            'from_organization' => $oldOrganization,
            'to_organization' => $newOrganization,
            'effective_date' => $validated['effective_date'],
            'reason' => $validated['reason'] ?? null,
        ], "Organization transfer: {$oldOrganization} → {$newOrganization}");

        $this->invalidateCache();
        $this->notifyAction('transferred', $employee);

        $employee->refresh();

        return $employee;
    }

    /**
     * Clear employee statistics cache.
     */
    private function invalidateCache(): void
    {
        Cache::forget('employee_statistics');
    }

    /**
     * Send notification for employee action.
     */
    private function notifyAction(string $action, $employee): void
    {
        $performedBy = auth()->user();
        if (! $performedBy) {
            return;
        }

        $this->notificationService->notifyByModule(
            'employees',
            new EmployeeActionNotification($action, $employee, $performedBy, 'employees'),
            $action
        );
    }

    /**
     * Build applied filters array from validated input.
     */
    private function extractIdentificationData(array &$data): array
    {
        $identificationFields = [
            'identification_type',
            'identification_number',
            'identification_issue_date',
            'identification_expiry_date',
        ];

        $identificationData = [];

        foreach ($identificationFields as $field) {
            if (array_key_exists($field, $data)) {
                $identificationData[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        return $identificationData;
    }

    private function upsertPrimaryIdentification(Employee $employee, array $identificationData, bool $requireTypeForCreate = false): void
    {
        $primary = $employee->primaryIdentification;
        $updatedBy = auth()->user()->name ?? 'system';

        if ($primary) {
            $primary->update($identificationData + ['updated_by' => $updatedBy]);
        } elseif (! $requireTypeForCreate || isset($identificationData['identification_type'])) {
            $identificationData['employee_id'] = $employee->id;
            $identificationData['is_primary'] = true;
            $identificationData['created_by'] = $updatedBy;
            $identificationData['updated_by'] = $updatedBy;
            EmployeeIdentification::create($identificationData);
        }
    }

    private function buildAppliedFilters(array $validated): array
    {
        $appliedFilters = [];

        if (! empty($validated['filter_organization'])) {
            $appliedFilters['organization'] = explode(',', $validated['filter_organization']);
        }
        if (! empty($validated['filter_status'])) {
            $appliedFilters['status'] = explode(',', $validated['filter_status']);
        }
        if (! empty($validated['filter_gender'])) {
            $appliedFilters['gender'] = explode(',', $validated['filter_gender']);
        }
        if (! empty($validated['filter_age'])) {
            $appliedFilters['age'] = $validated['filter_age'];
        }
        if (! empty($validated['filter_identification_type'])) {
            $appliedFilters['identification_type'] = explode(',', $validated['filter_identification_type']);
        }
        if (! empty($validated['filter_staff_id'])) {
            $appliedFilters['staff_id'] = $validated['filter_staff_id'];
        }

        return $appliedFilters;
    }
}
