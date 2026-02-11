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
        Schema::create('organization_hub_funds', function (Blueprint $table) {
            $table->id();
            $table->string('organization', 5)->unique();
            $table->foreignId('hub_grant_id')
                ->constrained('grants');
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            // Index for FK column (SQL Server does NOT auto-create these)
            $table->index('hub_grant_id', 'idx_ohf_hub_grant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_hub_funds');
    }
};
