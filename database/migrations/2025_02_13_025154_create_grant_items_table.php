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
        Schema::create('grant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('grants')->onDelete('cascade');
            $table->string('grant_position')->nullable();
            $table->decimal('grant_salary', 15, 2)->nullable();
            $table->decimal('grant_benefit', 15, 2)->nullable();
            $table->decimal('grant_level_of_effort', 5, 2)->nullable();
            $table->integer('grant_position_number')->nullable();

            // Budget line code moved from position_slots to grant_items
            // Can be NULL for General Fund (hub grants) which don't have budget line codes
            // Can be any format: 1.2.2.1, BL-001, A.B.C, etc. - no restrictions
            $table->string('budgetline_code', 255)->nullable();

            $table->timestamps();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();

            // Note: No unique constraint on budgetline_code + position
            // General Fund items have NULL budget line codes, so duplicates would occur
            // Uniqueness is enforced at application level for items WITH budget line codes
        });

        // insert the default grant items
        // $this->insertDefaultGrantItems();
    }

    // // create a function to insert the default grant items
    // public function insertDefaultGrantItems()
    // {
    //     // insert the default grant items
    //     DB::table('grant_items')->insert([
    //         [
    //             'grant_id' => 1,
    //             'grant_position' => "SMRU Staff",
    //             'grant_salary' => null,
    //             'grant_benefit' => null,
    //             'grant_level_of_effort' => null,
    //             'grant_position_number' => null,
    //             'created_by' => 'system',
    //             'updated_by' => 'system',
    //         ],
    //         [
    //             'grant_id' => 2,
    //             'grant_position' => "BHF Staff",
    //             'grant_salary' => null,
    //             'grant_benefit' => null,
    //             'grant_level_of_effort' => null,
    //             'grant_position_number' => null,
    //             'created_by' => 'system',
    //             'updated_by' => 'system',
    //         ]
    //     ]);
    // }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_items');
    }
};
