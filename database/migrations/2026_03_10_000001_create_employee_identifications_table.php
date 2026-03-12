<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('identification_type', 50);
            $table->string('identification_number', 50);
            $table->date('identification_issue_date')->nullable();
            $table->date('identification_expiry_date')->nullable();
            $table->string('first_name_en', 255)->nullable();
            $table->string('last_name_en', 255)->nullable();
            $table->string('first_name_th', 255)->nullable();
            $table->string('last_name_th', 255)->nullable();
            $table->string('initial_en', 10)->nullable();
            $table->string('initial_th', 10)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->index('employee_id', 'idx_emp_ident_employee');
            $table->index('is_primary', 'idx_emp_ident_primary');
            $table->index(['employee_id', 'is_primary'], 'idx_emp_ident_employee_primary');
        });

        DB::statement("
            INSERT INTO employee_identifications (
                employee_id, identification_type, identification_number,
                identification_issue_date, identification_expiry_date,
                first_name_en, last_name_en, first_name_th, last_name_th,
                initial_en, initial_th, is_primary,
                created_by, created_at, updated_at
            )
            SELECT
                id, identification_type, identification_number,
                identification_issue_date, identification_expiry_date,
                first_name_en, last_name_en, first_name_th, last_name_th,
                initial_en, initial_th, 1,
                'migration', GETDATE(), GETDATE()
            FROM employees
            WHERE identification_type IS NOT NULL
              AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_identifications');
    }
};
