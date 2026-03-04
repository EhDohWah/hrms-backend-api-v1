<?php

namespace App\Services;

use App\Events\UserProfileUpdated;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserProfileService
{
    /**
     * Update the authenticated user's profile picture.
     */
    public function updateProfilePicture($file): array
    {
        $user = Auth::user();

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $file->store('profile_pictures', 'public');

        $user->profile_picture = $path;
        $user->save();

        $fullUrl = Storage::disk('public')->url($path);

        $this->broadcastSafely(new UserProfileUpdated($user->id, 'profile_picture', [
            'profile_picture' => $path,
            'profile_picture_url' => $fullUrl,
        ]));

        Log::info('Profile picture updated', ['user_id' => $user->id, 'path' => $path]);

        return [
            'profile_picture' => $path,
            'url' => $fullUrl,
        ];
    }

    /**
     * Update the authenticated user's name.
     */
    public function updateUsername(string $name): array
    {
        $user = Auth::user();
        $oldName = $user->name;
        $user->name = $name;
        $user->save();

        $this->broadcastSafely(new UserProfileUpdated($user->id, 'name', [
            'name' => $user->name,
            'old_name' => $oldName,
        ]));

        Log::info('Username updated', ['user_id' => $user->id, 'old_name' => $oldName, 'new_name' => $user->name]);

        return ['name' => $user->name];
    }

    /**
     * Update the authenticated user's email.
     */
    public function updateEmail(string $email): array
    {
        $user = Auth::user();
        $oldEmail = $user->email;
        $user->email = $email;
        $user->save();

        $this->broadcastSafely(new UserProfileUpdated($user->id, 'email', [
            'email' => $user->email,
        ]));

        Log::info('Email updated', ['user_id' => $user->id, 'old_email' => $oldEmail, 'new_email' => $user->email]);

        return ['email' => $user->email];
    }

    /**
     * Update the authenticated user's password.
     * Aborts with 400 if current password is incorrect.
     */
    public function updatePassword(string $currentPassword, string $newPassword): void
    {
        $user = Auth::user();

        if (! Hash::check($currentPassword, $user->password)) {
            abort(400, 'Current password is incorrect');
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        $this->broadcastSafely(new UserProfileUpdated($user->id, 'password', [
            'password_changed_at' => now()->toIso8601String(),
        ]));

        Log::info('Password updated', ['user_id' => $user->id]);
    }

    /**
     * Get current user's permissions in simplified read/edit format.
     */
    public function myPermissions(User $user): array
    {
        $modules = Module::active()->ordered()->get();

        $permissions = [];

        foreach ($modules as $module) {
            $hasRead = $user->can($module->read_permission);

            $permissionPrefix = str_replace('.read', '', $module->read_permission);
            $hasEdit = $user->can("{$permissionPrefix}.edit");

            if ($hasRead || $hasEdit) {
                $permissions[$module->name] = [
                    'read' => $hasRead,
                    'edit' => $hasEdit,
                    'display_name' => $module->display_name,
                    'category' => $module->category,
                    'icon' => $module->icon,
                    'route' => $module->route,
                ];
            }
        }

        return $permissions;
    }

    /**
     * Dispatch a broadcast event without letting connection failures
     * (e.g. Reverb/Pusher not running) break the API response.
     */
    private function broadcastSafely(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed: ' . $e->getMessage());
        }
    }
}
