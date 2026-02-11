<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * List all attendance records with pagination, filtering, and sorting.
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_employee_id' => 'integer|nullable',
                'filter_status' => 'string|nullable',
                'filter_date_from' => 'date|nullable',
                'filter_date_to' => 'date|nullable',
                'search' => 'string|nullable|max:255',
                'sort_by' => 'string|nullable|in:date,employee_name,clock_in,clock_out,status,total_hours,created_at',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            $query = Attendance::with([
                'employee:id,staff_id,first_name_en,last_name_en',
            ]);

            // Filter by employee
            if (! empty($validated['filter_employee_id'])) {
                $query->where('employee_id', $validated['filter_employee_id']);
            }

            // Filter by status (comma-separated)
            if (! empty($validated['filter_status'])) {
                $statuses = explode(',', $validated['filter_status']);
                $query->whereIn('status', $statuses);
            }

            // Filter by date range
            if (! empty($validated['filter_date_from'])) {
                $query->where('date', '>=', $validated['filter_date_from']);
            }

            if (! empty($validated['filter_date_to'])) {
                $query->where('date', '<=', $validated['filter_date_to']);
            }

            // Search by employee name or staff_id
            if (! empty($validated['search'])) {
                $searchTerm = $validated['search'];
                $query->whereHas('employee', function ($q) use ($searchTerm) {
                    $q->where(function ($sub) use ($searchTerm) {
                        $sub->where('first_name_en', 'like', '%'.$searchTerm.'%')
                            ->orWhere('last_name_en', 'like', '%'.$searchTerm.'%')
                            ->orWhere('staff_id', 'like', '%'.$searchTerm.'%');
                    });
                });
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'date';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            switch ($sortBy) {
                case 'employee_name':
                    $query->join('employees', 'attendances.employee_id', '=', 'employees.id')
                        ->whereNull('employees.deleted_at')
                        ->orderBy('employees.first_name_en', $sortOrder)
                        ->select('attendances.*');
                    break;
                case 'date':
                    $query->orderBy('attendances.date', $sortOrder);
                    break;
                case 'clock_in':
                case 'clock_out':
                case 'status':
                case 'total_hours':
                    $query->orderBy("attendances.{$sortBy}", $sortOrder);
                    break;
                default:
                    $query->orderBy('attendances.date', $sortOrder);
                    break;
            }

            $attendances = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'message' => 'Attendance records retrieved successfully',
                'data' => $attendances->items(),
                'pagination' => [
                    'current_page' => $attendances->currentPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total(),
                    'last_page' => $attendances->lastPage(),
                    'from' => $attendances->firstItem(),
                    'to' => $attendances->lastItem(),
                    'has_more_pages' => $attendances->hasMorePages(),
                ],
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new attendance record.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
                'date' => 'required|date',
                'clock_in' => 'nullable|date_format:H:i',
                'clock_out' => 'nullable|date_format:H:i',
                'status' => 'required|string|in:Present,Absent,Late,Half Day,On Leave',
                'notes' => 'nullable|string|max:1000',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            $attendance = Attendance::create($validatedData);

            // Reload with employee relationship
            $attendance->load('employee:id,staff_id,first_name_en,last_name_en');

            return response()->json([
                'success' => true,
                'message' => 'Attendance record created successfully',
                'data' => $attendance,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create attendance record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific attendance record.
     */
    public function show($id)
    {
        try {
            $attendance = Attendance::with('employee:id,staff_id,first_name_en,last_name_en')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Attendance record retrieved successfully',
                'data' => $attendance,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing attendance record.
     */
    public function update(Request $request, $id)
    {
        try {
            $attendance = Attendance::findOrFail($id);

            $validatedData = $request->validate([
                'employee_id' => 'sometimes|required|integer|exists:employees,id',
                'date' => 'sometimes|required|date',
                'clock_in' => 'nullable|date_format:H:i',
                'clock_out' => 'nullable|date_format:H:i',
                'status' => 'sometimes|required|string|in:Present,Absent,Late,Half Day,On Leave',
                'notes' => 'nullable|string|max:1000',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            $attendance->update($validatedData);

            // Reload with employee relationship
            $attendance->load('employee:id,staff_id,first_name_en,last_name_en');

            return response()->json([
                'success' => true,
                'message' => 'Attendance record updated successfully',
                'data' => $attendance,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an attendance record.
     */
    public function destroy($id)
    {
        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attendance record deleted successfully',
                'data' => null,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return dropdown options for the attendance modal (employee list).
     */
    public function options()
    {
        try {
            $employees = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en')
                ->orderBy('first_name_en')
                ->get()
                ->map(function ($employee) {
                    $fullName = $employee->first_name_en;
                    if ($employee->last_name_en && $employee->last_name_en !== '-') {
                        $fullName .= ' '.$employee->last_name_en;
                    }

                    return [
                        'value' => $employee->id,
                        'label' => $fullName.' ('.$employee->staff_id.')',
                        'staff_id' => $employee->staff_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Options retrieved successfully',
                'data' => [
                    'employees' => $employees,
                    'statuses' => [
                        ['value' => 'Present', 'label' => 'Present'],
                        ['value' => 'Absent', 'label' => 'Absent'],
                        ['value' => 'Late', 'label' => 'Late'],
                        ['value' => 'Half Day', 'label' => 'Half Day'],
                        ['value' => 'On Leave', 'label' => 'On Leave'],
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
