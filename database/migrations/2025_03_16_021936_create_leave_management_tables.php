<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Leave Types
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('default_duration', 18, 2)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Leave Requests
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id'); // use unsignedBigInteger to match employees.id
            $table->unsignedBigInteger('leave_type_id'); // use unsignedBigInteger to match leave_types.id
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_days', 18, 2)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 50)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('cascade');

            $table->foreign('leave_type_id')
                  ->references('id')
                  ->on('leave_types')
                  ->onDelete('cascade');
        });

        // Leave Balances
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id'); // match employees.id type
            $table->unsignedBigInteger('leave_type_id'); // use unsignedBigInteger to match leave_types.id
            $table->decimal('remaining_days', 18, 2)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('cascade');

            $table->foreign('leave_type_id')
                  ->references('id')
                  ->on('leave_types')
                  ->onDelete('cascade');
        });

        // Leave Request Approvals
        Schema::create('leave_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_request_id'); // use unsignedBigInteger to match leave_requests.id
            $table->string('approver_role', 100)->nullable();
            $table->string('approver_name', 200)->nullable();
            $table->string('approver_signature', 200)->nullable();
            $table->date('approval_date')->nullable();
            $table->string('status', 50)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('leave_request_id')
                  ->references('id')
                  ->on('leave_requests')
                  ->onDelete('cascade');
        });

        // Traditional Leaves
        Schema::create('traditional_leaves', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->text('description')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop tables in reverse order to avoid foreign key conflicts
        Schema::dropIfExists('traditional_leaves');
        Schema::dropIfExists('leave_request_approvals');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
