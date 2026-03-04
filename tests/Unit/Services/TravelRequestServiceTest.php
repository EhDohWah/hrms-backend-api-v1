<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\TravelRequestService;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->service = new TravelRequestService;
});

/**
 * Helper: create standard travel request dependencies (employee, department, position).
 */
function createTravelDeps(array $overrides = []): array
{
    $department = Department::factory()->create($overrides['department'] ?? []);
    $position = Position::factory()->create(array_merge(
        ['department_id' => $department->id],
        $overrides['position'] ?? []
    ));
    $employee = Employee::factory()->create($overrides['employee'] ?? []);

    return compact('employee', 'department', 'position');
}

/**
 * Helper: create a travel request with consistent defaults.
 */
function createTravelRequest(array $deps, array $overrides = []): TravelRequest
{
    $defaults = [
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'destination' => 'Bangkok, Thailand',
        'start_date' => now()->addDays(7)->format('Y-m-d'),
        'to_date' => now()->addDays(14)->format('Y-m-d'),
        'purpose' => 'Business meeting',
        'transportation' => 'air',
        'accommodation' => 'smru_arrangement',
    ];

    return TravelRequest::factory()->create(array_merge($defaults, $overrides));
}

// =========================================================================
// list() tests
// =========================================================================

it('returns paginator with correct default pagination', function () {
    $deps = createTravelDeps();
    createTravelRequest($deps);

    $result = $this->service->list([]);

    expect($result)->toBeInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class)
        ->and($result->perPage())->toBe(10)
        ->and($result->currentPage())->toBe(1);
});

it('applies custom pagination parameters', function () {
    $deps = createTravelDeps();
    for ($i = 0; $i < 5; $i++) {
        createTravelRequest($deps);
    }

    $result = $this->service->list(['per_page' => 2, 'page' => 2]);

    expect($result->perPage())->toBe(2)
        ->and($result->currentPage())->toBe(2)
        ->and($result->total())->toBe(5);
});

it('applies search filter on employee name', function () {
    $deps = createTravelDeps(['employee' => ['first_name_en' => 'UniqueSearchName', 'last_name_en' => 'Tester']]);
    createTravelRequest($deps);

    $otherDeps = createTravelDeps(['employee' => ['first_name_en' => 'Other', 'last_name_en' => 'Person']]);
    createTravelRequest($otherDeps);

    $result = $this->service->list(['search' => 'UniqueSearchName']);

    expect($result->total())->toBe(1);
});

it('applies search filter on staff_id', function () {
    $deps = createTravelDeps(['employee' => ['staff_id' => 'SRCH9999']]);
    createTravelRequest($deps);

    $otherDeps = createTravelDeps(['employee' => ['staff_id' => 'OTHER0001']]);
    createTravelRequest($otherDeps);

    $result = $this->service->list(['search' => 'SRCH9999']);

    expect($result->total())->toBe(1);
});

it('applies department filter', function () {
    $deps = createTravelDeps(['department' => ['name' => 'Engineering']]);
    createTravelRequest($deps);

    $otherDeps = createTravelDeps(['department' => ['name' => 'Marketing']]);
    createTravelRequest($otherDeps);

    $result = $this->service->list(['filter_department' => 'Engineering']);

    expect($result->total())->toBe(1);
});

it('applies destination filter', function () {
    $deps = createTravelDeps();
    createTravelRequest($deps, ['destination' => 'Tokyo, Japan']);
    createTravelRequest($deps, ['destination' => 'Bangkok, Thailand']);

    $result = $this->service->list(['filter_destination' => 'Tokyo']);

    expect($result->total())->toBe(1);
});

it('applies transportation filter', function () {
    $deps = createTravelDeps();
    createTravelRequest($deps, ['transportation' => 'air']);
    createTravelRequest($deps, ['transportation' => 'smru_vehicle']);

    $result = $this->service->list(['filter_transportation' => 'air']);

    expect($result->total())->toBe(1);
});

it('applies default sorting (created_at desc)', function () {
    $deps = createTravelDeps();
    $older = createTravelRequest($deps);
    // Nudge created_at so ordering is deterministic
    $older->update(['created_at' => now()->subDay()]);
    $newer = createTravelRequest($deps);

    $result = $this->service->list([]);
    $items = $result->items();

    expect($items[0]->id)->toBe($newer->id);
});

it('applies sorting by start_date asc', function () {
    $deps = createTravelDeps();
    $later = createTravelRequest($deps, ['start_date' => now()->addDays(30)->format('Y-m-d')]);
    $earlier = createTravelRequest($deps, ['start_date' => now()->addDays(7)->format('Y-m-d')]);

    $result = $this->service->list(['sort_by' => 'start_date', 'sort_order' => 'asc']);
    $items = $result->items();

    expect($items[0]->id)->toBe($earlier->id);
});

// =========================================================================
// buildAppliedFilters() tests
// =========================================================================

it('returns empty array when no filters applied', function () {
    $result = $this->service->buildAppliedFilters([]);

    expect($result)->toBe([]);
});

it('returns correct structure with all filters', function () {
    $result = $this->service->buildAppliedFilters([
        'search' => 'test',
        'filter_department' => 'Engineering,Marketing',
        'filter_destination' => 'Bangkok',
        'filter_transportation' => 'air',
    ]);

    expect($result)->toBe([
        'search' => 'test',
        'department' => ['Engineering', 'Marketing'],
        'destination' => 'Bangkok',
        'transportation' => 'air',
    ]);
});

// =========================================================================
// create() tests
// =========================================================================

it('sets created_by from auth user', function () {
    $deps = createTravelDeps();
    $user = User::factory()->create(['name' => 'TestAdmin']);
    Auth::login($user);

    $travelRequest = $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'destination' => 'Singapore',
        'transportation' => 'air',
        'accommodation' => 'smru_arrangement',
    ]);

    expect($travelRequest->created_by)->toBe('TestAdmin');
});

it('creates record with all validated fields', function () {
    $deps = createTravelDeps();
    $user = User::factory()->create();
    Auth::login($user);

    $travelRequest = $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'destination' => 'London, UK',
        'purpose' => 'Conference',
        'transportation' => 'air',
        'accommodation' => 'self_arrangement',
    ]);

    expect($travelRequest)->toBeInstanceOf(TravelRequest::class)
        ->and($travelRequest->destination)->toBe('London, UK')
        ->and($travelRequest->purpose)->toBe('Conference')
        ->and($travelRequest->transportation->value)->toBe('air')
        ->and($travelRequest->accommodation->value)->toBe('self_arrangement');
});

it('loads relationships after creation', function () {
    $deps = createTravelDeps();
    $user = User::factory()->create();
    Auth::login($user);

    $travelRequest = $this->service->create([
        'employee_id' => $deps['employee']->id,
        'department_id' => $deps['department']->id,
        'position_id' => $deps['position']->id,
        'destination' => 'Tokyo',
        'transportation' => 'air',
        'accommodation' => 'smru_arrangement',
    ]);

    expect($travelRequest->relationLoaded('employee'))->toBeTrue()
        ->and($travelRequest->relationLoaded('department'))->toBeTrue()
        ->and($travelRequest->relationLoaded('position'))->toBeTrue();
});

// =========================================================================
// show() tests
// =========================================================================

it('returns travel request with loaded relations', function () {
    $deps = createTravelDeps();
    $travelRequest = createTravelRequest($deps);

    // Unload relations to test that show() loads them
    $fresh = TravelRequest::find($travelRequest->id);

    $result = $this->service->show($fresh);

    expect($result->relationLoaded('employee'))->toBeTrue()
        ->and($result->relationLoaded('department'))->toBeTrue()
        ->and($result->relationLoaded('position'))->toBeTrue()
        ->and($result->id)->toBe($travelRequest->id);
});

// =========================================================================
// update() tests
// =========================================================================

it('sets updated_by from auth user', function () {
    $deps = createTravelDeps();
    $travelRequest = createTravelRequest($deps);
    $user = User::factory()->create(['name' => 'Updater']);
    Auth::login($user);

    $result = $this->service->update($travelRequest, [
        'destination' => 'Updated Destination',
    ]);

    expect($result->updated_by)->toBe('Updater');
});

it('updates record and loads relationships', function () {
    $deps = createTravelDeps();
    $travelRequest = createTravelRequest($deps, ['destination' => 'Original']);
    $user = User::factory()->create();
    Auth::login($user);

    $result = $this->service->update($travelRequest, [
        'destination' => 'New Destination',
        'purpose' => 'Updated Purpose',
    ]);

    expect($result->destination)->toBe('New Destination')
        ->and($result->purpose)->toBe('Updated Purpose')
        ->and($result->relationLoaded('employee'))->toBeTrue()
        ->and($result->relationLoaded('department'))->toBeTrue()
        ->and($result->relationLoaded('position'))->toBeTrue();
});

// =========================================================================
// delete() tests
// =========================================================================

it('removes travel request record', function () {
    $deps = createTravelDeps();
    $travelRequest = createTravelRequest($deps);
    $id = $travelRequest->id;

    $this->service->delete($travelRequest);

    $this->assertDatabaseMissing('travel_requests', ['id' => $id]);
});

// =========================================================================
// getOptions() tests
// =========================================================================

it('returns transportation and accommodation arrays', function () {
    $options = $this->service->getOptions();

    expect($options)->toHaveKeys(['transportation', 'accommodation'])
        ->and($options['transportation'])->toHaveCount(4)
        ->and($options['accommodation'])->toHaveCount(3)
        ->and($options['transportation'][0])->toBe(['value' => 'smru_vehicle', 'label' => 'SMRU vehicle'])
        ->and($options['accommodation'][0])->toBe(['value' => 'smru_arrangement', 'label' => 'SMRU arrangement']);
});

// =========================================================================
// searchByStaffId() tests
// =========================================================================

it('throws EmployeeNotFoundException for unknown staff ID', function () {
    $this->service->searchByStaffId('NONEXISTENT', []);
})->throws(\App\Exceptions\Employment\EmployeeNotFoundException::class, 'No employee found with staff ID: NONEXISTENT');

it('returns paginated results for valid employee', function () {
    $deps = createTravelDeps(['employee' => ['staff_id' => 'TRAVEL9999']]);
    createTravelRequest($deps);
    createTravelRequest($deps);

    $result = $this->service->searchByStaffId('TRAVEL9999', ['per_page' => 5]);

    expect($result['employee'])->not->toBeNull()
        ->and($result['employee']->staff_id)->toBe('TRAVEL9999')
        ->and($result['paginator'])->not->toBeNull()
        ->and($result['paginator']->total())->toBe(2);
});

it('returns empty paginator for valid employee with no travel requests', function () {
    createTravelDeps(['employee' => ['staff_id' => 'EMPTY0001']]);

    $result = $this->service->searchByStaffId('EMPTY0001', []);

    expect($result['employee'])->not->toBeNull()
        ->and($result['paginator']->isEmpty())->toBeTrue();
});
