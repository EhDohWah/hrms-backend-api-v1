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
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->increments('id');

            // Matching "VARCHAR(200)" and allowing NULL
            $table->string('title', 200)->nullable();

            // Using longText to accommodate large content (NVARCHAR(MAX))
            $table->longText('content')->nullable();

            // If you want timestamps with millisecond precision (DATETIME2(3) equivalent in SQL Server):
            // $table->dateTime('created_at', 3)->nullable();
            // $table->dateTime('updated_at', 3)->nullable();
            //
            // Alternatively, Laravel's timestamps with precision:
            $table->timestamps(3);

            // Matching "VARCHAR(100)" and allowing NULL
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
