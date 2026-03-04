<?php

use App\DTOs\EmploymentListResult;
use App\DTOs\EmploymentUpdateResult;
use App\Exceptions\Employment\ActiveEmploymentExistsException;
use App\Exceptions\Employment\EmployeeNotFoundException;
use App\Exceptions\Employment\InvalidDateConstraintException;
use App\Exceptions\Employment\InvalidDepartmentPositionException;
use App\Exceptions\Employment\ProbationTransitionFailedException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Position;
use App\Models\ProbationRecord;
use App\Models\Site;
use App\Services\CacheManagerService;
use App\Services\EmploymentService;
use App\Services\ProbationRecordService;
use App\Services\ProbationTransitionService;
use Carbon\Carbon;

beforeEach(function () {
    $this->mockProbationTransitionService = Mockery::mock(ProbationTransitionService::class);
    $this->mockProbationRecordService = Mockery::mock(ProbationRecordService::class);
    $this->mockCacheManager = Mockery::mock(CacheManagerService::class);

    // Default cache mock expectations (allow any calls)
    $this->mockCacheManager->shouldReceive('clearModelCaches')->andReturnNull();
    $this->mockCacheManager->shouldReceive('clearListCaches')->andReturnNull();
    $this->mockCacheManager->shouldReceive('clearReportCaches')->andReturnNull();
    $this->mockCacheManager->shouldReceive('generateKey')->andReturn('test_cache_key');
    $this->mockCacheManager->shouldReceive('remember')->andReturnUsing(
        fn ($key, $callback) => $callback()
    );

    $this->service = new EmploymentService(
        $this->mockProbationTransitionService,
        $this->mockProbationRecordService,
        $this->mockCacheManager,
    );
});

afterEach(function () {
    Mockery::close();
});

/**
 * Helper: create standard employment dependencies (employee, department, position, site).
 */
function createEmploymentDeps(array $overrides = []): array
{
    $employee = Employee::factory()->create($overrides['employee'] ?? []);
    $department = Department::factory()->create($overrides['department'] ?? []);
    $position = Position::factory()->create(array_merge(
        ['department_id' => $department->id],
        $overrides['position'] ?? []
    ));
    $site = Site::factory()->create($overrides['site'] ?? []);

    return compact('employee', 'department', 'position', 'site');
}

/**
 * Helper: create an employment via factory with consistent dates.
 * Observer validates: pass_probation_date > start_date, within 6 months.
 */
function createEmployment(array $deps, array $overrides = []): Employment
{
    $defaults = [
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'site_id' => $deps['site']->id,
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
        'pass_probation_salary' => 50000,
        'probation_salary' => 40000,
    ];

    return Employment::factory()->create(array_merge($defaults, $overrides));
}

// =========================================================================
// create() tests
// =========================================================================

it('auto-calculates pass_probation_date to 3 months from start_date', function () {
    $deps = createEmploymentDeps();

    $this->mockProbationRecordService
        ->shouldReceive('createInitialRecord')
        ->once()
        ->andReturn(new ProbationRecord);

    $employment = $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'site_id' => $deps['site']->id,
        'start_date' => '2025-06-01',
        'pass_probation_salary' => 50000,
        'probation_salary' => 40000,
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
    ]);

    expect($employment->pass_probation_date->format('Y-m-d'))->toBe('2025-09-01');
});

it('respects explicit pass_probation_date', function () {
    $deps = createEmploymentDeps();

    $this->mockProbationRecordService
        ->shouldReceive('createInitialRecord')
        ->once()
        ->andReturn(new ProbationRecord);

    $employment = $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'site_id' => $deps['site']->id,
        'start_date' => '2025-06-01',
        'pass_probation_date' => '2025-09-15',
        'pass_probation_salary' => 50000,
        'probation_salary' => 40000,
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
    ]);

    expect($employment->pass_probation_date->format('Y-m-d'))->toBe('2025-09-15');
});

it('creates initial probation record on create', function () {
    $deps = createEmploymentDeps();

    $this->mockProbationRecordService
        ->shouldReceive('createInitialRecord')
        ->once()
        ->withArgs(fn (Employment $emp) => $emp->pass_probation_date !== null)
        ->andReturn(new ProbationRecord);

    $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'site_id' => $deps['site']->id,
        'start_date' => '2025-06-01',
        'pass_probation_salary' => 50000,
        'probation_salary' => 40000,
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
    ]);
});

it('throws ActiveEmploymentExistsException when employee has active employment', function () {
    $deps = createEmploymentDeps();

    // Create an active employment (start_date in the past, no end date)
    createEmployment($deps, [
        'start_date' => Carbon::today()->subMonths(2)->format('Y-m-d'),
        'pass_probation_date' => Carbon::today()->addMonth()->format('Y-m-d'),
        'end_probation_date' => null,
    ]);

    $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'site_id' => $deps['site']->id,
        'start_date' => Carbon::today()->subDay()->format('Y-m-d'),
        'pass_probation_salary' => 50000,
        'probation_salary' => 40000,
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
    ]);
})->throws(ActiveEmploymentExistsException::class);

it('returns employment with loaded relationships on create', function () {
    $deps = createEmploymentDeps();

    $this->mockProbationRecordService
        ->shouldReceive('createInitialRecord')
        ->once()
        ->andReturn(new ProbationRecord);

    $employment = $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'site_id' => $deps['site']->id,
        'start_date' => '2025-06-01',
        'pass_probation_salary' => 50000,
        'probation_salary' => 40000,
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
    ]);

    expect($employment->relationLoaded('employee'))->toBeTrue()
        ->and($employment->relationLoaded('department'))->toBeTrue()
        ->and($employment->relationLoaded('position'))->toBeTrue()
        ->and($employment->relationLoaded('site'))->toBeTrue();
});

// =========================================================================
// update() tests
// =========================================================================

it('recalculates pass_probation_date when start_date changes', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleProbationExtension')
        ->once();

    $result = $this->service->update($employment, [
        'start_date' => '2025-03-01',
    ]);

    expect($result)->toBeInstanceOf(EmploymentUpdateResult::class)
        ->and($result->employment->pass_probation_date->format('Y-m-d'))->toBe('2025-06-01')
        ->and($result->earlyTermination)->toBeFalse();
});

it('throws InvalidDepartmentPositionException for mismatched position', function () {
    $department1 = Department::factory()->create();
    $department2 = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department1->id]);
    $employee = Employee::factory()->create();
    $site = Site::factory()->create();

    $employment = createEmployment([
        'employee' => $employee,
        'department' => $department1,
        'position' => $position,
        'site' => $site,
    ]);

    $this->service->update($employment, [
        'department_id' => $department2->id,
        'position_id' => $position->id,
    ]);
})->throws(InvalidDepartmentPositionException::class);

it('throws InvalidDateConstraintException when end_probation_date is before start_date', function () {
    $deps = createEmploymentDeps();
    $employment = createEmployment($deps);

    $this->service->update($employment, [
        'start_date' => '2025-06-01',
        'end_probation_date' => '2025-05-01',
    ]);
})->throws(InvalidDateConstraintException::class);

it('detects and handles probation extension on update', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleProbationExtension')
        ->once()
        ->withArgs(fn (Employment $emp, $oldDate, $newDate) => $newDate === '2025-05-01');

    $result = $this->service->update($employment, [
        'pass_probation_date' => '2025-05-01',
    ]);

    expect($result)->toBeInstanceOf(EmploymentUpdateResult::class)
        ->and($result->earlyTermination)->toBeFalse();
});

it('detects and handles early termination on update', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleEarlyTermination')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Early termination handled']);

    $result = $this->service->update($employment, [
        'end_probation_date' => '2025-03-01',
    ]);

    expect($result)->toBeInstanceOf(EmploymentUpdateResult::class)
        ->and($result->earlyTermination)->toBeTrue()
        ->and($result->probationResult)->not->toBeNull();
});

// =========================================================================
// completeProbation() tests
// =========================================================================

it('throws ProbationTransitionFailedException on completion failure', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleProbationCompletion')
        ->once()
        ->andReturn(['success' => false, 'message' => 'Probation already completed']);

    $this->service->completeProbation($employment);
})->throws(ProbationTransitionFailedException::class, 'Probation already completed');

it('returns employment and allocations on successful completion', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleProbationCompletion')
        ->once()
        ->andReturn([
            'success' => true,
            'message' => 'Completed',
            'employment' => $employment->load('employeeFundingAllocations'),
        ]);

    $result = $this->service->completeProbation($employment);

    expect($result)
        ->toHaveKey('employment')
        ->toHaveKey('updated_allocations');
});

// =========================================================================
// list() tests
// =========================================================================

it('returns EmploymentListResult with filters', function () {
    $result = $this->service->list([
        'per_page' => 25,
        'sort_by' => 'start_date',
        'sort_order' => 'asc',
        'filter_organization' => 'SMRU',
    ]);

    expect($result)->toBeInstanceOf(EmploymentListResult::class)
        ->and($result->appliedFilters['organization'])->toBe(['SMRU']);
});

it('defaults to 10 per page and start_date desc', function () {
    $result = $this->service->list([]);

    expect($result)->toBeInstanceOf(EmploymentListResult::class)
        ->and($result->paginator->perPage())->toBe(10);
});

// =========================================================================
// searchByStaffId() tests
// =========================================================================

it('throws EmployeeNotFoundException for unknown staff ID', function () {
    $this->service->searchByStaffId('NONEXISTENT', false);
})->throws(EmployeeNotFoundException::class);

it('returns employee data for known staff ID', function () {
    $deps = createEmploymentDeps(['employee' => ['staff_id' => 'EMP9999']]);

    createEmployment($deps, [
        'start_date' => Carbon::today()->subMonths(2)->format('Y-m-d'),
        'pass_probation_date' => Carbon::today()->addMonth()->format('Y-m-d'),
        'end_probation_date' => null,
    ]);

    $result = $this->service->searchByStaffId('EMP9999', false);

    expect($result)->toHaveKeys(['employments', 'employee_summary', 'statistics'])
        ->and($result)->not->toHaveKey('found')
        ->and($result['employee_summary']['staff_id'])->toBe('EMP9999')
        ->and($result['statistics']['total_employments'])->toBe(1)
        ->and($result['statistics']['active_employments'])->toBe(1);
});

// =========================================================================
// getGlobalBenefitPercentages() tests
// =========================================================================

it('returns all three benefit percentages', function () {
    $result = $this->service->getGlobalBenefitPercentages();

    expect($result)
        ->toHaveKey('health_welfare_percentage')
        ->toHaveKey('pvd_percentage')
        ->toHaveKey('saving_fund_percentage');
});

// =========================================================================
// show() tests
// =========================================================================

it('loads relations and appends benefit percentages on show', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $result = $this->service->show($employment);

    expect($result->relationLoaded('employee'))->toBeTrue()
        ->and($result->relationLoaded('department'))->toBeTrue()
        ->and($result->relationLoaded('position'))->toBeTrue()
        ->and($result->relationLoaded('site'))->toBeTrue();

    // Benefit percentages are dynamically appended as attributes (may be null if no settings seeded)
    $attributes = $result->getAttributes();
    expect($attributes)->toHaveKey('health_welfare_percentage')
        ->and($attributes)->toHaveKey('pvd_percentage')
        ->and($attributes)->toHaveKey('saving_fund_percentage');
});

// =========================================================================
// delete() tests
// =========================================================================

it('removes employment record on delete', function () {
    $deps = createEmploymentDeps();

    // Create without events to avoid history record FK constraint
    $employment = Employment::withoutEvents(function () use ($deps) {
        return Employment::factory()->create([
            'employee_id' => $deps['employee']->id,
            'department_id' => $deps['department']->id,
            'position_id' => $deps['position']->id,
            'site_id' => $deps['site']->id,
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'pass_probation_salary' => 50000,
            'probation_salary' => 40000,
        ]);
    });

    $employmentId = $employment->id;

    $this->service->delete($employment);

    $this->assertDatabaseMissing('employments', ['id' => $employmentId]);
});

// =========================================================================
// updateProbationStatus() tests
// =========================================================================

it('throws ProbationTransitionFailedException on status update failure', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleProbationCompletion')
        ->once()
        ->andReturn(['success' => false, 'message' => 'Cannot update status']);

    $this->service->updateProbationStatus($employment, [
        'action' => 'passed',
    ]);
})->throws(ProbationTransitionFailedException::class);

it('returns history and message on successful probation status update', function () {
    $deps = createEmploymentDeps();

    $employment = createEmployment($deps, [
        'start_date' => '2025-01-01',
        'pass_probation_date' => '2025-04-01',
    ]);

    $this->mockProbationTransitionService
        ->shouldReceive('handleManualProbationFailure')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Failed']);

    $this->mockProbationRecordService
        ->shouldReceive('getHistory')
        ->once()
        ->andReturn(['records' => []]);

    $result = $this->service->updateProbationStatus($employment, [
        'action' => 'failed',
        'reason' => 'Performance issues',
    ]);

    expect($result)
        ->toHaveKey('employment')
        ->toHaveKey('probation_history')
        ->toHaveKey('message')
        ->and($result['message'])->toBe('Probation marked as failed successfully.');
});
