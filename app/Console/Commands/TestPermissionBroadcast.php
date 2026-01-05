<?php

namespace App\Console\Commands;

use App\Events\UserPermissionsUpdated;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Test command to manually broadcast permission update event
 *
 * Usage: php artisan test:permission-broadcast {userId}
 */
class TestPermissionBroadcast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:permission-broadcast {userId : The ID of the user to broadcast to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test broadcasting a permission update event to a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('userId');

        // Verify user exists
        $user = User::find($userId);
        if (! $user) {
            $this->error("User with ID {$userId} not found!");

            return 1;
        }

        $this->info("Testing permission broadcast for user: {$user->name} (ID: {$userId})");
        $this->info("Channel: App.Models.User.{$userId}");
        $this->info('Event: user.permissions-updated');
        $this->newLine();

        try {
            // Dispatch the event
            $this->info('Dispatching UserPermissionsUpdated event...');
            event(new UserPermissionsUpdated($userId, 'Test Command', 'Manual test broadcast'));

            Log::info('Test permission broadcast dispatched', [
                'user_id' => $userId,
                'user_name' => $user->name,
                'channel' => "App.Models.User.{$userId}",
            ]);

            $this->newLine();
            $this->info('✅ Event dispatched successfully!');
            $this->newLine();
            $this->comment('If Reverb is running, check:');
            $this->comment('1. Reverb console for broadcast confirmation');
            $this->comment('2. Frontend browser console for received event');
            $this->comment('3. Laravel logs: storage/logs/laravel.log');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error broadcasting event: '.$e->getMessage());
            Log::error('Test permission broadcast failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
