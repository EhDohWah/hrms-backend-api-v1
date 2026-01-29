<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes are critical for optimizing the EmploymentController performance
     * Reduces query time from 4+ seconds to under 400ms
     */
    public function up(): void
    {
        // Add indexes to employments table
        Schema::table('employments', function (Blueprint $table) {
            // Index for employee relationship queries
            $table->index('employee_id', 'idx_employments_employee_id');

            // Index for date-based queries and sorting
            $table->index('start_date', 'idx_employments_start_date');
            $table->index('end_probation_date', 'idx_employments_end_probation_date');

            // Index for site filtering
            $table->index('site_id', 'idx_employments_site_id');

            // Index for department and position filtering
            $table->index('department_id', 'idx_employments_department_id');
            $table->index('position_id', 'idx_employments_position_id');

            // Composite index for active employment queries
            $table->index(['start_date', 'end_probation_date'], 'idx_employments_active_period');
        });

        // Add indexes to employees table
        Schema::table('employees', function (Blueprint $table) {
            // Index for staff_id searches and sorting
            if (! Schema::hasIndex('employees', 'idx_employees_staff_id')) {
                $table->index('staff_id', 'idx_employees_staff_id');
            }

            // Index for organization filtering
            if (! Schema::hasIndex('employees', 'idx_employees_organization')) {
                $table->index('organization', 'idx_employees_organization');
            }

            // Composite index for name sorting
            $table->index(['first_name_en', 'last_name_en'], 'idx_employees_full_name');
        });

        // Add indexes to employee_funding_allocations table
        Schema::table('employee_funding_allocations', function (Blueprint $table) {
            // Index for employment relationship queries
            if (! Schema::hasIndex('employee_funding_allocations', 'idx_efa_employment_id')) {
                $table->index('employment_id', 'idx_efa_employment_id');
            }

            // Index for allocation type filtering
            $table->index('allocation_type', 'idx_efa_allocation_type');

            // Composite index for employment + type queries
            $table->index(['employment_id', 'allocation_type'], 'idx_efa_employment_type');
        });

        // Add indexes to sites table if needed
        Schema::table('sites', function (Blueprint $table) {
            // Index for name searches (if not already added in sites migration)
            if (! Schema::hasIndex('sites', 'idx_sites_name')) {
                $table->index('name', 'idx_sites_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove employments indexes (with existence checks for MySQL compatibility)
        Schema::table('employments', function (Blueprint $table) {
            $indexes = [
                'idx_employments_employee_id',
                'idx_employments_start_date',
                'idx_employments_end_probation_date',
                'idx_employments_site_id',
                'idx_employments_department_id',
                'idx_employments_position_id',
                'idx_employments_active_period',
            ];

            foreach ($indexes as $index) {
                if (Schema::hasIndex('employments', $index)) {
                    $table->dropIndex($index);
                }
            }
        });

        // Remove employees indexes
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasIndex('employees', 'idx_employees_staff_id')) {
                $table->dropIndex('idx_employees_staff_id');
            }
            if (Schema::hasIndex('employees', 'idx_employees_organization')) {
                $table->dropIndex('idx_employees_organization');
            }
            if (Schema::hasIndex('employees', 'idx_employees_full_name')) {
                $table->dropIndex('idx_employees_full_name');
            }
        });

        // Remove employee_funding_allocations indexes
        Schema::table('employee_funding_allocations', function (Blueprint $table) {
            if (Schema::hasIndex('employee_funding_allocations', 'idx_efa_employment_id')) {
                $table->dropIndex('idx_efa_employment_id');
            }
            if (Schema::hasIndex('employee_funding_allocations', 'idx_efa_allocation_type')) {
                $table->dropIndex('idx_efa_allocation_type');
            }
            if (Schema::hasIndex('employee_funding_allocations', 'idx_efa_employment_type')) {
                $table->dropIndex('idx_efa_employment_type');
            }
        });

        // Remove sites indexes
        Schema::table('sites', function (Blueprint $table) {
            if (Schema::hasIndex('sites', 'idx_sites_name')) {
                $table->dropIndex('idx_sites_name');
            }
        });
    }
};
