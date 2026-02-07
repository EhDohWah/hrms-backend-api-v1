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
        Schema::create('deletion_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('deletion_key', 40)->unique();
            $table->string('root_model');
            $table->unsignedBigInteger('root_id');
            $table->string('root_display_name')->nullable();
            $table->json('snapshot_keys');
            $table->json('table_order');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['root_model', 'root_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletion_manifests');
    }
};
