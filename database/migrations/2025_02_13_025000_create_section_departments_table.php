<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('section_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'department_id', 'deleted_at']);
            $table->index(['department_id', 'is_active', 'deleted_at']);
        });

        // Note: Seeding will be done via SectionDepartmentSeeder after lookups table exists
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_departments');
    }
};
