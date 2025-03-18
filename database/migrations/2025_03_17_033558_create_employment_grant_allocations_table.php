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
        Schema::create('employment_grant_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employment_id');
            $table->unsignedBigInteger('grant_items_id');
            $table->decimal('level_of_effort', 4, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Foreign keys
            $table->foreign('employment_id')
                  ->references('id')->on('employments')
                  ->onDelete('cascade');

            $table->foreign('grant_items_id')
                  ->references('id')->on('grant_items')
                  ->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_grant_allocations');
    }
};
