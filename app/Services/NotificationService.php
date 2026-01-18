<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * NotificationService
 *
 * Centralized service for dispatching notifications with:
 * - Module-based categorization
 * - Permission-aware recipient filtering
 */
class NotificationService
{
    /**
     * Send notification to users with permission to access the module
     *
     * @param  string  $module  Module name from ModuleSeeder (e.g., 'employee-management', 'grant-management')
     * @param  Notification  $notification  The notification instance
     * @param  string|null  $action  Action type (created, updated, deleted) - for logging purposes
     * @param  User|null  $excludeUser  Optional user to exclude (typically the performer)
     * @return int Number of users notified
     */
    public function notifyByModule(
        string $module,
        Notification $notification,
        ?string $action = null,
        ?User $excludeUser = null
    ): int {
        $recipients = $this->getRecipientsForModule($module, $excludeUser);

        // If no recipients found via permissions, fall back to notifying all users
        // This ensures notifications are sent even if permissions aren't fully configured
        if ($recipients->isEmpty()) {
            Log::info("[NotificationService] No recipients found for module: {$module}" . ($action ? " (action: {$action})" : '') . " - falling back to all users");
            
            $query = User::query();
            if ($excludeUser) {
                $query->where('id', '!=', $excludeUser->id);
            }
            $recipients = $query->get();
        }

        if ($recipients->isEmpty()) {
            Log::warning("[NotificationService] No users to notify for module: {$module}");
            return 0;
        }

        // Send notifications
        foreach ($recipients as $user) {
            try {
                $user->notify($notification);
            } catch (\Exception $e) {
                Log::error("[NotificationService] Failed to notify user {$user->id}: {$e->getMessage()}");
            }
        }

        Log::info("[NotificationService] Notified {$recipients->count()} users for module: {$module}" . ($action ? " (action: {$action})" : ''));

        return $recipients->count();
    }

    /**
     * Send notification to a specific user
     *
     * @param  User  $user  The target user
     * @param  Notification  $notification  The notification instance
     * @return bool Success status
     */
    public function notifyUser(User $user, Notification $notification): bool
    {
        try {
            $user->notify($notification);
            Log::info("[NotificationService] Notified user {$user->id}");

            return true;
        } catch (\Exception $e) {
            Log::error("[NotificationService] Failed to notify user {$user->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Send notification to multiple specific users
     *
     * @param  Notification  $notification  The notification instance
     * @param  Collection|array  $users  Collection or array of User models
     * @return int Number of users notified
     */
    public function notifyUsers(Notification $notification, Collection|array $users): int
    {
        $users = collect($users);
        $count = 0;

        foreach ($users as $user) {
            if ($this->notifyUser($notification, $user)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send notification to all users (use sparingly!)
     *
     * @param  Notification  $notification  The notification instance
     * @param  User|null  $excludeUser  Optional user to exclude
     * @return int Number of users notified
     */
    public function notifyAll(Notification $notification, ?User $excludeUser = null): int
    {
        $query = User::query();

        if ($excludeUser) {
            $query->where('id', '!=', $excludeUser->id);
        }

        $recipients = $query->get();

        return $this->notifyUsers($notification, $recipients);
    }

    /**
     * Send notification to users with specific roles
     *
     * @param  Notification  $notification  The notification instance
     * @param  array|string  $roles  Role names
     * @param  User|null  $excludeUser  Optional user to exclude
     * @return int Number of users notified
     */
    public function notifyByRoles(
        Notification $notification,
        array|string $roles,
        ?User $excludeUser = null
    ): int {
        $roles = is_array($roles) ? $roles : [$roles];

        $query = User::whereHas('roles', function ($q) use ($roles) {
            $q->whereIn('name', $roles);
        });

        if ($excludeUser) {
            $query->where('id', '!=', $excludeUser->id);
        }

        $recipients = $query->get();

        return $this->notifyUsers($notification, $recipients);
    }

    /**
     * Get recipients who have permission to access a module
     *
     * @param  string  $module  Module name
     * @param  User|null  $excludeUser  User to exclude
     * @return Collection Users with access to the module
     */
    public function getRecipientsForModule(string $module, ?User $excludeUser = null): Collection
    {
        // Build the read permission name
        $readPermission = "{$module}.read";

        // Find users with the permission (through roles)
        $query = User::whereHas('roles.permissions', function ($q) use ($readPermission) {
            $q->where('name', $readPermission);
        });

        // Or users with direct permission (if using direct permissions)
        $query->orWhereHas('permissions', function ($q) use ($readPermission) {
            $q->where('name', $readPermission);
        });

        // Exclude specified user
        if ($excludeUser) {
            $query->where('id', '!=', $excludeUser->id);
        }

        return $query->get();
    }

    /**
     * Get category from module name
     *
     * @param  string  $module  Module name from ModuleSeeder
     * @return NotificationCategory
     */
    public function getCategoryFromModule(string $module): NotificationCategory
    {
        return NotificationCategory::fromModule($module);
    }

    /**
     * Get notification metadata for a module
     *
     * @param  string  $module  Module name
     * @return array Metadata array with category, module, and UI labels
     */
    public function getNotificationMetadata(string $module): array
    {
        $category = $this->getCategoryFromModule($module);

        return [
            'category' => $category->value,
            'module' => $module,
            'category_label' => $category->label(),
            'category_icon' => $category->icon(),
            'category_color' => $category->color(),
        ];
    }
}
