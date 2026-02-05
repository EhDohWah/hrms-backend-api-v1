<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get user notifications with pagination and filtering
     *
     * Query Parameters:
     * - per_page: Number of items per page (default: 20, max: 100)
     * - page: Page number
     * - category: Filter by category (e.g., 'employee', 'grants', 'payroll')
     * - read_status: Filter by read status ('read', 'unread', 'all')
     * - search: Search in notification message
     * - sort: Sort field (default: 'created_at')
     * - order: Sort order ('asc', 'desc', default: 'desc')
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 20), 100);

        $query = $request->user()->notifications();

        // Filter by category (from JSON data column)
        if ($category = $request->input('category')) {
            $query->where(function ($q) use ($category) {
                // Check if category is in the data JSON field
                $q->whereRaw("JSON_VALUE(data, '$.category') = ?", [$category])
                    ->orWhere('category', $category);
            });
        }

        // Filter by read status
        if ($readStatus = $request->input('read_status')) {
            if ($readStatus === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($readStatus === 'unread') {
                $query->whereNull('read_at');
            }
            // 'all' returns everything
        }

        // Search in message
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw("JSON_VALUE(data, '$.message') LIKE ?", ["%{$search}%"]);
            });
        }

        // Sorting - reorder to override any default ordering
        $sortField = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');
        $allowedSortFields = ['created_at', 'read_at'];

        if (in_array($sortField, $allowedSortFields)) {
            // Use reorder() to clear any existing order clauses first (prevents duplicate ORDER BY)
            $query->reorder($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $notifications = $query->paginate($perPage);

        // Transform notifications to include parsed data
        $notifications->getCollection()->transform(function ($notification) {
            return $this->transformNotification($notification);
        });

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->getUnreadCount(request()->user()),
            ],
        ]);
    }

    /**
     * Get a single notification by ID
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformNotification($notification),
        ]);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $this->transformNotification($notification->fresh()),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marked as read",
            'marked_count' => $count,
        ]);
    }

    /**
     * Delete a single notification
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Delete multiple notifications
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|string',
        ]);

        $count = $request->user()
            ->notifications()
            ->whereIn('id', $request->input('ids'))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications deleted",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Delete all read notifications
     */
    public function clearRead(Request $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->whereNotNull('read_at')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} read notifications cleared",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $total = $user->notifications()->count();
        $unread = $user->unreadNotifications()->count();
        $read = $total - $unread;

        // Count by category
        $byCategory = $user->notifications()
            ->selectRaw("JSON_VALUE(data, '$.category') as category, COUNT(*) as count")
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'unread' => $unread,
                'read' => $read,
                'by_category' => $byCategory,
            ],
        ]);
    }

    /**
     * Get available categories for filtering
     */
    public function filterOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => NotificationCategory::toArray(),
            ],
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'count' => $this->getUnreadCount($request->user()),
            ],
        ]);
    }

    /**
     * Transform notification for response
     */
    private function transformNotification($notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $data,
            'message' => $data['message'] ?? null,
            'category' => $data['category'] ?? $notification->category ?? 'general',
            'category_label' => $data['category_label'] ?? null,
            'category_icon' => $data['category_icon'] ?? null,
            'category_color' => $data['category_color'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
        ];
    }

    /**
     * Get unread count for user
     */
    private function getUnreadCount($user): int
    {
        return $user->unreadNotifications()->count();
    }
}
