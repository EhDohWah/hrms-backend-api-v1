<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\OrgFundedAllocation;
use App\Models\Position;
use App\Models\PositionSlot;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "CREATING COMPREHENSIVE TEST DATA\n";
echo "========================================\n\n";

try {
    DB::beginTransaction();

    // ============================================================================
    // 1. CREATE GRANTS
    // ============================================================================
    echo "1. Creating Grants...\n";

    $grant1 = Grant::create([
        'name' => 'SMRU Research Grant 2025',
        'code' => 'SRG-2025-001',
        'subsidiary' => 'SMRU',
        'description' => 'Research Grant for SMRU 2025',
        'end_date' => '2025-12-31',
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Grant: {$grant1->name} (ID: {$grant1->id})\n";

    $grant2 = Grant::create([
        'name' => 'BHF Health Initiative 2025',
        'code' => 'BHF-2025-002',
        'subsidiary' => 'BHF',
        'description' => 'Health Initiative Grant for BHF 2025',
        'end_date' => '2025-12-31',
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Grant: {$grant2->name} (ID: {$grant2->id})\n\n";

    // ============================================================================
    // 2. CREATE GRANT ITEMS
    // ============================================================================
    echo "2. Creating Grant Items...\n";

    $grantItem1 = GrantItem::create([
        'grant_id' => $grant1->id,
        'grant_position' => 'Senior Researcher',
        'grant_position_number' => 3,
        'grant_salary' => 45000,
        'grant_benefit' => 5000,
        'budgetline_code' => 'SRG-SR-001',
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Grant Item: {$grantItem1->grant_position} (ID: {$grantItem1->id})\n";

    $grantItem2 = GrantItem::create([
        'grant_id' => $grant1->id,
        'grant_position' => 'Research Assistant',
        'grant_position_number' => 5,
        'grant_salary' => 25000,
        'grant_benefit' => 3000,
        'budgetline_code' => 'SRG-RA-002',
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Grant Item: {$grantItem2->grant_position} (ID: {$grantItem2->id})\n";

    $grantItem3 = GrantItem::create([
        'grant_id' => $grant2->id,
        'grant_position' => 'Medical Coordinator',
        'grant_position_number' => 2,
        'grant_salary' => 35000,
        'grant_benefit' => 4000,
        'budgetline_code' => 'BHF-MC-001',
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Grant Item: {$grantItem3->grant_position} (ID: {$grantItem3->id})\n\n";

    // ============================================================================
    // 3. CREATE POSITION SLOTS
    // ============================================================================
    echo "3. Creating Position Slots...\n";

    $slot1 = PositionSlot::create([
        'grant_item_id' => $grantItem1->id,
        'slot_number' => 1,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Position Slot #1 for {$grantItem1->grant_position}\n";

    $slot2 = PositionSlot::create([
        'grant_item_id' => $grantItem2->id,
        'slot_number' => 1,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Position Slot #1 for {$grantItem2->grant_position}\n";

    $slot3 = PositionSlot::create([
        'grant_item_id' => $grantItem3->id,
        'slot_number' => 1,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Position Slot #1 for {$grantItem3->grant_position}\n\n";

    // ============================================================================
    // 4. GET REFERENCE DATA
    // ============================================================================
    echo "4. Getting Reference Data...\n";

    $department = Department::first();
    $position = Position::first();
    $workLocation = WorkLocation::first();

    echo "   ✓ Department: {$department->name} (ID: {$department->id})\n";
    echo "   ✓ Position: {$position->title} (ID: {$position->id})\n";
    echo "   ✓ Work Location: {$workLocation->name} (ID: {$workLocation->id})\n\n";

    // ============================================================================
    // 5. CREATE EMPLOYEES WITH DIFFERENT PROBATION SCENARIOS
    // ============================================================================
    echo "5. Creating Employees with Different Probation Scenarios...\n\n";

    // SCENARIO 1: Employee completing probation TODAY
    echo "   SCENARIO 1: Employee Completing Probation Today\n";
    $employee1 = Employee::create([
        'staff_id' => 'EMP-2025-001',
        'first_name_en' => 'John',
        'last_name_en' => 'Doe',
        'subsidiary' => 'SMRU',
        'gender' => 'Male',
        'date_of_birth' => '1990-01-15',
        'status' => true,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Employee: {$employee1->staff_id} - {$employee1->first_name_en} {$employee1->last_name_en}\n";

    $employment1 = Employment::create([
        'employee_id' => $employee1->id,
        'pay_method' => 'Transferred to bank',
        'start_date' => Carbon::today()->subMonths(3),
        'pass_probation_date' => Carbon::today(), // TODAY!
        'end_date' => null,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'work_location_id' => $workLocation->id,
        'probation_salary' => 20000,
        'pass_probation_salary' => 25000,
        'status' => true,
        // NOTE: probation_status removed - tracked in probation_records table
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
        // NOTE: Benefit percentages are managed globally in benefit_settings table
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Employment (ID: {$employment1->id})\n";
    echo "     - Pass Probation Date: {$employment1->pass_probation_date->format('Y-m-d')}\n";
    echo "     - Probation Status: Tracked in probation_records table\n";

    $allocation1 = EmployeeFundingAllocation::create([
        'employee_id' => $employee1->id,
        'employment_id' => $employment1->id,
        'position_slot_id' => $slot1->id,
        'fte' => 1.00, // 100%
        'allocation_type' => 'grant',
        'allocated_amount' => 20000, // probation_salary
        'salary_type' => 'probation_salary',
        'status' => 'active',
        'start_date' => $employment1->start_date,
        'end_date' => null,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Funding Allocation (ID: {$allocation1->id})\n";
    echo "     - Status: {$allocation1->status}\n";
    echo "     - Salary Type: {$allocation1->salary_type}\n";
    echo "     - Allocated Amount: {$allocation1->allocated_amount}\n\n";

    // SCENARIO 2: Employee will be terminated early (during probation)
    echo "   SCENARIO 2: Employee for Early Termination Test\n";
    $employee2 = Employee::create([
        'staff_id' => 'EMP-2025-002',
        'first_name_en' => 'Jane',
        'last_name_en' => 'Smith',
        'subsidiary' => 'SMRU',
        'gender' => 'Female',
        'date_of_birth' => '1992-05-20',
        'status' => true,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Employee: {$employee2->staff_id} - {$employee2->first_name_en} {$employee2->last_name_en}\n";

    $employment2 = Employment::create([
        'employee_id' => $employee2->id,
        'pay_method' => 'Transferred to bank',
        'start_date' => Carbon::today()->subMonths(2),
        'pass_probation_date' => Carbon::today()->addMonth(), // 1 month from now
        'end_date' => null, // Will be set later
        'department_id' => $department->id,
        'position_id' => $position->id,
        'work_location_id' => $workLocation->id,
        'probation_salary' => 18000,
        'pass_probation_salary' => 22000,
        'status' => true,
        // NOTE: probation_status removed - tracked in probation_records table
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
        // NOTE: Benefit percentages are managed globally in benefit_settings table
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Employment (ID: {$employment2->id})\n";
    echo "     - Pass Probation Date: {$employment2->pass_probation_date->format('Y-m-d')}\n";
    echo "     - Probation Status: Tracked in probation_records table\n";

    $allocation2 = EmployeeFundingAllocation::create([
        'employee_id' => $employee2->id,
        'employment_id' => $employment2->id,
        'position_slot_id' => $slot2->id,
        'fte' => 1.00, // 100%
        'allocation_type' => 'grant',
        'allocated_amount' => 18000,
        'salary_type' => 'probation_salary',
        'status' => 'active',
        'start_date' => $employment2->start_date,
        'end_date' => null,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Funding Allocation (ID: {$allocation2->id})\n";
    echo "     - Status: {$allocation2->status}\n";
    echo "     - Salary Type: {$allocation2->salary_type}\n\n";

    // SCENARIO 3: Employee for probation extension test
    echo "   SCENARIO 3: Employee for Probation Extension Test\n";
    $employee3 = Employee::create([
        'staff_id' => 'EMP-2025-003',
        'first_name_en' => 'Michael',
        'last_name_en' => 'Johnson',
        'subsidiary' => 'BHF',
        'gender' => 'Male',
        'date_of_birth' => '1988-08-10',
        'status' => true,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Employee: {$employee3->staff_id} - {$employee3->first_name_en} {$employee3->last_name_en}\n";

    $employment3 = Employment::create([
        'employee_id' => $employee3->id,
        'pay_method' => 'Transferred to bank',
        'start_date' => Carbon::today()->subMonths(2),
        'pass_probation_date' => Carbon::today()->addWeek(), // 1 week from now
        'end_date' => null,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'work_location_id' => $workLocation->id,
        'probation_salary' => 30000,
        'pass_probation_salary' => 35000,
        'status' => true,
        // NOTE: probation_status removed - tracked in probation_records table
        'health_welfare' => true,
        'pvd' => true,
        'saving_fund' => false,
        // NOTE: Benefit percentages are managed globally in benefit_settings table
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Employment (ID: {$employment3->id})\n";
    echo "     - Pass Probation Date: {$employment3->pass_probation_date->format('Y-m-d')}\n";
    echo "     - Probation Status: Tracked in probation_records table\n";

    $allocation3a = EmployeeFundingAllocation::create([
        'employee_id' => $employee3->id,
        'employment_id' => $employment3->id,
        'position_slot_id' => $slot3->id,
        'fte' => 0.60, // 60%
        'allocation_type' => 'grant',
        'allocated_amount' => 18000, // 30000 * 0.60
        'salary_type' => 'probation_salary',
        'status' => 'active',
        'start_date' => $employment3->start_date,
        'end_date' => null,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Funding Allocation #1 (ID: {$allocation3a->id}) - 60% Grant\n";

    // Create OrgFundedAllocation record first
    $orgFunded1 = OrgFundedAllocation::create([
        'grant_id' => $grant2->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'description' => 'General Fund - BHF',
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created OrgFundedAllocation (ID: {$orgFunded1->id}) - General Fund\n";

    $allocation3b = EmployeeFundingAllocation::create([
        'employee_id' => $employee3->id,
        'employment_id' => $employment3->id,
        'position_slot_id' => null,
        'org_funded_id' => $orgFunded1->id, // Now properly references the org_funded_allocation
        'fte' => 0.40, // 40%
        'allocation_type' => 'org_funded',
        'allocated_amount' => 12000, // 30000 * 0.40
        'salary_type' => 'probation_salary',
        'status' => 'active',
        'start_date' => $employment3->start_date,
        'end_date' => null,
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "   ✓ Created Funding Allocation #2 (ID: {$allocation3b->id}) - 40% Org Funded\n\n";

    DB::commit();

    echo "========================================\n";
    echo "TEST DATA CREATION COMPLETED!\n";
    echo "========================================\n\n";

    echo "SUMMARY:\n";
    echo "--------\n";
    echo "✓ Grants Created: 2\n";
    echo "✓ Grant Items Created: 3\n";
    echo "✓ Position Slots Created: 3\n";
    echo "✓ Org Funded Allocations Created: 1\n";
    echo "✓ Employees Created: 3\n";
    echo "✓ Employments Created: 3\n";
    echo "✓ Funding Allocations Created: 4\n\n";

    echo "TEST SCENARIOS READY:\n";
    echo "---------------------\n";
    echo "1. Employee #1 (EMP-2025-001) - Probation completes TODAY\n";
    echo "   - Ready for automatic transition test\n";
    echo "   - Run: php artisan employment:process-probation-transitions\n\n";

    echo "2. Employee #2 (EMP-2025-002) - Early Termination Test\n";
    echo "   - Update end_date to before pass_probation_date via API\n";
    echo "   - Should mark allocations as 'terminated'\n\n";

    echo "3. Employee #3 (EMP-2025-003) - Probation Extension Test\n";
    echo "   - Update pass_probation_date to later date via API\n";
    echo "   - Should set probation_status to 'extended'\n\n";

    // Store IDs for easy reference
    echo "IMPORTANT IDS FOR TESTING:\n";
    echo "--------------------------\n";
    echo "Employment #1 ID: {$employment1->id}\n";
    echo "Employment #2 ID: {$employment2->id}\n";
    echo "Employment #3 ID: {$employment3->id}\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: ".$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
    exit(1);
}
