# Database Seeder Update Instructions

## Critical Issues Found & Required Updates

### 1. **ProbationAllocationSeeder.php** - CRITICAL ERRORS ❌

**Problems:**
- References deleted model: `WorkLocation` (line 16, 48-51)
- References deleted model: `OrgFundedAllocation` (line 11, 60-65)
- References deleted model: `PositionSlot` (line 14, 147-150, 320-328)
- Uses old architecture with intermediary tables

**Required Changes:**

#### Replace:
```php
use App\Models\WorkLocation;  // DELETED
use App\Models\OrgFundedAllocation;  // DELETED
use App\Models\PositionSlot;  // DELETED
```

#### With:
```php
use App\Models\Site;  // NEW - replaces WorkLocation
// Remove OrgFundedAllocation and PositionSlot imports
```

#### Architecture Changes:

**OLD (5-table chain):**
```
Grant → GrantItem → PositionSlot → EmployeeFundingAllocation
Grant → OrgFundedAllocation → EmployeeFundingAllocation
```

**NEW (3-table simplified):**
```
Grant → GrantItem → EmployeeFundingAllocation (via grant_item_id)
Grant → EmployeeFundingAllocation (via grant_id directly for org-funded)
```

#### Specific Line Changes:

**Lines 48-51** (Replace `WorkLocation` with `Site`):
```php
// OLD:
$workLocation = WorkLocation::create([
    'name' => 'Org Probation HQ',
    'type' => 'site',
]);

// NEW:
$site = Site::create([
    'name' => 'Org Probation HQ',
    'code' => 'ORG-HQ',
    'description' => 'Org Probation Headquarters',
    'address' => '123 Test St',
    'is_active' => true,
    'created_by' => 'Seeder',
    'updated_by' => 'Seeder',
]);
```

**Lines 60-65** (Remove `OrgFundedAllocation`, use direct grant allocation):
```php
// OLD:
$orgGrant = Grant::create([...]);
$orgFunded = OrgFundedAllocation::create([
    'grant_id' => $orgGrant->id,
    'department_id' => $department->id,
    'position_id' => $position->id,
    'description' => 'Org funded pool allocation (100%)',
]);

// NEW:
$orgGrant = Grant::create([
    'name' => 'Organization Core Fund',
    'code' => 'ORG-CORE-100',
    'organization' => 'SMRU',
    'description' => 'Internal funding for org-only hires',
    'created_by' => 'Seeder',
    'updated_by' => 'Seeder',
]);
// No OrgFundedAllocation needed!
```

**Lines 77-92** (Update Employment to use `site_id` instead of `work_location_id`):
```php
// OLD:
$employment = Employment::create([
    ...
    'work_location_id' => $workLocation->id,  // WRONG COLUMN
    ...
]);

// NEW:
$employment = Employment::create([
    ...
    'site_id' => $site->id,  // CORRECT COLUMN
    'section_department_id' => null,  // Can be null or link to section_department
    ...
]);
```

**Lines 96-108** (Update allocation to use `grant_id` directly, remove `org_funded_id`):
```php
// OLD:
$allocation = EmployeeFundingAllocation::create([
    'employee_id' => $employee->id,
    'employment_id' => $employment->id,
    'org_funded_id' => $orgFunded->id,  // DELETED COLUMN
    'allocation_type' => 'org_funded',
    'fte' => 1.0,
    'status' => 'active',
    'start_date' => $employment->start_date,
]);

// NEW:
$allocation = EmployeeFundingAllocation::create([
    'employee_id' => $employee->id,
    'employment_id' => $employment->id,
    'grant_id' => $orgGrant->id,  // Direct grant reference for org-funded
    'grant_item_id' => null,  // NULL for org-funded (not grant item)
    'allocation_type' => 'org_funded',
    'fte' => 1.0,
    'allocated_amount' => null,  // Will be calculated by service
    'salary_type' => 'probation_salary',  // Important for initial allocation
    'status' => 'active',
    'start_date' => $employment->start_date,
    'created_by' => 'Seeder',
    'updated_by' => 'Seeder',
]);
```

**Lines 147-157** (Remove PositionSlot, use GrantItem directly):
```php
// OLD:
$grantItem = GrantItem::create([...]);
$slot = PositionSlot::create([
    'grant_item_id' => $grantItem->id,
    'slot_number' => 1,
]);
$orgFunded = OrgFundedAllocation::create([...]);

// NEW:
$grantItem = GrantItem::create([
    'grant_id' => $grant->id,
    'grant_position' => 'Hybrid Liaison',
    'grant_salary' => 15000,
    'grant_benefit' => 1500,
    'grant_level_of_effort' => 30.00,
    'grant_position_number' => 1,
    'budgetline_code' => 'HYB-7030-01',
    'created_by' => 'Seeder',
    'updated_by' => 'Seeder',
]);
// No PositionSlot or OrgFundedAllocation needed!
```

**Lines 235-257** (Update allocations for grant+org split):
```php
// OLD:
$orgAllocation = EmployeeFundingAllocation::create([
    ...
    'org_funded_id' => $orgFunded->id,  // DELETED
    ...
]);
$grantAllocation = EmployeeFundingAllocation::create([
    ...
    'position_slot_id' => $slot->id,  // DELETED
    ...
]);

// NEW:
// Org-funded portion (70%)
$orgAllocation = EmployeeFundingAllocation::create([
    'employee_id' => $employee->id,
    'employment_id' => $employment->id,
    'grant_id' => $grant->id,  // Direct reference
    'grant_item_id' => null,  // NULL for org-funded
    'allocation_type' => 'org_funded',
    'fte' => 0.70,
    'status' => 'active',
    'start_date' => $startDate->toDateString(),
    'created_by' => 'Seeder',
    'updated_by' => 'Seeder',
]);

// Grant-funded portion (30%)
$grantAllocation = EmployeeFundingAllocation::create([
    'employee_id' => $employee->id,
    'employment_id' => $employment->id,
    'grant_id' => $grant->id,  // Parent grant
    'grant_item_id' => $grantItem->id,  // Specific grant item
    'allocation_type' => 'grant',
    'fte' => 0.30,
    'status' => 'active',
    'start_date' => $startDate->toDateString(),
    'created_by' => 'Seeder',
    'updated_by' => 'Seeder',
]);
```

**Complete Updated Scenarios:**
All three scenarios (`seedOrgFundedInitialScenario`, `seedOrgGrantSplitExtensionScenario`, `seedMultiGrantPayrollScenario`) need the above changes applied.

---

### 2. **EmployeeSeeder.php** - VALIDATION ERRORS ⚠️

**Problems:**
- Line 39: Uses 'Other' for gender (not in lookups - only Male/Female)
- Lines 42-43: Uses values not in lookups (British, Australian)

**Required Changes:**

```php
// Line 39 - REMOVE 'Other' from gender:
// OLD:
'gender' => $faker->randomElement(['Male', 'Female', 'Other']),

// NEW:
'gender' => $faker->randomElement(['Male', 'Female']),

// Lines 42-43 - Use only valid nationality values from lookups:
// OLD:
'nationality' => $faker->randomElement(['Thai', 'Myanmar', 'American', 'British', 'Australian']),

// NEW:
'nationality' => $faker->randomElement(['Thai', 'Burmese', 'American', 'Taiwanese', 'Australian', 'N/A', 'Stateless']),

// OR better - pull from lookups dynamically:
'nationality' => DB::table('lookups')->where('type', 'nationality')->inRandomOrder()->value('value'),
```

---

### 3. **UserSeeder.php** - ROLE NAME ERRORS ❌

**Problems:**
- Lines 28, 42, 55, 69: Uses incorrect role names (capitalized)
- Migration creates lowercase roles: admin, hr-manager, hr-assistant-senior, hr-assistant-junior, site-admin

**Required Changes:**

```php
// Line 28 - Admin role:
// OLD:
$admin->assignRole('Admin');

// NEW:
$admin->assignRole('admin');

// Line 42 - HR Manager role:
// OLD:
$hrManager->assignRole('HR-Manager');

// NEW:
$hrManager->assignRole('hr-manager');

// Lines 45-56 - Split HR Assistant into two users:
// OLD:
$hrAssistant = User::firstOrCreate(
    ['email' => 'hrassistant@hrms.com'],
    [...]
);
$hrAssistant->assignRole('HR-Assistant');

// NEW:
// Senior HR Assistant
$hrAssistantSenior = User::firstOrCreate(
    ['email' => 'hrassistant.senior@hrms.com'],
    [
        'name' => 'HR Assistant Senior',
        'password' => Hash::make('password'),
        'last_login_at' => now(),
        'created_by' => 'Seeder',
        'updated_by' => 'Seeder',
    ]
);
$hrAssistantSenior->assignRole('hr-assistant-senior');

// Junior HR Assistant
$hrAssistantJunior = User::firstOrCreate(
    ['email' => 'hrassistant.junior@hrms.com'],
    [
        'name' => 'HR Assistant Junior',
        'password' => Hash::make('password'),
        'last_login_at' => now(),
        'created_by' => 'Seeder',
        'updated_by' => 'Seeder',
    ]
);
$hrAssistantJunior->assignRole('hr-assistant-junior');

// Lines 58-77 - Remove Employee user (no such role):
// Site Admin instead:
$siteAdmin = User::firstOrCreate(
    ['email' => 'siteadmin@hrms.com'],
    [
        'name' => 'Site Admin',
        'password' => Hash::make('password'),
        'last_login_at' => now(),
        'created_by' => 'Seeder',
        'updated_by' => 'Seeder',
    ]
);
$siteAdmin->assignRole('site-admin');
```

---

### 4. **Create Missing Seeders**

#### 4a. SubsidiarySeeder.php (NEW FILE)
```php
<?php

namespace Database\Seeders;

use App\Models\Subsidiary;
use Illuminate\Database\Seeder;

class SubsidiarySeeder extends Seeder
{
    public function run(): void
    {
        $subsidiaries = [
            ['code' => 'SMRU', 'created_by' => 'Seeder', 'updated_by' => 'Seeder'],
            ['code' => 'BHF', 'created_by' => 'Seeder', 'updated_by' => 'Seeder'],
        ];

        foreach ($subsidiaries as $organization) {
            Subsidiary::firstOrCreate(
                ['code' => $organization['code']],
                $organization
            );
        }

        $this->command->info('Subsidiaries seeded successfully.');
    }
}
```

#### 4b. LeaveTypeSeeder.php (NEW FILE)
```php
<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['name' => 'Annual Leave', 'default_duration' => 26, 'description' => 'Paid annual leave', 'requires_attachment' => false],
            ['name' => 'Unpaid Leave', 'default_duration' => 0, 'description' => 'Leave without pay', 'requires_attachment' => false],
            ['name' => 'Traditional day-off', 'default_duration' => 13, 'description' => 'Traditional holidays', 'requires_attachment' => false],
            ['name' => 'Sick', 'default_duration' => 30, 'description' => 'Sick leave', 'requires_attachment' => true],
            ['name' => 'Maternity leave', 'default_duration' => 98, 'description' => 'Maternity leave', 'requires_attachment' => true],
            ['name' => 'Compassionate', 'default_duration' => 5, 'description' => 'Compassionate leave', 'requires_attachment' => false],
            ['name' => 'Career development training', 'default_duration' => 14, 'description' => 'Professional development', 'requires_attachment' => false],
            ['name' => 'Personal leave', 'default_duration' => 3, 'description' => 'Personal time off', 'requires_attachment' => false],
            ['name' => 'Military leave', 'default_duration' => 60, 'description' => 'Military service leave', 'requires_attachment' => true],
            ['name' => 'Sterilization leave', 'default_duration' => 0, 'description' => 'Medical sterilization leave', 'requires_attachment' => true],
            ['name' => 'Other', 'default_duration' => 0, 'description' => 'Other types of leave', 'requires_attachment' => false],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::firstOrCreate(
                ['name' => $type['name']],
                array_merge($type, [
                    'created_by' => 'Seeder',
                    'updated_by' => 'Seeder',
                ])
            );
        }

        $this->command->info('Leave types seeded successfully.');
    }
}
```

#### 4c. TrainingSeeder.php (NEW FILE)
```php
<?php

namespace Database\Seeders;

use App\Models\Training;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $trainings = [
            [
                'title' => 'Leadership Development Program',
                'organizer' => 'HR Department',
                'start_date' => Carbon::now()->addDays(30),
                'end_date' => Carbon::now()->addDays(32),
            ],
            [
                'title' => 'Data Analysis with Python',
                'organizer' => 'IT Department',
                'start_date' => Carbon::now()->addDays(45),
                'end_date' => Carbon::now()->addDays(47),
            ],
            [
                'title' => 'Project Management Fundamentals',
                'organizer' => 'Training Institute',
                'start_date' => Carbon::now()->addDays(60),
                'end_date' => Carbon::now()->addDays(63),
            ],
            [
                'title' => 'Clinical Research Ethics',
                'organizer' => 'Research Department',
                'start_date' => Carbon::now()->addDays(15),
                'end_date' => Carbon::now()->addDays(16),
            ],
            [
                'title' => 'Advanced Excel Training',
                'organizer' => 'Finance Department',
                'start_date' => Carbon::now()->addDays(20),
                'end_date' => Carbon::now()->addDays(21),
            ],
            [
                'title' => 'Effective Communication Skills',
                'organizer' => 'HR Department',
                'start_date' => Carbon::now()->addDays(90),
                'end_date' => Carbon::now()->addDays(92),
            ],
            [
                'title' => 'Laboratory Safety Procedures',
                'organizer' => 'Laboratory Department',
                'start_date' => Carbon::now()->addDays(10),
                'end_date' => Carbon::now()->addDays(10),
            ],
            [
                'title' => 'Financial Reporting Standards',
                'organizer' => 'Finance Department',
                'start_date' => Carbon::now()->addDays(75),
                'end_date' => Carbon::now()->addDays(77),
            ],
            [
                'title' => 'Community Engagement Best Practices',
                'organizer' => 'Public Engagement',
                'start_date' => Carbon::now()->addDays(50),
                'end_date' => Carbon::now()->addDays(52),
            ],
            [
                'title' => 'Cybersecurity Awareness',
                'organizer' => 'IT Department',
                'start_date' => Carbon::now()->addDays(5),
                'end_date' => Carbon::now()->addDays(5),
            ],
        ];

        foreach ($trainings as $training) {
            Training::create(array_merge($training, [
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]));
        }

        $this->command->info('Training programs seeded successfully.');
    }
}
```

---

### 5. **DatabaseSeeder.php** - UPDATE EXECUTION ORDER

**Current:**
```php
$this->call([
    // PermissionRoleSeeder::class,
    // UserSeeder::class,
    // BudgetLineSeeder::class,
    EmployeeSeeder::class,
    // InterviewSeeder::class,
    // GrantSeeder::class,
    // JobOfferSeeder::class,
    // Thai2025TaxDataSeeder::class,
    SectionDepartmentSeeder::class,
    ProbationAllocationSeeder::class,
    BenefitSettingSeeder::class,
]);
```

**NEW (Correct Dependency Order):**
```php
public function run(): void
{
    $this->call([
        // 1. Base data (no dependencies)
        SubsidiarySeeder::class,         // NEW - Creates SMRU, BHF
        LeaveTypeSeeder::class,          // NEW - Creates leave types
        TrainingSeeder::class,           // NEW - Creates training programs

        // 2. Users & Permissions (requires roles from migration)
        // PermissionRoleSeeder::class,  // Optional - migration already creates
        UserSeeder::class,                // Creates 5 users (UPDATED - fixed roles)

        // 3. Employees (requires users optionally)
        EmployeeSeeder::class,            // Creates 100 employees (UPDATED)

        // 4. Organizational structure (requires departments from migration)
        SectionDepartmentSeeder::class,   // Creates sub-departments

        // 5. Complex scenarios (requires all above)
        ProbationAllocationSeeder::class, // Creates employment scenarios (UPDATED)

        // 6. Settings
        BenefitSettingSeeder::class,      // Creates benefit settings

        // 7. Optional additional data
        // GrantSeeder::class,            // Creates additional test grants
        // InterviewSeeder::class,        // Creates interview records
        // JobOfferSeeder::class,         // Creates job offers
    ]);
}
```

---

## Summary of Changes

| Seeder | Status | Action Required |
|--------|--------|-----------------|
| ProbationAllocationSeeder | ❌ BROKEN | Complete rewrite - remove 3 deleted models |
| EmployeeSeeder | ⚠️ DATA ISSUES | Fix gender/nationality values |
| UserSeeder | ❌ ROLE ERRORS | Fix role names to lowercase |
| SubsidiarySeeder | ❌ MISSING | Create new file |
| LeaveTypeSeeder | ❌ MISSING | Create new file |
| TrainingSeeder | ❌ MISSING | Create new file |
| DatabaseSeeder | ⚠️ ORDER WRONG | Update execution order |
| BenefitSettingSeeder | ✅ OK | No changes needed |
| SectionDepartmentSeeder | ✅ OK | No changes needed |
| GrantSeeder | ✅ OK | Optional - works if uncommented |

---

## Priority Actions

1. **CRITICAL:** Fix ProbationAllocationSeeder (remove deleted tables)
2. **CRITICAL:** Fix UserSeeder (role names)
3. **HIGH:** Create SubsidiarySeeder
4. **HIGH:** Create LeaveTypeSeeder
5. **MEDIUM:** Fix EmployeeSeeder validation
6. **MEDIUM:** Create TrainingSeeder
7. **LOW:** Update DatabaseSeeder execution order

---

## Testing After Updates

```bash
# Test with fresh database
php artisan migrate:fresh

# Run seeders
php artisan db:seed

# Verify data
php artisan tinker
>>> \App\Models\Employee::count()
>>> \App\Models\User::with('roles')->get()
>>> \App\Models\EmployeeFundingAllocation::with('grant', 'grantItem')->get()
>>> \App\Models\ProbationRecord::with('employment')->get()
```

Expected results:
- 100 employees
- 5 users with correct roles
- 3 complex employment scenarios with allocations
- 11 leave types
- 2 subsidiaries
- 10 training programs
- 5 benefit settings
- No errors about missing tables or columns
