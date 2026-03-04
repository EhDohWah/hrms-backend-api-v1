<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationCategory;
use App\Http\Requests\Notification\BulkDestroyNotificationsRequest;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages user notifications including listing, reading, and bulk operations.
 *
 * Notifications use Laravel's built-in DatabaseNotification (UUID IDs).
 * All queries are scoped to the authenticated user for security.
 */
class NotificationController extends BaseApiController
{
    /**
     * Get user notifications with pagination and filtering.
     */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min($validated['per_page'] ?? 20, 100);

        $query = $request->user()->notifications();

        // Filter by category (from JSON data column)
        if ($category = $validated['category'] ?? null) {
            $query->where(function ($q) use ($category) {
                $q->whereRaw("JSON_VALUE(data, '$.category') = ?", [$category])
                    ->orWhere('category', $category);
            });
        }

        // Filter by read status
        if ($readStatus = $validated['read_status'] ?? null) {
            if ($readStatus === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($readStatus === 'unread') {
                $query->whereNull('read_at');
            }
        }

        // Search in message
        if ($search = $validated['search'] ?? null) {
            $query->whereRaw("JSON_VALUE(data, '$.message') LIKE ?", ["%{$search}%"]);
        }

        // Sorting — reorder to override any default ordering
        $sortField = $validated['sort'] ?? 'created_at';
        $sortOrder = $validated['order'] ?? 'desc';

        if (in_array($sortField, ['created_at', 'read_at'])) {
            $query->reorder($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications)
            ->additional([
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ])
            ->response();
    }

    /**
     * Get a single notification by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        return NotificationResource::make($notification)
            ->additional(['success' => true, 'message' => 'Notification retrieved successfully'])
            ->response();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return $this->successResponse(
            new NotificationResource($notification->fresh()),
            'Notification marked as read'
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications->markAsRead();

        return $this->successResponse(
            ['marked_count' => $count],
            "{$count} notifications marked as read"
        );
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return $this->successResponse(null, 'Notification deleted');
    }

    /**
     * Delete multiple notifications.
     */
    public function bulkDestroy(BulkDestroyNotificationsRequest $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->whereIn('id', $request->validated('ids'))
            ->delete();

        return $this->successResponse(
            ['deleted_count' => $count],
            "{$count} notifications deleted"
        );
    }

    /**
     * Delete all read notifications.
     */
    public function clearRead(Request $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->whereNotNull('read_at')
            ->delete();

        return $this->successResponse(
            ['deleted_count' => $count],
            "{$count} read notifications cleared"
        );
    }

    /**
     * Get notification statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $total = $user->notifications()->count();
        $unread = $user->unreadNotifications()->count();

        // Count by category
        $byCategory = $user->notifications()
            ->selectRaw("JSON_VALUE(data, '$.category') as category, COUNT(*) as count")
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        return $this->successResponse([
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
            'by_category' => $byCategory,
        ], 'Notification statistics retrieved successfully');
    }

    /**
     * Get available categories for filtering.
     */
    public function filterOptions(): JsonResponse
    {
        return $this->successResponse(
            ['categories' => NotificationCategory::toArray()],
            'Filter options retrieved successfully'
        );
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse(
            ['count' => $request->user()->unreadNotifications()->count()],
            'Unread count retrieved successfully'
        );
    }
}
