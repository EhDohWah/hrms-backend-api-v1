<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TravelRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TravelRequestFeatureTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    private Employee $employee;

    private Department $department;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create test department
        $this->department = Department::factory()->create([
            'name' => 'Information Technology',
            'description' => 'IT Department',
            'is_active' => true,
        ]);

        // Create test position
        $this->position = Position::factory()->create([
            'title' => 'Software Developer',
            'department_id' => $this->department->id,
            'level' => 3,
            'is_manager' => false,
            'is_active' => true,
        ]);

        // Create test employee
        $this->employee = Employee::factory()->create([
            'staff_id' => 'EMP001',
            'first_name_en' => 'John',
            'last_name_en' => 'Doe',
            'organization' => 'SMRU',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'status' => 'Local ID',
        ]);
    }

    // ==================== MODEL TESTS ====================

    public function test_travel_request_model_has_correct_fillable_fields(): void
    {
        $travelRequest = new TravelRequest;
        $fillable = $travelRequest->getFillable();

        // Test that all required fields are fillable
        $expectedFields = [
            'employee_id',
            'department_id',
            'position_id',
            'destination',
            'start_date',
            'to_date',
            'purpose',
            'grant',
            'transportation',
            'transportation_other_text',
            'accommodation',
            'accommodation_other_text',
            'request_by_date',
            'supervisor_approved',
            'supervisor_approved_date',
            'hr_acknowledged',
            'hr_acknowledgement_date',
            'remarks',
            'created_by',
            'updated_by',
        ];

        foreach ($expectedFields as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    public function test_travel_request_model_has_correct_casts(): void
    {
        $travelRequest = new TravelRequest;
        $casts = $travelRequest->getCasts();

        $expectedCasts = [
            'supervisor_approved' => 'boolean',
            'hr_acknowledged' => 'boolean',
            'start_date' => 'date',
            'to_date' => 'date',
            'request_by_date' => 'date',
            'supervisor_approved_date' => 'date',
            'hr_acknowledgement_date' => 'date',
        ];

        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertEquals($expectedCast, $casts[$field], "Field '{$field}' should be cast to '{$expectedCast}'");
        }
    }

    public function test_travel_request_has_employee_relationship(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertInstanceOf(Employee::class, $travelRequest->employee);
        $this->assertEquals($this->employee->id, $travelRequest->employee->id);
    }

    public function test_travel_request_has_department_relationship(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertInstanceOf(Department::class, $travelRequest->department);
        $this->assertEquals($this->department->id, $travelRequest->department->id);
    }

    public function test_travel_request_has_position_relationship(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertInstanceOf(Position::class, $travelRequest->position);
        $this->assertEquals($this->position->id, $travelRequest->position->id);
    }

    // ==================== DATABASE SCHEMA TESTS ====================

    public function test_database_schema_has_approval_columns(): void
    {
        // Test travel_requests table has approval columns
        $this->assertTrue(\Schema::hasColumn('travel_requests', 'supervisor_approved'));
        $this->assertTrue(\Schema::hasColumn('travel_requests', 'supervisor_approved_date'));
        $this->assertTrue(\Schema::hasColumn('travel_requests', 'hr_acknowledged'));
        $this->assertTrue(\Schema::hasColumn('travel_requests', 'hr_acknowledgement_date'));
    }

    public function test_database_schema_has_required_columns(): void
    {
        $requiredColumns = [
            'id', 'employee_id', 'department_id', 'position_id', 'destination',
            'start_date', 'to_date', 'purpose', 'grant', 'transportation',
            'accommodation', 'remarks', 'created_by', 'updated_by',
            'created_at', 'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(\Schema::hasColumn('travel_requests', $column), "Column '{$column}' should exist in travel_requests table");
        }
    }

    // ==================== FACTORY TESTS ====================

    public function test_can_create_travel_request_using_factory(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertInstanceOf(TravelRequest::class, $travelRequest);
        $this->assertEquals($this->employee->id, $travelRequest->employee_id);
        $this->assertEquals($this->department->id, $travelRequest->department_id);
        $this->assertEquals($this->position->id, $travelRequest->position_id);
        $this->assertDatabaseHas('travel_requests', [
            'id' => $travelRequest->id,
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);
    }

    public function test_can_create_travel_request_with_approval_states(): void
    {
        $pendingRequest = TravelRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertFalse($pendingRequest->supervisor_approved);
        $this->assertFalse($pendingRequest->hr_acknowledged);

        $approvedRequest = TravelRequest::factory()->fullyApproved()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertTrue($approvedRequest->supervisor_approved);
        $this->assertTrue($approvedRequest->hr_acknowledged);
        $this->assertNotNull($approvedRequest->supervisor_approved_date);
        $this->assertNotNull($approvedRequest->hr_acknowledgement_date);
    }

    // ==================== BUSINESS LOGIC TESTS ====================

    public function test_can_create_travel_request_with_new_approval_fields(): void
    {
        $travelRequestData = [
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'destination' => 'Bangkok, Thailand',
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'to_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'purpose' => 'Business meeting',
            'grant' => 'Company funded',
            'transportation' => 'air',
            'accommodation' => 'smru_arrangement',
            'supervisor_approved' => false,
            'hr_acknowledged' => false,
            'supervisor_signature' => false,
            'hr_signature' => false,
        ];

        $travelRequest = TravelRequest::create($travelRequestData);

        $this->assertInstanceOf(TravelRequest::class, $travelRequest);
        $this->assertEquals($this->employee->id, $travelRequest->employee_id);
        $this->assertEquals($this->department->id, $travelRequest->department_id);
        $this->assertEquals($this->position->id, $travelRequest->position_id);
        $this->assertEquals('Bangkok, Thailand', $travelRequest->destination);
        $this->assertFalse($travelRequest->supervisor_approved);
        $this->assertFalse($travelRequest->hr_acknowledged);

        $this->assertDatabaseHas('travel_requests', [
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'destination' => 'Bangkok, Thailand',
            'supervisor_approved' => false,
            'hr_acknowledged' => false,
        ]);
    }

    public function test_can_update_travel_request_approval_status(): void
    {
        $travelRequest = TravelRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        // Update with approvals
        $travelRequest->update([
            'supervisor_approved' => true,
            'supervisor_approved_date' => Carbon::today()->format('Y-m-d'),
            'hr_acknowledged' => true,
            'hr_acknowledgement_date' => Carbon::today()->format('Y-m-d'),
        ]);

        $travelRequest->refresh();

        $this->assertTrue($travelRequest->supervisor_approved);
        $this->assertTrue($travelRequest->hr_acknowledged);
        $this->assertNotNull($travelRequest->supervisor_approved_date);
        $this->assertNotNull($travelRequest->hr_acknowledgement_date);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_travel_request_requires_valid_employee_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        TravelRequest::create([
            'employee_id' => 99999, // Non-existent employee
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'destination' => 'Bangkok, Thailand',
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'to_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
        ]);
    }

    public function test_travel_request_requires_valid_department_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        TravelRequest::create([
            'employee_id' => $this->employee->id,
            'department_id' => 99999, // Non-existent department
            'position_id' => $this->position->id,
            'destination' => 'Bangkok, Thailand',
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'to_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
        ]);
    }

    public function test_travel_request_requires_valid_position_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        TravelRequest::create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => 99999, // Non-existent position
            'destination' => 'Bangkok, Thailand',
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'to_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
        ]);
    }

    // ==================== TRANSPORTATION AND ACCOMMODATION TESTS ====================

    public function test_travel_request_transportation_options(): void
    {
        $options = TravelRequest::getTransportationOptions();
        $expectedOptions = ['smru_vehicle', 'public_transportation', 'air', 'other'];

        $this->assertEquals($expectedOptions, $options);
    }

    public function test_travel_request_accommodation_options(): void
    {
        $options = TravelRequest::getAccommodationOptions();
        $expectedOptions = ['smru_arrangement', 'self_arrangement', 'other'];

        $this->assertEquals($expectedOptions, $options);
    }

    public function test_can_create_travel_request_with_other_transportation(): void
    {
        $travelRequest = TravelRequest::factory()->otherTransportation('Private car rental')->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertEquals('other', $travelRequest->transportation);
        $this->assertEquals('Private car rental', $travelRequest->transportation_other_text);
    }

    public function test_can_create_travel_request_with_other_accommodation(): void
    {
        $travelRequest = TravelRequest::factory()->otherAccommodation('Family guest house')->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertEquals('other', $travelRequest->accommodation);
        $this->assertEquals('Family guest house', $travelRequest->accommodation_other_text);
    }

    // ==================== SEARCH AND FILTERING TESTS ====================

    public function test_can_query_travel_requests_by_employee(): void
    {
        $otherEmployee = Employee::factory()->create();

        // Create requests for our test employee
        TravelRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        // Create requests for other employee
        TravelRequest::factory()->count(2)->create([
            'employee_id' => $otherEmployee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $requests = TravelRequest::where('employee_id', $this->employee->id)->get();

        $this->assertCount(3, $requests);
        foreach ($requests as $request) {
            $this->assertEquals($this->employee->id, $request->employee_id);
        }
    }

    public function test_can_query_travel_requests_by_department(): void
    {
        $otherDepartment = Department::factory()->create();

        TravelRequest::factory()->count(2)->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        TravelRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'department_id' => $otherDepartment->id,
            'position_id' => $this->position->id,
        ]);

        $requests = TravelRequest::where('department_id', $this->department->id)->get();

        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertEquals($this->department->id, $request->department_id);
        }
    }

    public function test_can_query_travel_requests_by_approval_status(): void
    {
        TravelRequest::factory()->count(2)->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'supervisor_approved' => true,
            'hr_acknowledged' => false,
        ]);

        TravelRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'supervisor_approved' => true,
            'hr_acknowledged' => true,
        ]);

        $supervisorApprovedRequests = TravelRequest::where('supervisor_approved', true)->get();
        $hrAcknowledgedRequests = TravelRequest::where('hr_acknowledged', true)->get();
        $fullyApprovedRequests = TravelRequest::where('supervisor_approved', true)
            ->where('hr_acknowledged', true)
            ->get();

        $this->assertCount(5, $supervisorApprovedRequests);
        $this->assertCount(3, $hrAcknowledgedRequests);
        $this->assertCount(3, $fullyApprovedRequests);
    }

    public function test_can_query_travel_requests_by_destination(): void
    {
        TravelRequest::factory()->count(2)->toDestination('Bangkok, Thailand')->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        TravelRequest::factory()->count(3)->toDestination('Singapore')->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $bangkokRequests = TravelRequest::where('destination', 'Bangkok, Thailand')->get();
        $singaporeRequests = TravelRequest::where('destination', 'Singapore')->get();

        $this->assertCount(2, $bangkokRequests);
        $this->assertCount(3, $singaporeRequests);
    }

    public function test_can_query_travel_requests_by_transportation(): void
    {
        TravelRequest::factory()->count(2)->airTransportation()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        TravelRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'transportation' => 'smru_vehicle',
        ]);

        $airRequests = TravelRequest::where('transportation', 'air')->get();
        $vehicleRequests = TravelRequest::where('transportation', 'smru_vehicle')->get();

        $this->assertCount(2, $airRequests);
        $this->assertCount(3, $vehicleRequests);
    }

    // ==================== RELATIONSHIP TESTS ====================

    public function test_travel_request_loads_employee_relationship(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $travelRequestWithEmployee = TravelRequest::with('employee')->find($travelRequest->id);

        $this->assertTrue($travelRequestWithEmployee->relationLoaded('employee'));
        $this->assertEquals($this->employee->staff_id, $travelRequestWithEmployee->employee->staff_id);
        $this->assertEquals($this->employee->first_name_en, $travelRequestWithEmployee->employee->first_name_en);
    }

    public function test_travel_request_loads_department_relationship(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $travelRequestWithDepartment = TravelRequest::with('department')->find($travelRequest->id);

        $this->assertTrue($travelRequestWithDepartment->relationLoaded('department'));
        $this->assertEquals($this->department->name, $travelRequestWithDepartment->department->name);
    }

    public function test_travel_request_loads_position_relationship(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $travelRequestWithPosition = TravelRequest::with('position')->find($travelRequest->id);

        $this->assertTrue($travelRequestWithPosition->relationLoaded('position'));
        $this->assertEquals($this->position->title, $travelRequestWithPosition->position->title);
    }

    public function test_travel_request_scope_with_relations(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $travelRequestWithRelations = TravelRequest::withRelations()->find($travelRequest->id);

        $this->assertTrue($travelRequestWithRelations->relationLoaded('employee'));
        $this->assertTrue($travelRequestWithRelations->relationLoaded('department'));
        $this->assertTrue($travelRequestWithRelations->relationLoaded('position'));
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function test_travel_request_boolean_fields_are_properly_cast(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'supervisor_approved' => 1, // Integer 1
            'hr_acknowledged' => 0, // Integer 0
        ]);

        $travelRequest->refresh();

        $this->assertIsBool($travelRequest->supervisor_approved);
        $this->assertIsBool($travelRequest->hr_acknowledged);
        $this->assertTrue($travelRequest->supervisor_approved);
        $this->assertFalse($travelRequest->hr_acknowledged);
    }

    public function test_travel_request_date_fields_are_properly_cast(): void
    {
        $startDate = '2024-06-15';
        $endDate = '2024-06-20';
        $approvalDate = '2024-01-15';

        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'start_date' => $startDate,
            'to_date' => $endDate,
            'request_by_date' => $approvalDate,
            'supervisor_approved_date' => $approvalDate,
            'hr_acknowledgement_date' => $approvalDate,
        ]);

        $travelRequest->refresh();

        $this->assertInstanceOf(\Carbon\Carbon::class, $travelRequest->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $travelRequest->to_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $travelRequest->request_by_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $travelRequest->supervisor_approved_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $travelRequest->hr_acknowledgement_date);
        $this->assertEquals($startDate, $travelRequest->start_date->format('Y-m-d'));
        $this->assertEquals($endDate, $travelRequest->to_date->format('Y-m-d'));
        $this->assertEquals($approvalDate, $travelRequest->request_by_date->format('Y-m-d'));
        $this->assertEquals($approvalDate, $travelRequest->supervisor_approved_date->format('Y-m-d'));
        $this->assertEquals($approvalDate, $travelRequest->hr_acknowledgement_date->format('Y-m-d'));
    }

    // ==================== FACTORY CONFIGURATION TESTS ====================

    public function test_travel_request_factory_creates_valid_dates(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $this->assertNotNull($travelRequest->start_date);
        $this->assertNotNull($travelRequest->to_date);
        $this->assertGreaterThanOrEqual($travelRequest->start_date, $travelRequest->to_date);
    }

    public function test_travel_request_factory_creates_valid_transportation_options(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $validOptions = ['smru_vehicle', 'public_transportation', 'air', 'other'];
        $this->assertContains($travelRequest->transportation, $validOptions);
    }

    public function test_travel_request_factory_creates_valid_accommodation_options(): void
    {
        $travelRequest = TravelRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        $validOptions = ['smru_arrangement', 'self_arrangement', 'other'];
        $this->assertContains($travelRequest->accommodation, $validOptions);
    }
}
