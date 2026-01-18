<?php

namespace App\Enums;

/**
 * Notification Category Enum
 *
 * Categories aligned with ModuleSeeder structure for consistent
 * UI grouping and filtering in the frontend.
 */
enum NotificationCategory: string
{
    // ============ MAIN MENU ============
    case DASHBOARD = 'dashboard';

    // ============ GRANT ============
    case GRANTS = 'grants';

    // ============ RECRUITMENT ============
    case RECRUITMENT = 'recruitment';

    // ============ HRM - EMPLOYEE ============
    case EMPLOYEE = 'employee';

    // ============ HRM - HOLIDAYS ============
    case HOLIDAYS = 'holidays';

    // ============ HRM - LEAVES ============
    case LEAVES = 'leaves';

    // ============ HRM - TRAVEL ============
    case TRAVEL = 'travel';

    // ============ HRM - ATTENDANCE ============
    case ATTENDANCE = 'attendance';

    // ============ HRM - TRAINING ============
    case TRAINING = 'training';

    // ============ HRM - RESIGNATION ============
    case RESIGNATION = 'resignation';

    // ============ HRM - TERMINATION ============
    case TERMINATION = 'termination';

    // ============ FINANCE & ACCOUNTS - PAYROLL ============
    case PAYROLL = 'payroll';

    // ============ ADMINISTRATION - LOOKUPS ============
    case LOOKUPS = 'lookups';

    // ============ ADMINISTRATION - ORGANIZATION ============
    case ORGANIZATION = 'organization';

    // ============ ADMINISTRATION - USER MANAGEMENT ============
    case USER_MANAGEMENT = 'user_management';

    // ============ ADMINISTRATION - REPORTS ============
    case REPORTS = 'reports';

    // ============ ADMINISTRATION - FILE UPLOADS ============
    case FILE_UPLOADS = 'file_uploads';

    // ============ ADMINISTRATION - RECYCLE BIN ============
    case RECYCLE_BIN = 'recycle_bin';

    // ============ SYSTEM & IMPORT ============
    case IMPORT = 'import';
    case SYSTEM = 'system';

    // ============ FALLBACK ============
    case GENERAL = 'general';

    /**
     * Get display label for the category
     */
    public function label(): string
    {
        return match ($this) {
            self::DASHBOARD => 'Dashboard',
            self::GRANTS => 'Grants',
            self::RECRUITMENT => 'Recruitment',
            self::EMPLOYEE => 'Employee',
            self::HOLIDAYS => 'Holidays',
            self::LEAVES => 'Leaves',
            self::TRAVEL => 'Travel',
            self::ATTENDANCE => 'Attendance',
            self::TRAINING => 'Training',
            self::RESIGNATION => 'Resignation',
            self::TERMINATION => 'Termination',
            self::PAYROLL => 'Payroll',
            self::LOOKUPS => 'Lookups',
            self::ORGANIZATION => 'Organization',
            self::USER_MANAGEMENT => 'User Management',
            self::REPORTS => 'Reports',
            self::FILE_UPLOADS => 'File Uploads',
            self::RECYCLE_BIN => 'Recycle Bin',
            self::IMPORT => 'Import',
            self::SYSTEM => 'System',
            self::GENERAL => 'General',
        };
    }

    /**
     * Get emoji icon for the category
     */
    public function icon(): string
    {
        return match ($this) {
            self::DASHBOARD => '📊',
            self::GRANTS => '🎯',
            self::RECRUITMENT => '👔',
            self::EMPLOYEE => '👤',
            self::HOLIDAYS => '🏖️',
            self::LEAVES => '📅',
            self::TRAVEL => '✈️',
            self::ATTENDANCE => '⏰',
            self::TRAINING => '📚',
            self::RESIGNATION => '🚪',
            self::TERMINATION => '⛔',
            self::PAYROLL => '💰',
            self::LOOKUPS => '📋',
            self::ORGANIZATION => '🏢',
            self::USER_MANAGEMENT => '👥',
            self::REPORTS => '📈',
            self::FILE_UPLOADS => '📁',
            self::RECYCLE_BIN => '🗑️',
            self::IMPORT => '📊',
            self::SYSTEM => '⚠️',
            self::GENERAL => '🔔',
        };
    }

    /**
     * Get color for the category (for UI styling)
     */
    public function color(): string
    {
        return match ($this) {
            self::DASHBOARD => '#1890ff',
            self::GRANTS => '#52c41a',
            self::RECRUITMENT => '#722ed1',
            self::EMPLOYEE => '#1890ff',
            self::HOLIDAYS => '#13c2c2',
            self::LEAVES => '#1890ff',
            self::TRAVEL => '#2f54eb',
            self::ATTENDANCE => '#52c41a',
            self::TRAINING => '#fa8c16',
            self::RESIGNATION => '#faad14',
            self::TERMINATION => '#f5222d',
            self::PAYROLL => '#52c41a',
            self::LOOKUPS => '#8c8c8c',
            self::ORGANIZATION => '#1890ff',
            self::USER_MANAGEMENT => '#722ed1',
            self::REPORTS => '#52c41a',
            self::FILE_UPLOADS => '#52c41a',
            self::RECYCLE_BIN => '#8c8c8c',
            self::IMPORT => '#52c41a',
            self::SYSTEM => '#faad14',
            self::GENERAL => '#8c8c8c',
        };
    }

    /**
     * Map module name to category
     */
    public static function fromModule(string $module): self
    {
        return match ($module) {
            // Dashboard
            'dashboard' => self::DASHBOARD,

            // Grants
            'grants_list', 'grant_position' => self::GRANTS,

            // Recruitment
            'interviews', 'job_offers' => self::RECRUITMENT,

            // Employee
            'employees', 'employment_records', 'employee_funding_allocations', 'employee_resignation' => self::EMPLOYEE,

            // HRM - Others
            'holidays' => self::HOLIDAYS,
            'resignation' => self::RESIGNATION,
            'termination' => self::TERMINATION,

            // Leaves
            'leaves_admin', 'leaves_employee', 'leave_settings', 'leave_types', 'leave_balances' => self::LEAVES,

            // Travel
            'travel_admin', 'travel_employee' => self::TRAVEL,

            // Attendance
            'attendance_admin', 'attendance_employee', 'timesheets', 'shift_schedule', 'overtime' => self::ATTENDANCE,

            // Training
            'training_list', 'employee_training' => self::TRAINING,

            // Payroll
            'employee_salary', 'tax_settings', 'benefit_settings', 'payslip', 'payroll_items' => self::PAYROLL,

            // Administration
            'lookup_list' => self::LOOKUPS,
            'sites', 'departments', 'positions', 'section_departments' => self::ORGANIZATION,
            'users', 'roles' => self::USER_MANAGEMENT,
            'file_uploads' => self::FILE_UPLOADS,
            'recycle_bin_list' => self::RECYCLE_BIN,

            // Reports
            'report_list', 'expense_report', 'invoice_report', 'payment_report', 'project_report',
            'task_report', 'user_report', 'employee_report', 'payslip_report', 'attendance_report',
            'leave_report', 'daily_report' => self::REPORTS,

            // Import/System
            'import' => self::IMPORT,
            'system' => self::SYSTEM,

            default => self::GENERAL,
        };
    }

    /**
     * Get all categories as array for API responses
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'icon' => $category->icon(),
                'color' => $category->color(),
            ],
            self::cases()
        );
    }
}
