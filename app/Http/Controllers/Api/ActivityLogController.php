<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get paginated activity logs with optional filters
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'subject_type' => 'string|nullable',
                'subject_id' => 'integer|nullable',
                'user_id' => 'integer|nullable',
                'action' => 'string|nullable',
                'date_from' => 'date|nullable',
                'date_to' => 'date|nullable',
            ]);

            $perPage = $validated['per_page'] ?? 20;

            $query = ActivityLog::with('user:id,name,email')
                ->latest('created_at');

            // Apply filters
            if (! empty($validated['subject_type'])) {
                $query->bySubjectType($validated['subject_type']);
            }

            if (! empty($validated['subject_id']) && ! empty($validated['subject_type'])) {
                $query->forSubject($validated['subject_type'], $validated['subject_id']);
            }

            if (! empty($validated['user_id'])) {
                $query->byUser($validated['user_id']);
            }

            if (! empty($validated['action'])) {
                $query->byAction($validated['action']);
            }

            if (! empty($validated['date_from']) || ! empty($validated['date_to'])) {
                $query->dateRange(
                    $validated['date_from'] ?? null,
                    $validated['date_to'] ?? null
                );
            }

            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'has_more_pages' => $logs->hasMorePages(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get activity logs for a specific subject
     */
    public function forSubject(Request $request, string $type, int $id)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
            ]);

            $perPage = $validated['per_page'] ?? 20;

            $logs = ActivityLog::with('user:id,name,email')
                ->forSubject($type, $id)
                ->latest('created_at')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Activity logs for subject retrieved successfully',
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'has_more_pages' => $logs->hasMorePages(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity logs for subject',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent activity logs across all types
     */
    public function recent(Request $request)
    {
        try {
            $validated = $request->validate([
                'limit' => 'integer|min:1|max:100',
            ]);

            $limit = $validated['limit'] ?? 50;

            $logs = ActivityLog::with('user:id,name,email')
                ->latest('created_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Recent activity logs retrieved successfully',
                'data' => $logs,
                'count' => $logs->count(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent activity logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

