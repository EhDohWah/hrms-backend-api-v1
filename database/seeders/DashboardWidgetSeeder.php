<?php

namespace Database\Seeders;

use App\Models\DashboardWidget;
use Illuminate\Database\Seeder;

class DashboardWidgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $widgets = [
            // General Widgets (no permission required)
            [
                'name' => 'welcome_card',
                'display_name' => 'Welcome Card',
                'description' => 'Displays a personalized welcome message with user info',
                'component' => 'WelcomeWidget',
                'icon' => 'ti-home',
                'category' => 'general',
                'size' => 'full',
                'required_permission' => null,
                'is_active' => true,
                'is_default' => true,
                'default_order' => 0,
            ],
            [
                'name' => 'quick_actions',
                'display_name' => 'Quick Actions',
                'description' => 'Quick access buttons for common tasks',
                'component' => 'QuickActionsWidget',
                'icon' => 'ti-bolt',
                'category' => 'general',
                'size' => 'medium',
                'required_permission' => null,
                'is_active' => true,
                'is_default' => true,
                'default_order' => 1,
            ],

            // HR Widgets
            [
                'name' => 'employee_stats',
                'display_name' => 'Employee Statistics',
                'description' => 'Overview of total employees, departments, and positions',
                'component' => 'EmployeeStatsWidget',
                'icon' => 'ti-users',
                'category' => 'hr',
                'size' => 'medium',
                'required_permission' => 'employee.read',
                'is_active' => true,
                'is_default' => true,
                'default_order' => 2,
            ],
            [
                'name' => 'recent_hires',
                'display_name' => 'Recent Hires',
                'description' => 'List of recently hired employees',
                'component' => 'RecentHiresWidget',
                'icon' => 'ti-user-plus',
                'category' => 'hr',
                'size' => 'medium',
                'required_permission' => 'employee.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 3,
            ],
            [
                'name' => 'department_overview',
                'display_name' => 'Department Overview',
                'description' => 'Employee count by department chart',
                'component' => 'DepartmentOverviewWidget',
                'icon' => 'ti-building',
                'category' => 'hr',
                'size' => 'large',
                'required_permission' => 'department.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 4,
            ],
            [
                'name' => 'probation_tracker',
                'display_name' => 'Probation Tracker',
                'description' => 'Employees currently on probation',
                'component' => 'ProbationTrackerWidget',
                'icon' => 'ti-clock',
                'category' => 'hr',
                'size' => 'medium',
                'required_permission' => 'employee.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 5,
            ],

            // Leave Widgets
            [
                'name' => 'leave_summary',
                'display_name' => 'Leave Summary',
                'description' => 'Summary of leave balances and requests',
                'component' => 'LeaveSummaryWidget',
                'icon' => 'ti-calendar-off',
                'category' => 'leave',
                'size' => 'medium',
                'required_permission' => 'leave.read',
                'is_active' => true,
                'is_default' => true,
                'default_order' => 6,
            ],
            [
                'name' => 'pending_leave_requests',
                'display_name' => 'Pending Leave Requests',
                'description' => 'Leave requests awaiting approval',
                'component' => 'PendingLeaveRequestsWidget',
                'icon' => 'ti-clipboard-list',
                'category' => 'leave',
                'size' => 'medium',
                'required_permission' => 'leave.edit',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 7,
            ],
            [
                'name' => 'leave_calendar',
                'display_name' => 'Leave Calendar',
                'description' => 'Calendar view of employee leaves',
                'component' => 'LeaveCalendarWidget',
                'icon' => 'ti-calendar',
                'category' => 'leave',
                'size' => 'large',
                'required_permission' => 'leave.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 8,
            ],

            // Payroll Widgets
            [
                'name' => 'payroll_summary',
                'display_name' => 'Payroll Summary',
                'description' => 'Overview of current payroll status',
                'component' => 'PayrollSummaryWidget',
                'icon' => 'ti-cash',
                'category' => 'payroll',
                'size' => 'medium',
                'required_permission' => 'payroll.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 9,
            ],
            [
                'name' => 'payroll_upcoming',
                'display_name' => 'Upcoming Payroll',
                'description' => 'Next payroll date and estimated amount',
                'component' => 'UpcomingPayrollWidget',
                'icon' => 'ti-calendar-dollar',
                'category' => 'payroll',
                'size' => 'small',
                'required_permission' => 'payroll.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 10,
            ],

            // Attendance Widgets
            [
                'name' => 'attendance_today',
                'display_name' => 'Today\'s Attendance',
                'description' => 'Current day attendance status',
                'component' => 'TodayAttendanceWidget',
                'icon' => 'ti-clock-check',
                'category' => 'attendance',
                'size' => 'medium',
                'required_permission' => 'attendance.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 11,
            ],

            // Recruitment Widgets
            [
                'name' => 'open_positions',
                'display_name' => 'Open Positions',
                'description' => 'Currently open job positions',
                'component' => 'OpenPositionsWidget',
                'icon' => 'ti-briefcase',
                'category' => 'recruitment',
                'size' => 'medium',
                'required_permission' => 'recruitment.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 12,
            ],
            [
                'name' => 'pending_interviews',
                'display_name' => 'Pending Interviews',
                'description' => 'Upcoming interviews schedule',
                'component' => 'PendingInterviewsWidget',
                'icon' => 'ti-message-dots',
                'category' => 'recruitment',
                'size' => 'medium',
                'required_permission' => 'interview.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 13,
            ],

            // Training Widgets
            [
                'name' => 'training_overview',
                'display_name' => 'Training Overview',
                'description' => 'Overview of training programs and participation',
                'component' => 'TrainingOverviewWidget',
                'icon' => 'ti-certificate',
                'category' => 'training',
                'size' => 'medium',
                'required_permission' => 'training.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 14,
            ],

            // Reports Widgets
            [
                'name' => 'reports_quick_access',
                'display_name' => 'Quick Reports',
                'description' => 'Quick access to common reports',
                'component' => 'QuickReportsWidget',
                'icon' => 'ti-chart-bar',
                'category' => 'reports',
                'size' => 'medium',
                'required_permission' => 'report.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 15,
            ],

            // User Management Widgets (Admin only)
            [
                'name' => 'user_activity',
                'display_name' => 'User Activity',
                'description' => 'Recent user login and activity',
                'component' => 'UserActivityWidget',
                'icon' => 'ti-activity',
                'category' => 'hr',
                'size' => 'medium',
                'required_permission' => 'user_management.read',
                'is_active' => true,
                'is_default' => false,
                'default_order' => 16,
            ],
            [
                'name' => 'system_notifications',
                'display_name' => 'System Notifications',
                'description' => 'Important system notifications and alerts',
                'component' => 'SystemNotificationsWidget',
                'icon' => 'ti-bell',
                'category' => 'general',
                'size' => 'medium',
                'required_permission' => null,
                'is_active' => true,
                'is_default' => true,
                'default_order' => 17,
            ],
        ];

        foreach ($widgets as $widget) {
            DashboardWidget::updateOrCreate(
                ['name' => $widget['name']],
                $widget
            );
        }

        $this->command->info('Dashboard widgets seeded successfully!');
    }
}
