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
            $table->index('end_date', 'idx_employments_end_date');

            // Index for work location filtering
            $table->index('work_location_id', 'idx_employments_work_location_id');

            // Index for department position filtering
            $table->index('department_position_id', 'idx_employments_department_position_id');

            // Index for employment type filtering
            $table->index('employment_type', 'idx_employments_employment_type');

            // Composite index for active employment queries
            $table->index(['start_date', 'end_date'], 'idx_employments_active_period');
        });

        // Add indexes to employees table
        Schema::table('employees', function (Blueprint $table) {
            // Index for staff_id searches and sorting
            if (! Schema::hasIndex('employees', 'idx_employees_staff_id')) {
                $table->index('staff_id', 'idx_employees_staff_id');
            }

            // Index for subsidiary filtering
            if (! Schema::hasIndex('employees', 'idx_employees_subsidiary')) {
                $table->index('subsidiary', 'idx_employees_subsidiary');
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

        // Add indexes to work_locations table if needed
        Schema::table('work_locations', function (Blueprint $table) {
            // Index for name searches
            if (! Schema::hasIndex('work_locations', 'idx_work_locations_name')) {
                $table->index('name', 'idx_work_locations_name');
            }
        });

        // Add indexes to department_positions table
        Schema::table('department_positions', function (Blueprint $table) {
            // Index for department filtering
            $table->index('department', 'idx_department_positions_department');

            // Index for position filtering
            $table->index('position', 'idx_department_positions_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove employments indexes
        Schema::table('employments', function (Blueprint $table) {
            $table->dropIndex('idx_employments_employee_id');
            $table->dropIndex('idx_employments_start_date');
            $table->dropIndex('idx_employments_end_date');
            $table->dropIndex('idx_employments_work_location_id');
            $table->dropIndex('idx_employments_department_position_id');
            $table->dropIndex('idx_employments_employment_type');
            $table->dropIndex('idx_employments_active_period');
        });

        // Remove employees indexes
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasIndex('employees', 'idx_employees_staff_id')) {
                $table->dropIndex('idx_employees_staff_id');
            }
            if (Schema::hasIndex('employees', 'idx_employees_subsidiary')) {
                $table->dropIndex('idx_employees_subsidiary');
            }
            $table->dropIndex('idx_employees_full_name');
        });

        // Remove employee_funding_allocations indexes
        Schema::table('employee_funding_allocations', function (Blueprint $table) {
            if (Schema::hasIndex('employee_funding_allocations', 'idx_efa_employment_id')) {
                $table->dropIndex('idx_efa_employment_id');
            }
            $table->dropIndex('idx_efa_allocation_type');
            $table->dropIndex('idx_efa_employment_type');
        });

        // Remove work_locations indexes
        Schema::table('work_locations', function (Blueprint $table) {
            if (Schema::hasIndex('work_locations', 'idx_work_locations_name')) {
                $table->dropIndex('idx_work_locations_name');
            }
        });

        // Remove department_positions indexes
        Schema::table('department_positions', function (Blueprint $table) {
            $table->dropIndex('idx_department_positions_department');
            $table->dropIndex('idx_department_positions_position');
        });
    }
};
