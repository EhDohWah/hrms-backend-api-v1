<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $table->string('subsidiary');
            $table->text('description')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Composite indexes for optimized filtering & sorting
            $table->index(['subsidiary', 'code'], 'idx_grants_subsidiary_code');
            $table->index(['subsidiary', 'end_date', 'id'], 'idx_grants_subsidiary_end_date_id');
        });

        $this->insertDefaultGrants();
    }

    // create a function to insert the two default "hub" grants
    public function insertDefaultGrants()
    {
        // Insert default grants
        $smruGrant = DB::table('grants')->insertGetId([
            'code'         => 'S0031',
            'name'         => 'Other Fund',
            'subsidiary'   => 'SMRU',
            'description'  => "SMRU's hub grant",
            'end_date'     => null,
            'created_at'   => now(),
            'updated_at'   => now(),
            'created_by'   => 'system',
            'updated_by'   => 'system',
        ]);

        $bhfGrant = DB::table('grants')->insertGetId([
            'code'         => 'S22001',
            'name'         => 'General Fund',
            'subsidiary'   => 'BHF',
            'description'  => "BHF's hub grant",
            'end_date'     => null,
            'created_at'   => now(),
            'updated_at'   => now(),
            'created_by'   => 'system',
            'updated_by'   => 'system',
        ]);

    }





    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grants');
    }
};
