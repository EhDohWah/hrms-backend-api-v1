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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Unique module identifier (e.g., users, employees)');
            $table->string('display_name')->comment('Display name in UI (e.g., Users, Employees)');
            $table->text('description')->nullable()->comment('Module description');
            $table->string('icon')->nullable()->comment('Icon class for menu');
            $table->string('category')->nullable()->comment('Category grouping (e.g., Administration, HRM)');
            $table->string('route')->nullable()->comment('Frontend route path');
            $table->string('active_link')->nullable()->comment('Active link identifier for menu highlighting');
            $table->string('parent_module')->nullable()->comment('Parent module name for hierarchical structure');
            $table->boolean('is_parent')->default(false)->comment('Is this a parent menu (has submenus)');
            $table->string('read_permission')->comment('Permission name for read access (e.g., users.read)');
            $table->json('edit_permissions')->comment('Array of permission names for edit access');
            $table->integer('order')->default(0)->comment('Display order');
            $table->boolean('is_active')->default(true)->comment('Is module active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
