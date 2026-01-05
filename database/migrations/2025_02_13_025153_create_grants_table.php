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
        // Create the grants table
        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('organization');
            $table->text('description')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Composite indexes for optimized filtering & sorting
            $table->index(['organization', 'code'], 'idx_grants_organization_code');
            $table->index(['organization', 'end_date', 'id'], 'idx_grants_organization_end_date_id');
        });

        // Removed: Default hub grants are now imported via Excel
        // Previously created: S0031 (SMRU Other Fund) and S22001 (BHF General Fund)
        // These hub grants (General Fund/Organization Saving Grants) should be imported from Excel
        // along with their grant items that don't have budget line codes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grants');
    }
};
