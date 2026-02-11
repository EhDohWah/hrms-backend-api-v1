<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Leave Types - Enhanced
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('default_duration', 18, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Leave Requests - Enhanced with approval fields
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 18, 2);
            $table->text('reason')->nullable();
            $table->string('status', 50)->default('pending');

            // Approval fields - directly in the leave_requests table
            $table->boolean('supervisor_approved')->default(false);
            $table->date('supervisor_approved_date')->nullable();
            $table->boolean('hr_site_admin_approved')->default(false);
            $table->date('hr_site_admin_approved_date')->nullable();

            // Attachment notes as simple text field
            $table->text('attachment_notes')->nullable();

            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Indexes for performance
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Leave Balances - Enhanced
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->decimal('total_days', 18, 2)->default(0);
            $table->decimal('used_days', 18, 2)->default(0);
            $table->decimal('remaining_days', 18, 2)->default(0);
            $table->year('year')->default(DB::raw('YEAR(GETDATE())'));
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Unique constraint to prevent duplicate records
            $table->unique(['employee_id', 'leave_type_id', 'year']);

            // Indexes for performance
            $table->index(['employee_id', 'year']);
            $table->index('leave_type_id', 'idx_leave_bal_type');
        });

        // Migrate existing approval data to new structure
        $this->migrateApprovalData();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop in this order to respect foreign key constraints:
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }

    /**
     * Migrate existing approval data from leave_request_approvals table to leave_requests columns
     * This method safely handles cases where the old table doesn't exist
     */
    private function migrateApprovalData()
    {
        // Check if the old approval table exists (in case this is a fresh install)
        if (! Schema::hasTable('leave_request_approvals')) {
            return;
        }

        try {
            // Migrate approval data to the new structure
            $approvals = DB::table('leave_request_approvals')
                ->orderBy('leave_request_id')
                ->orderBy('created_at')
                ->get();

            foreach ($approvals->groupBy('leave_request_id') as $leaveRequestId => $requestApprovals) {
                $updateData = [];

                foreach ($requestApprovals as $approval) {
                    // Map approval roles to specific fields
                    $role = strtolower($approval->approver_role ?? '');

                    if (str_contains($role, 'supervisor') || str_contains($role, 'manager')) {
                        $updateData['supervisor_approved'] = true;
                        $updateData['supervisor_approved_date'] = $approval->approval_date;
                    } elseif (str_contains($role, 'hr') || str_contains($role, 'admin') || str_contains($role, 'site')) {
                        // Consolidate HR and Site Admin approvals into single field
                        $updateData['hr_site_admin_approved'] = true;
                        $updateData['hr_site_admin_approved_date'] = $approval->approval_date;
                    } else {
                        // Default to supervisor if role is unclear
                        if (empty($updateData['supervisor_approved'])) {
                            $updateData['supervisor_approved'] = true;
                            $updateData['supervisor_approved_date'] = $approval->approval_date;
                        }
                    }
                }

                // Update the leave request with approval data
                if (! empty($updateData)) {
                    DB::table('leave_requests')
                        ->where('id', $leaveRequestId)
                        ->update($updateData);
                }
            }

            // Drop the old approval table after migration
            Schema::dropIfExists('leave_request_approvals');

        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            Log::error('Failed to migrate approval data: '.$e->getMessage());
        }
    }
};
