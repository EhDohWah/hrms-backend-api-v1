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
            $table->string('subsidiary');
            $table->text('description')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

          // Insert the two default “hub” grants
          DB::table('grants')->insert([
            [
                'code'         => 'S0031',
                'name'         => 'Other Fund',
                'subsidiary'   => 'SMRU',
                'description'  => "SMRU's hub grant",
                'end_date'     => null,
                'created_at'   => now(),
                'updated_at'   => now(),
                'created_by'   => 'system',
                'updated_by'   => 'system',
            ],
            [
                'code'         => 'B24002',
                'name'         => 'General Fund',
                'subsidiary'   => 'BHF',
                'description'  => "BHF's hub grant",
                'end_date'     => null,
                'created_at'   => now(),
                'updated_at'   => now(),
                'created_by'   => 'system',
                'updated_by'   => 'system',
            ],
        ]);
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

        // Insert default subsidiary hub funds
        DB::table('subsidiary_hub_funds')->insert([
            [
                'subsidiary'   => 'SMRU',
                'hub_grant_id' => $smruGrant,
                'created_at'   => now(),
                'updated_at'   => now(),
                'created_by'   => 'system',
                'updated_by'   => 'system',
            ],
            [
                'subsidiary'   => 'BHF',
                'hub_grant_id' => $bhfGrant,
                'created_at'   => now(),
                'updated_at'   => now(),
                'created_by'   => 'system',
                'updated_by'   => 'system',
            ],
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
