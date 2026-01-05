<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * IMPORTANT: This seeder stores ONLY actionable submenus with routes and permissions.
     *
     * The frontend sidebar-data.json handles:
     * - Category titles (Main Menu, Grant, Recruitment, HRM, Finance & Accounts, Administration)
     * - Parent menus (Dashboard, Grants, Recruitment, Employee, Leaves, etc.)
     *
     * This seeder stores:
     * - Actionable submenus with actual routes and permissions
     * - The 'category' field groups items for the permission accordion UI
     *
     * Matching sidebar-data.json structure:
     * - tittle: "HRM" → Used for sidebar grouping only
     * - menu[].menuValue: "Employee" → category field in this seeder
     * - menu[].subMenus[].menuValue: "Employees" → This is what we store!
     */
    public function run(): void
    {
        $modules = [
            // ============================================================================
            // MAIN MENU > DASHBOARD
            // Single dynamic dashboard for all users with customizable widgets
            // Each user sees their own widgets based on their preferences and permissions
            // ============================================================================
            [
                'name' => 'dashboard',
                'display_name' => 'My Dashboard',
                'description' => 'Dynamic dashboard with customizable widgets',
                'icon' => 'smart-home',
                'category' => 'Dashboard',
                'route' => '/dashboard',
                'active_link' => '/dashboard',
                'read_permission' => 'dashboard.read',
                'edit_permissions' => ['dashboard.edit'],
                'order' => 1,
            ],

            // ============================================================================
            // GRANT > GRANTS SUBMENUS
            // From sidebar-data.json: tittle: "Grant", menu[0].menuValue: "Grants"
            // ============================================================================
            [
                'name' => 'grants_list',
                'display_name' => 'Grants List',
                'description' => 'View and manage grants',
                'icon' => 'award',
                'category' => 'Grants',
                'route' => '/grant/list',
                'active_link' => '/grant/list',
                'read_permission' => 'grants_list.read',
                'edit_permissions' => ['grants_list.edit'],
                'order' => 10,
            ],
            [
                'name' => 'grant_position',
                'display_name' => 'Grant Position',
                'description' => 'Manage grant positions',
                'icon' => 'award',
                'category' => 'Grants',
                'route' => '/grant/grant-position',
                'active_link' => '/grant/grant-position',
                'read_permission' => 'grant_position.read',
                'edit_permissions' => ['grant_position.edit'],
                'order' => 11,
            ],

            // ============================================================================
            // RECRUITMENT > RECRUITMENT SUBMENUS
            // From sidebar-data.json: tittle: "Recruitment", menu[0].menuValue: "Recruitment"
            // ============================================================================
            [
                'name' => 'interviews',
                'display_name' => 'Interviews',
                'description' => 'Manage candidate interviews',
                'icon' => 'calendar',
                'category' => 'Recruitment',
                'route' => '/recruitment/interviews-list',
                'active_link' => '/recruitment/interviews-list',
                'read_permission' => 'interviews.read',
                'edit_permissions' => ['interviews.edit'],
                'order' => 20,
            ],
            [
                'name' => 'job_offers',
                'display_name' => 'Job Offers',
                'description' => 'Manage job offers',
                'icon' => 'mail',
                'category' => 'Recruitment',
                'route' => '/recruitment/job-offers-list',
                'active_link' => '/recruitment/job-offers-list',
                'read_permission' => 'job_offers.read',
                'edit_permissions' => ['job_offers.edit'],
                'order' => 21,
            ],

            // ============================================================================
            // HRM > EMPLOYEE SUBMENUS
            // From sidebar-data.json: tittle: "HRM", menu[0].menuValue: "Employee"
            // ============================================================================
            [
                'name' => 'employees',
                'display_name' => 'Employees',
                'description' => 'Manage employee records',
                'icon' => 'users',
                'category' => 'Employee',
                'route' => '/employee/employee-list',
                'active_link' => '/employee/employee-list',
                'read_permission' => 'employees.read',
                'edit_permissions' => ['employees.edit'],
                'order' => 30,
            ],
            [
                'name' => 'employment_records',
                'display_name' => 'Employment Records',
                'description' => 'Manage employment records',
                'icon' => 'briefcase',
                'category' => 'Employee',
                'route' => '/employee/employment-list',
                'active_link' => '/employee/employment-list',
                'read_permission' => 'employment_records.read',
                'edit_permissions' => ['employment_records.edit'],
                'order' => 31,
            ],
            [
                'name' => 'employee_resignation',
                'display_name' => 'Employee Resignation',
                'description' => 'Manage employee resignations',
                'icon' => 'external-link',
                'category' => 'Employee',
                'route' => '/employee/employee-resignation',
                'active_link' => '/employee/employee-resignation',
                'read_permission' => 'employee_resignation.read',
                'edit_permissions' => ['employee_resignation.edit'],
                'order' => 32,
            ],

            // ============================================================================
            // HRM > HOLIDAYS (Standalone - no submenus in sidebar-data.json)
            // From sidebar-data.json: menu[1].menuValue: "Holidays", hasSubRoute: false
            // ============================================================================
            [
                'name' => 'holidays',
                'display_name' => 'Holidays',
                'description' => 'Manage company holidays',
                'icon' => 'calendar-event',
                'category' => 'HRM',
                'route' => '/hrm/holidays',
                'active_link' => '/hrm/holidays',
                'read_permission' => 'holidays.read',
                'edit_permissions' => ['holidays.edit'],
                'order' => 40,
            ],

            // ============================================================================
            // HRM > LEAVES SUBMENUS
            // From sidebar-data.json: menu[2].menuValue: "Leaves"
            // ============================================================================
            [
                'name' => 'leaves_admin',
                'display_name' => 'Leaves (Admin)',
                'description' => 'Admin leave management',
                'icon' => 'clipboard-check',
                'category' => 'Leaves',
                'route' => '/leave/admin/leaves-admin',
                'active_link' => '/leave/admin/leaves-admin',
                'read_permission' => 'leaves_admin.read',
                'edit_permissions' => ['leaves_admin.edit'],
                'order' => 50,
            ],
            [
                'name' => 'leaves_employee',
                'display_name' => 'Leave (Employee)',
                'description' => 'Employee leave management',
                'icon' => 'clipboard-check',
                'category' => 'Leaves',
                'route' => '/leave/employee/leaves-employee',
                'active_link' => '/leave/employee/leaves-employee',
                'read_permission' => 'leaves_employee.read',
                'edit_permissions' => ['leaves_employee.edit'],
                'order' => 51,
            ],
            [
                'name' => 'leave_settings',
                'display_name' => 'Leave Settings',
                'description' => 'Configure leave settings',
                'icon' => 'clipboard-check',
                'category' => 'Leaves',
                'route' => '/leave/admin/leave-settings',
                'active_link' => '/leave/admin/leave-settings',
                'read_permission' => 'leave_settings.read',
                'edit_permissions' => ['leave_settings.edit'],
                'order' => 52,
            ],
            [
                'name' => 'leave_types',
                'display_name' => 'Leave Types',
                'description' => 'Manage leave types',
                'icon' => 'clipboard-check',
                'category' => 'Leaves',
                'route' => '/leave/admin/leave-types',
                'active_link' => '/leave/admin/leave-types',
                'read_permission' => 'leave_types.read',
                'edit_permissions' => ['leave_types.edit'],
                'order' => 53,
            ],
            [
                'name' => 'leave_balances',
                'display_name' => 'Leave Balances',
                'description' => 'View leave balances',
                'icon' => 'clipboard-check',
                'category' => 'Leaves',
                'route' => '/leave/admin/leave-balances',
                'active_link' => '/leave/admin/leave-balances',
                'read_permission' => 'leave_balances.read',
                'edit_permissions' => ['leave_balances.edit'],
                'order' => 54,
            ],

            // ============================================================================
            // HRM > TRAVEL SUBMENUS
            // From sidebar-data.json: menu[3].menuValue: "Travel"
            // ============================================================================
            [
                'name' => 'travel_admin',
                'display_name' => 'Travel (Admin)',
                'description' => 'Admin travel management',
                'icon' => 'bus',
                'category' => 'Travel',
                'route' => '/requests/travel/admin',
                'active_link' => '/requests/travel/admin',
                'read_permission' => 'travel_admin.read',
                'edit_permissions' => ['travel_admin.edit'],
                'order' => 60,
            ],
            [
                'name' => 'travel_employee',
                'display_name' => 'Travel (Employee)',
                'description' => 'Employee travel requests',
                'icon' => 'bus',
                'category' => 'Travel',
                'route' => '/requests/travel',
                'active_link' => '/requests/travel',
                'read_permission' => 'travel_employee.read',
                'edit_permissions' => ['travel_employee.edit'],
                'order' => 61,
            ],

            // ============================================================================
            // HRM > ATTENDANCE SUBMENUS
            // From sidebar-data.json: menu[4].menuValue: "Attendance"
            // ============================================================================
            [
                'name' => 'attendance_admin',
                'display_name' => 'Attendance (Admin)',
                'description' => 'Admin attendance management',
                'icon' => 'file-time',
                'category' => 'Attendance',
                'route' => '/attendance/attendance-admin',
                'active_link' => '/attendance/attendance-admin',
                'read_permission' => 'attendance_admin.read',
                'edit_permissions' => ['attendance_admin.edit'],
                'order' => 70,
            ],
            [
                'name' => 'attendance_employee',
                'display_name' => 'Attendance (Employee)',
                'description' => 'Employee attendance view',
                'icon' => 'file-time',
                'category' => 'Attendance',
                'route' => '/attendance/attendance-employee',
                'active_link' => '/attendance/attendance-employee',
                'read_permission' => 'attendance_employee.read',
                'edit_permissions' => ['attendance_employee.edit'],
                'order' => 71,
            ],
            [
                'name' => 'timesheets',
                'display_name' => 'Timesheets',
                'description' => 'Manage timesheets',
                'icon' => 'file-time',
                'category' => 'Attendance',
                'route' => '/attendance/timesheets',
                'active_link' => '/attendance/timesheets',
                'read_permission' => 'timesheets.read',
                'edit_permissions' => ['timesheets.edit'],
                'order' => 72,
            ],
            [
                'name' => 'shift_schedule',
                'display_name' => 'Shift & Schedule',
                'description' => 'Manage shifts and schedules',
                'icon' => 'file-time',
                'category' => 'Attendance',
                'route' => '/attendance/schedule-timing',
                'active_link' => '/attendance/schedule-timing',
                'read_permission' => 'shift_schedule.read',
                'edit_permissions' => ['shift_schedule.edit'],
                'order' => 73,
            ],
            [
                'name' => 'overtime',
                'display_name' => 'Overtime',
                'description' => 'Manage overtime',
                'icon' => 'file-time',
                'category' => 'Attendance',
                'route' => '/attendance/overtime',
                'active_link' => '/attendance/overtime',
                'read_permission' => 'overtime.read',
                'edit_permissions' => ['overtime.edit'],
                'order' => 74,
            ],

            // ============================================================================
            // HRM > TRAINING SUBMENUS
            // From sidebar-data.json: menu[5].menuValue: "Training"
            // ============================================================================
            [
                'name' => 'training_list',
                'display_name' => 'Training List',
                'description' => 'View training programs',
                'icon' => 'edit',
                'category' => 'Training',
                'route' => '/training/training-list',
                'active_link' => '/training/training-list',
                'read_permission' => 'training_list.read',
                'edit_permissions' => ['training_list.edit'],
                'order' => 80,
            ],
            [
                'name' => 'employee_training',
                'display_name' => 'Employee Training',
                'description' => 'Manage employee training',
                'icon' => 'edit',
                'category' => 'Training',
                'route' => '/training/employee-training-list',
                'active_link' => '/training/employee-training-list',
                'read_permission' => 'employee_training.read',
                'edit_permissions' => ['employee_training.edit'],
                'order' => 81,
            ],

            // ============================================================================
            // HRM > RESIGNATION (Standalone - no submenus)
            // From sidebar-data.json: menu[6].menuValue: "Resignation", hasSubRoute: false
            // ============================================================================
            [
                'name' => 'resignation',
                'display_name' => 'Resignation',
                'description' => 'Manage resignations',
                'icon' => 'external-link',
                'category' => 'HRM',
                'route' => '/hrm/resignation',
                'active_link' => '/hrm/resignation',
                'read_permission' => 'resignation.read',
                'edit_permissions' => ['resignation.edit'],
                'order' => 90,
            ],

            // ============================================================================
            // HRM > TERMINATION (Standalone - no submenus)
            // From sidebar-data.json: menu[7].menuValue: "Termination", hasSubRoute: false
            // ============================================================================
            [
                'name' => 'termination',
                'display_name' => 'Termination',
                'description' => 'Manage terminations',
                'icon' => 'circle-x',
                'category' => 'HRM',
                'route' => '/hrm/termination',
                'active_link' => '/hrm/termination',
                'read_permission' => 'termination.read',
                'edit_permissions' => ['termination.edit'],
                'order' => 91,
            ],

            // ============================================================================
            // FINANCE & ACCOUNTS > PAYROLL SUBMENUS
            // From sidebar-data.json: tittle: "Finance & Accounts", menu[0].menuValue: "Payroll"
            // ============================================================================
            [
                'name' => 'employee_salary',
                'display_name' => 'Employee Salary',
                'description' => 'Manage employee salaries',
                'icon' => 'cash',
                'category' => 'Payroll',
                'route' => '/payroll/employee-salary',
                'active_link' => '/payroll/employee-salary',
                'read_permission' => 'employee_salary.read',
                'edit_permissions' => ['employee_salary.edit'],
                'order' => 100,
            ],
            [
                'name' => 'tax_settings',
                'display_name' => 'Tax Settings',
                'description' => 'Manage tax settings',
                'icon' => 'receipt-tax',
                'category' => 'Payroll',
                'route' => '/payroll/tax-settings',
                'active_link' => '/payroll/tax-settings',
                'read_permission' => 'tax_settings.read',
                'edit_permissions' => ['tax_settings.edit'],
                'order' => 101,
            ],
            [
                'name' => 'benefit_settings',
                'display_name' => 'Benefit Settings',
                'description' => 'Manage benefit settings',
                'icon' => 'cash',
                'category' => 'Payroll',
                'route' => '/payroll/benefit-settings',
                'active_link' => '/payroll/benefit-settings',
                'read_permission' => 'benefit_settings.read',
                'edit_permissions' => ['benefit_settings.edit'],
                'order' => 102,
            ],
            [
                'name' => 'payslip',
                'display_name' => 'Payslip',
                'description' => 'View payslips',
                'icon' => 'cash',
                'category' => 'Payroll',
                'route' => '/payroll/payslip',
                'active_link' => '/payroll/payslip',
                'read_permission' => 'payslip.read',
                'edit_permissions' => ['payslip.edit'],
                'order' => 103,
            ],
            [
                'name' => 'payroll_items',
                'display_name' => 'Payroll Items',
                'description' => 'Manage payroll items',
                'icon' => 'cash',
                'category' => 'Payroll',
                'route' => '/payroll/payroll',
                'active_link' => '/payroll/payroll',
                'read_permission' => 'payroll_items.read',
                'edit_permissions' => ['payroll_items.edit'],
                'order' => 104,
            ],

            // ============================================================================
            // ADMINISTRATION > LOOKUPS SUBMENUS
            // From sidebar-data.json: tittle: "Administration", menu[0].menuValue: "Lookups"
            // ============================================================================
            [
                'name' => 'lookup_list',
                'display_name' => 'Lookup List',
                'description' => 'Manage lookup values',
                'icon' => 'user-star',
                'category' => 'Lookups',
                'route' => '/lookups/lookup-list',
                'active_link' => '/lookups/lookup-list',
                'read_permission' => 'lookup_list.read',
                'edit_permissions' => ['lookup_list.edit'],
                'order' => 110,
            ],

            // ============================================================================
            // ADMINISTRATION > ORGANIZATION STRUCTURE SUBMENUS
            // From sidebar-data.json: menu[1].menuValue: "Organization Structure"
            // ============================================================================
            [
                'name' => 'sites',
                'display_name' => 'Sites',
                'description' => 'Manage company sites',
                'icon' => 'building',
                'category' => 'Organization Structure',
                'route' => '/sites',
                'active_link' => '/sites',
                'read_permission' => 'sites.read',
                'edit_permissions' => ['sites.edit'],
                'order' => 120,
            ],
            [
                'name' => 'departments',
                'display_name' => 'Departments',
                'description' => 'Manage departments',
                'icon' => 'building',
                'category' => 'Organization Structure',
                'route' => '/departments',
                'active_link' => '/departments',
                'read_permission' => 'departments.read',
                'edit_permissions' => ['departments.edit'],
                'order' => 121,
            ],
            [
                'name' => 'positions',
                'display_name' => 'Positions',
                'description' => 'Manage positions',
                'icon' => 'building',
                'category' => 'Organization Structure',
                'route' => '/positions',
                'active_link' => '/positions',
                'read_permission' => 'positions.read',
                'edit_permissions' => ['positions.edit'],
                'order' => 122,
            ],
            [
                'name' => 'section_departments',
                'display_name' => 'Section Departments',
                'description' => 'Manage section departments',
                'icon' => 'building',
                'category' => 'Organization Structure',
                'route' => '/section-departments',
                'active_link' => '/section-departments',
                'read_permission' => 'section_departments.read',
                'edit_permissions' => ['section_departments.edit'],
                'order' => 123,
            ],

            // ============================================================================
            // ADMINISTRATION > USER MANAGEMENT SUBMENUS
            // From sidebar-data.json: menu[2].menuValue: "User Management"
            // ============================================================================
            [
                'name' => 'users',
                'display_name' => 'Users',
                'description' => 'Manage system users',
                'icon' => 'users',
                'category' => 'User Management',
                'route' => '/user-management/users',
                'active_link' => '/user-management/users',
                'read_permission' => 'users.read',
                'edit_permissions' => ['users.edit'],
                'order' => 130,
            ],
            [
                'name' => 'roles',
                'display_name' => 'Roles',
                'description' => 'Manage roles and permissions',
                'icon' => 'shield',
                'category' => 'User Management',
                'route' => '/user-management/roles',
                'active_link' => '/user-management/roles',
                'read_permission' => 'roles.read',
                'edit_permissions' => ['roles.edit'],
                'order' => 131,
            ],

            // ============================================================================
            // ADMINISTRATION > REPORTS SUBMENUS
            // From sidebar-data.json: menu[3].menuValue: "Reports"
            // ============================================================================
            [
                'name' => 'report_list',
                'display_name' => 'Report List',
                'description' => 'View all reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/report-list',
                'active_link' => '/reports/report-list',
                'read_permission' => 'report_list.read',
                'edit_permissions' => ['report_list.edit'],
                'order' => 140,
            ],
            [
                'name' => 'expense_report',
                'display_name' => 'Expense Report',
                'description' => 'View expense reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/expenses-report',
                'active_link' => '/reports/expenses-report',
                'read_permission' => 'expense_report.read',
                'edit_permissions' => ['expense_report.edit'],
                'order' => 141,
            ],
            [
                'name' => 'invoice_report',
                'display_name' => 'Invoice Report',
                'description' => 'View invoice reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/invoice-report',
                'active_link' => '/reports/invoice-report',
                'read_permission' => 'invoice_report.read',
                'edit_permissions' => ['invoice_report.edit'],
                'order' => 142,
            ],
            [
                'name' => 'payment_report',
                'display_name' => 'Payment Report',
                'description' => 'View payment reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/payment-report',
                'active_link' => '/reports/payment-report',
                'read_permission' => 'payment_report.read',
                'edit_permissions' => ['payment_report.edit'],
                'order' => 143,
            ],
            [
                'name' => 'project_report',
                'display_name' => 'Project Report',
                'description' => 'View project reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/project-report',
                'active_link' => '/reports/project-report',
                'read_permission' => 'project_report.read',
                'edit_permissions' => ['project_report.edit'],
                'order' => 144,
            ],
            [
                'name' => 'task_report',
                'display_name' => 'Task Report',
                'description' => 'View task reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/task-report',
                'active_link' => '/reports/task-report',
                'read_permission' => 'task_report.read',
                'edit_permissions' => ['task_report.edit'],
                'order' => 145,
            ],
            [
                'name' => 'user_report',
                'display_name' => 'User Report',
                'description' => 'View user reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/user-report',
                'active_link' => '/reports/user-report',
                'read_permission' => 'user_report.read',
                'edit_permissions' => ['user_report.edit'],
                'order' => 146,
            ],
            [
                'name' => 'employee_report',
                'display_name' => 'Employee Report',
                'description' => 'View employee reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/employee-report',
                'active_link' => '/reports/employee-report',
                'read_permission' => 'employee_report.read',
                'edit_permissions' => ['employee_report.edit'],
                'order' => 147,
            ],
            [
                'name' => 'payslip_report',
                'display_name' => 'Payslip Report',
                'description' => 'View payslip reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/payslip-report',
                'active_link' => '/reports/payslip-report',
                'read_permission' => 'payslip_report.read',
                'edit_permissions' => ['payslip_report.edit'],
                'order' => 148,
            ],
            [
                'name' => 'attendance_report',
                'display_name' => 'Attendance Report',
                'description' => 'View attendance reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/attendance-report',
                'active_link' => '/reports/attendance-report',
                'read_permission' => 'attendance_report.read',
                'edit_permissions' => ['attendance_report.edit'],
                'order' => 149,
            ],
            [
                'name' => 'leave_report',
                'display_name' => 'Leave Report',
                'description' => 'View leave reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/leave-report',
                'active_link' => '/reports/leave-report',
                'read_permission' => 'leave_report.read',
                'edit_permissions' => ['leave_report.edit'],
                'order' => 150,
            ],
            [
                'name' => 'daily_report',
                'display_name' => 'Daily Report',
                'description' => 'View daily reports',
                'icon' => 'user-star',
                'category' => 'Reports',
                'route' => '/reports/daily-report',
                'active_link' => '/reports/daily-report',
                'read_permission' => 'daily_report.read',
                'edit_permissions' => ['daily_report.edit'],
                'order' => 151,
            ],

            // ============================================================================
            // ADMINISTRATION > FILE UPLOADS (Standalone)
            // From sidebar-data.json: menu[4].menuValue: "File Uploads", hasSubRoute: false
            // ============================================================================
            [
                'name' => 'file_uploads',
                'display_name' => 'File Uploads',
                'description' => 'Manage file uploads',
                'icon' => 'upload',
                'category' => 'Administration',
                'route' => '/file-uploads',
                'active_link' => '/file-uploads',
                'read_permission' => 'file_uploads.read',
                'edit_permissions' => ['file_uploads.edit'],
                'order' => 160,
            ],

            // ============================================================================
            // ADMINISTRATION > RECYCLE BIN SUBMENUS
            // From sidebar-data.json: menu[5].menuValue: "Recycle Bin"
            // ============================================================================
            [
                'name' => 'recycle_bin_list',
                'display_name' => 'Recycle Bin List',
                'description' => 'View deleted items',
                'icon' => 'recycle',
                'category' => 'Recycle Bin',
                'route' => '/recycle-bin/recycle-bin-list',
                'active_link' => '/recycle-bin/recycle-bin-list',
                'read_permission' => 'recycle_bin_list.read',
                'edit_permissions' => ['recycle_bin_list.edit'],
                'order' => 170,
            ],
        ];

        foreach ($modules as $moduleData) {
            Module::updateOrCreate(
                ['name' => $moduleData['name']],
                $moduleData
            );
        }

        $this->command->info('✅ Modules seeded successfully!');
        $this->command->info('Total actionable modules: '.count($modules));
        $this->command->info('');
        $this->command->info('Categories for permission accordion:');
        $this->command->info('- Main Menu (1 item: Dashboard - dynamic with widgets)');
        $this->command->info('- Grants (2 items)');
        $this->command->info('- Recruitment (2 items)');
        $this->command->info('- Employee (3 items)');
        $this->command->info('- HRM (3 standalone items: Holidays, Resignation, Termination)');
        $this->command->info('- Leaves (5 items)');
        $this->command->info('- Travel (2 items)');
        $this->command->info('- Attendance (5 items)');
        $this->command->info('- Training (2 items)');
        $this->command->info('- Payroll (5 items)');
        $this->command->info('- Lookups (1 item)');
        $this->command->info('- Organization Structure (4 items)');
        $this->command->info('- User Management (2 items)');
        $this->command->info('- Reports (12 items)');
        $this->command->info('- Administration (1 item: File Uploads)');
        $this->command->info('- Recycle Bin (1 item)');
    }
}
