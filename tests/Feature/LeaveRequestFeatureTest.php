<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LeaveRequestFeatureTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    private Employee $employee;

    private LeaveType $leaveType;

    private LeaveBalance $leaveBalance;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create test employee
        $this->employee = Employee::factory()->create([
            'staff_id' => 'EMP001',
            'first_name_en' => 'John',
            'last_name_en' => 'Doe',
            'subsidiary' => 'SMRU',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'status' => 'Local ID',
        ]);

        // Create test leave type
        $this->leaveType = LeaveType::create([
            'name' => 'Annual Leave',
            'default_duration' => 15,
            'description' => 'Annual vacation leave',
            'requires_attachment' => false,
            'created_by' => 'System',
        ]);

        // Create test leave balance
        $this->leaveBalance = LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'total_days' => 15,
            'used_days' => 0,
            'remaining_days' => 15,
            'year' => Carbon::now()->year,
            'created_by' => 'System',
        ]);
    }

    // ==================== MODEL TESTS ====================

    public function test_leave_request_model_has_correct_fillable_fields(): void
    {
        $leaveRequest = new LeaveRequest;
        $fillable = $leaveRequest->getFillable();

        // Test that all required fields are fillable
        $expectedFields = [
            'employee_id',
            'leave_type_id',
            'start_date',
            'end_date',
            'total_days',
            'reason',
            'status',
            'supervisor_approved',
            'supervisor_approved_date',
            'hr_site_admin_approved',
            'hr_site_admin_approved_date',
            'attachment_notes',
            'created_by',
            'updated_by',
        ];

        foreach ($expectedFields as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    public function test_leave_request_model_has_correct_casts(): void
    {
        $leaveRequest = new LeaveRequest;
        $casts = $leaveRequest->getCasts();

        $expectedCasts = [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'decimal:2',
            'supervisor_approved' => 'boolean',
            'supervisor_approved_date' => 'date',
            'hr_site_admin_approved' => 'boolean',
            'hr_site_admin_approved_date' => 'date',
        ];

        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertEquals($expectedCast, $casts[$field], "Field '{$field}' should be cast to '{$expectedCast}'");
        }
    }

    public function test_leave_request_has_employee_relationship(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $this->assertInstanceOf(Employee::class, $leaveRequest->employee);
        $this->assertEquals($this->employee->id, $leaveRequest->employee->id);
    }

    public function test_leave_request_has_leave_type_relationship(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $this->assertInstanceOf(LeaveType::class, $leaveRequest->leaveType);
        $this->assertEquals($this->leaveType->id, $leaveRequest->leaveType->id);
    }

    // ==================== DATABASE SCHEMA TESTS ====================

    public function test_database_schema_has_approval_columns(): void
    {
        // Test leave_requests table has new approval columns
        $this->assertTrue(\Schema::hasColumn('leave_requests', 'supervisor_approved'));
        $this->assertTrue(\Schema::hasColumn('leave_requests', 'supervisor_approved_date'));
        $this->assertTrue(\Schema::hasColumn('leave_requests', 'hr_site_admin_approved'));
        $this->assertTrue(\Schema::hasColumn('leave_requests', 'hr_site_admin_approved_date'));
    }

    public function test_database_schema_has_required_columns(): void
    {
        $requiredColumns = [
            'id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
            'total_days', 'reason', 'status', 'attachment_notes',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(\Schema::hasColumn('leave_requests', $column), "Column '{$column}' should exist in leave_requests table");
        }
    }

    // ==================== FACTORY TESTS ====================

    public function test_can_create_leave_request_using_factory(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $this->assertInstanceOf(LeaveRequest::class, $leaveRequest);
        $this->assertEquals($this->employee->id, $leaveRequest->employee_id);
        $this->assertEquals($this->leaveType->id, $leaveRequest->leave_type_id);
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);
    }

    public function test_can_create_leave_request_with_approval_states(): void
    {
        $pendingRequest = LeaveRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $this->assertEquals('pending', $pendingRequest->status);
        $this->assertFalse($pendingRequest->supervisor_approved);
        $this->assertFalse($pendingRequest->hr_site_admin_approved);

        $approvedRequest = LeaveRequest::factory()->approved()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $this->assertEquals('approved', $approvedRequest->status);
        $this->assertTrue($approvedRequest->supervisor_approved);
        $this->assertTrue($approvedRequest->hr_site_admin_approved);
        $this->assertNotNull($approvedRequest->supervisor_approved_date);
        $this->assertNotNull($approvedRequest->hr_site_admin_approved_date);
    }

    // ==================== BUSINESS LOGIC TESTS ====================

    public function test_can_create_leave_request_with_new_approval_fields(): void
    {
        $leaveRequestData = [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Personal vacation',
            'status' => 'pending',
            'supervisor_approved' => false,
            'hr_site_admin_approved' => false,
        ];

        $leaveRequest = LeaveRequest::create($leaveRequestData);

        $this->assertInstanceOf(LeaveRequest::class, $leaveRequest);
        $this->assertEquals($this->employee->id, $leaveRequest->employee_id);
        $this->assertEquals($this->leaveType->id, $leaveRequest->leave_type_id);
        $this->assertEquals('pending', $leaveRequest->status);
        $this->assertFalse($leaveRequest->supervisor_approved);
        $this->assertFalse($leaveRequest->hr_site_admin_approved);

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'total_days' => 3,
            'status' => 'pending',
            'supervisor_approved' => false,
            'hr_site_admin_approved' => false,
        ]);
    }

    public function test_can_update_leave_request_approval_status(): void
    {
        $leaveRequest = LeaveRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        // Update with approvals
        $leaveRequest->update([
            'status' => 'approved',
            'supervisor_approved' => true,
            'supervisor_approved_date' => Carbon::today()->format('Y-m-d'),
            'hr_site_admin_approved' => true,
            'hr_site_admin_approved_date' => Carbon::today()->format('Y-m-d'),
        ]);

        $leaveRequest->refresh();

        $this->assertEquals('approved', $leaveRequest->status);
        $this->assertTrue($leaveRequest->supervisor_approved);
        $this->assertTrue($leaveRequest->hr_site_admin_approved);
        $this->assertNotNull($leaveRequest->supervisor_approved_date);
        $this->assertNotNull($leaveRequest->hr_site_admin_approved_date);
    }

    public function test_leave_request_statistics_method_works(): void
    {
        // Create some test data
        LeaveRequest::factory()->count(3)->pending()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        LeaveRequest::factory()->count(2)->approved()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        LeaveRequest::factory()->count(1)->declined()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $statistics = LeaveRequest::getStatistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('totalRequests', $statistics);
        $this->assertArrayHasKey('pendingRequests', $statistics);
        $this->assertArrayHasKey('approvedRequests', $statistics);
        $this->assertArrayHasKey('declinedRequests', $statistics);
        $this->assertArrayHasKey('statusBreakdown', $statistics);
        $this->assertArrayHasKey('timeBreakdown', $statistics);
        $this->assertArrayHasKey('leaveTypeBreakdown', $statistics);

        $this->assertEquals(6, $statistics['totalRequests']);
        $this->assertEquals(3, $statistics['pendingRequests']);
        $this->assertEquals(2, $statistics['approvedRequests']);
        $this->assertEquals(1, $statistics['declinedRequests']);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_leave_request_requires_valid_employee_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        LeaveRequest::create([
            'employee_id' => 99999, // Non-existent employee
            'leave_type_id' => $this->leaveType->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
            'total_days' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_leave_request_requires_valid_leave_type_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => 99999, // Non-existent leave type
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
            'total_days' => 2,
            'status' => 'pending',
        ]);
    }

    // ==================== SEARCH AND FILTERING TESTS ====================

    public function test_can_query_leave_requests_by_employee(): void
    {
        $otherEmployee = Employee::factory()->create();

        // Create requests for our test employee
        LeaveRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        // Create requests for other employee
        LeaveRequest::factory()->count(2)->create([
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $requests = LeaveRequest::where('employee_id', $this->employee->id)->get();

        $this->assertCount(3, $requests);
        foreach ($requests as $request) {
            $this->assertEquals($this->employee->id, $request->employee_id);
        }
    }

    public function test_can_query_leave_requests_by_status(): void
    {
        LeaveRequest::factory()->count(2)->pending()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        LeaveRequest::factory()->count(3)->approved()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $pendingRequests = LeaveRequest::where('status', 'pending')->get();
        $approvedRequests = LeaveRequest::where('status', 'approved')->get();

        $this->assertCount(2, $pendingRequests);
        $this->assertCount(3, $approvedRequests);

        foreach ($pendingRequests as $request) {
            $this->assertEquals('pending', $request->status);
        }

        foreach ($approvedRequests as $request) {
            $this->assertEquals('approved', $request->status);
        }
    }

    public function test_can_query_leave_requests_by_approval_status(): void
    {
        LeaveRequest::factory()->count(2)->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'supervisor_approved' => true,
            'hr_site_admin_approved' => false,
        ]);

        LeaveRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'supervisor_approved' => true,
            'hr_site_admin_approved' => true,
        ]);

        $supervisorApprovedRequests = LeaveRequest::where('supervisor_approved', true)->get();
        $hrApprovedRequests = LeaveRequest::where('hr_site_admin_approved', true)->get();
        $fullyApprovedRequests = LeaveRequest::where('supervisor_approved', true)
            ->where('hr_site_admin_approved', true)
            ->get();

        $this->assertCount(5, $supervisorApprovedRequests);
        $this->assertCount(3, $hrApprovedRequests);
        $this->assertCount(3, $fullyApprovedRequests);
    }

    // ==================== RELATIONSHIP TESTS ====================

    public function test_leave_request_loads_employee_relationship(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $leaveRequestWithEmployee = LeaveRequest::with('employee')->find($leaveRequest->id);

        $this->assertTrue($leaveRequestWithEmployee->relationLoaded('employee'));
        $this->assertEquals($this->employee->staff_id, $leaveRequestWithEmployee->employee->staff_id);
        $this->assertEquals($this->employee->first_name_en, $leaveRequestWithEmployee->employee->first_name_en);
    }

    public function test_leave_request_loads_leave_type_relationship(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
        ]);

        $leaveRequestWithType = LeaveRequest::with('leaveType')->find($leaveRequest->id);

        $this->assertTrue($leaveRequestWithType->relationLoaded('leaveType'));
        $this->assertEquals($this->leaveType->name, $leaveRequestWithType->leaveType->name);
        $this->assertEquals($this->leaveType->description, $leaveRequestWithType->leaveType->description);
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function test_leave_request_boolean_fields_are_properly_cast(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'supervisor_approved' => 1, // Integer 1
            'hr_site_admin_approved' => true, // Boolean true
        ]);

        $leaveRequest->refresh();

        $this->assertIsBool($leaveRequest->supervisor_approved);
        $this->assertIsBool($leaveRequest->hr_site_admin_approved);

        $this->assertTrue($leaveRequest->supervisor_approved);
        $this->assertTrue($leaveRequest->hr_site_admin_approved);
    }

    public function test_leave_request_date_fields_are_properly_cast(): void
    {
        $approvalDate = '2024-01-15';

        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'supervisor_approved_date' => $approvalDate,
            'hr_site_admin_approved_date' => $approvalDate,
        ]);

        $leaveRequest->refresh();

        $this->assertInstanceOf(\Carbon\Carbon::class, $leaveRequest->supervisor_approved_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $leaveRequest->hr_site_admin_approved_date);

        $this->assertEquals($approvalDate, $leaveRequest->supervisor_approved_date->format('Y-m-d'));
        $this->assertEquals($approvalDate, $leaveRequest->hr_site_admin_approved_date->format('Y-m-d'));
    }
}
