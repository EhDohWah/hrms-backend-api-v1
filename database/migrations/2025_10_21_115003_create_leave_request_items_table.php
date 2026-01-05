<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create leave_request_items table
        Schema::create('leave_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_request_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->decimal('days', 8, 2);
            $table->timestamps();

            // Foreign keys with SQL Server compatible syntax
            $table->foreign('leave_request_id')
                ->references('id')->on('leave_requests')
                ->onDelete('cascade');

            $table->foreign('leave_type_id')
                ->references('id')->on('leave_types')
                ->onDelete('no action');

            // Unique constraint to prevent duplicate leave types in one request
            $table->unique(['leave_request_id', 'leave_type_id'], 'unique_request_leave_type');

            // Index for performance
            $table->index(['leave_request_id', 'leave_type_id']);
        });

        // Migrate existing leave_requests data to leave_request_items
        $this->migrateExistingData();

        // Remove leave_type_id column from leave_requests table
        Schema::table('leave_requests', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['leave_type_id']);
            // Drop the column
            $table->dropColumn('leave_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Add leave_type_id column back to leave_requests
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('leave_type_id')->nullable()->after('employee_id')->constrained('leave_types');
        });

        // Migrate data back from leave_request_items to leave_requests
        try {
            $items = DB::table('leave_request_items')
                ->select('leave_request_id', 'leave_type_id', 'days')
                ->get();

            foreach ($items->groupBy('leave_request_id') as $leaveRequestId => $requestItems) {
                // Use the first item's leave_type_id (for simplicity in rollback)
                $firstItem = $requestItems->first();
                if ($firstItem) {
                    DB::table('leave_requests')
                        ->where('id', $leaveRequestId)
                        ->update(['leave_type_id' => $firstItem->leave_type_id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to migrate data back to leave_requests: '.$e->getMessage());
        }

        // Drop leave_request_items table
        Schema::dropIfExists('leave_request_items');
    }

    /**
     * Migrate existing leave_requests data to leave_request_items
     */
    private function migrateExistingData(): void
    {
        try {
            // Get all existing leave requests that have a leave_type_id
            $leaveRequests = DB::table('leave_requests')
                ->whereNotNull('leave_type_id')
                ->select('id', 'leave_type_id', 'total_days')
                ->get();

            $itemsToInsert = [];
            $now = now();

            foreach ($leaveRequests as $request) {
                $itemsToInsert[] = [
                    'leave_request_id' => $request->id,
                    'leave_type_id' => $request->leave_type_id,
                    'days' => $request->total_days,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Batch insert all items
            if (! empty($itemsToInsert)) {
                // Insert in chunks to avoid memory issues
                foreach (array_chunk($itemsToInsert, 500) as $chunk) {
                    DB::table('leave_request_items')->insert($chunk);
                }

                Log::info('Successfully migrated '.count($itemsToInsert).' leave request records to leave_request_items table');
            }
        } catch (\Exception $e) {
            Log::error('Failed to migrate existing leave request data: '.$e->getMessage());
            throw $e;
        }
    }
};
