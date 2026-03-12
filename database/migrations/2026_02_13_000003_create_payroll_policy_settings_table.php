<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_policy_settings', function (Blueprint $table) {
            $table->id();

            $table->string('policy_key', 100);
            $table->decimal('policy_value', 10, 2)->nullable();
            $table->string('setting_type', 50)->default('numeric');
            $table->string('category', 50)->nullable();
            $table->string('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('policy_key');
            $table->index('category');
            $table->index('effective_date');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_policy_settings');
    }
};
