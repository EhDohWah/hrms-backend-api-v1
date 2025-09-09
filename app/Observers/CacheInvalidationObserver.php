<?php

namespace App\Observers;

use App\Services\CacheManagerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CacheInvalidationObserver
{
    protected CacheManagerService $cacheManager;

    public function __construct(CacheManagerService $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->invalidateModelCaches($model, 'created');
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->invalidateModelCaches($model, 'updated');
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->invalidateModelCaches($model, 'deleted');
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->invalidateModelCaches($model, 'restored');
    }

    /**
     * Handle the Model "forceDeleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->invalidateModelCaches($model, 'forceDeleted');
    }

    /**
     * Invalidate caches for the model
     */
    protected function invalidateModelCaches(Model $model, string $operation): void
    {
        try {
            $modelType = $this->getModelType($model);
            $modelId = $model->getKey();

            // Clear model-specific caches
            $this->cacheManager->clearModelCaches($modelType, $modelId);

            // Handle special cases for specific models
            $this->handleSpecialCases($model, $operation);

            Log::info('Cache invalidated via observer', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'operation' => $operation,
            ]);

        } catch (\Exception $e) {
            Log::error('Cache invalidation failed in observer', [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get model type for cache management
     */
    protected function getModelType(Model $model): string
    {
        $modelClass = class_basename($model);

        $typeMapping = [
            'Employee' => 'employees',
            'LeaveRequest' => 'leave_requests',
            'LeaveBalance' => 'leave_balances',
            'Employment' => 'employments',
            'Interview' => 'interviews',
            'JobOffer' => 'job_offers',
            'DepartmentPosition' => 'department_positions',
            'WorkLocation' => 'work_locations',
        ];

        return $typeMapping[$modelClass] ?? strtolower($modelClass);
    }

    /**
     * Handle special cases for different models
     */
    protected function handleSpecialCases(Model $model, string $operation): void
    {
        $modelClass = get_class($model);

        switch ($modelClass) {
            case 'App\Models\Employee':
                $this->handleEmployeeChanges($model, $operation);
                break;

            case 'App\Models\LeaveRequest':
                $this->handleLeaveRequestChanges($model, $operation);
                break;

            case 'App\Models\LeaveBalance':
                $this->handleLeaveBalanceChanges($model, $operation);
                break;

            case 'App\Models\Employment':
                $this->handleEmploymentChanges($model, $operation);
                break;

            case 'App\Models\Interview':
            case 'App\Models\JobOffer':
                $this->handleReportableModelChanges($model, $operation);
                break;
        }
    }

    /**
     * Handle Employee model changes
     */
    protected function handleEmployeeChanges(Model $employee, string $operation): void
    {
        // Clear employment caches for this employee
        $this->cacheManager->clearModelCaches('employments');

        // Clear leave-related caches
        $this->cacheManager->clearModelCaches('leave_requests');
        $this->cacheManager->clearModelCaches('leave_balances');

        // Clear report caches
        $this->cacheManager->clearReportCaches();
    }

    /**
     * Handle LeaveRequest model changes
     */
    protected function handleLeaveRequestChanges(Model $leaveRequest, string $operation): void
    {
        // Clear employee caches
        if ($leaveRequest->employee_id) {
            $this->cacheManager->clearModelCaches('employees', $leaveRequest->employee_id);
        }

        // Clear leave balance caches
        $this->cacheManager->clearModelCaches('leave_balances');

        // Clear report caches
        $this->cacheManager->clearReportCaches();
    }

    /**
     * Handle LeaveBalance model changes
     */
    protected function handleLeaveBalanceChanges(Model $leaveBalance, string $operation): void
    {
        // Clear employee caches
        if ($leaveBalance->employee_id) {
            $this->cacheManager->clearModelCaches('employees', $leaveBalance->employee_id);
        }

        // Clear leave request caches
        $this->cacheManager->clearModelCaches('leave_requests');

        // Clear report caches
        $this->cacheManager->clearReportCaches();
    }

    /**
     * Handle Employment model changes
     */
    protected function handleEmploymentChanges(Model $employment, string $operation): void
    {
        // Clear employee caches
        if ($employment->employee_id) {
            $this->cacheManager->clearModelCaches('employees', $employment->employee_id);
        }

        // Clear report caches
        $this->cacheManager->clearReportCaches();
    }

    /**
     * Handle changes to models that affect reports
     */
    protected function handleReportableModelChanges(Model $model, string $operation): void
    {
        // Clear all report caches
        $this->cacheManager->clearReportCaches();
    }
}
