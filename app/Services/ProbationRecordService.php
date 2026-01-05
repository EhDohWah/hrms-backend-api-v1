<?php

namespace App\Services;

use App\Models\Employment;
use App\Models\ProbationRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing probation records and tracking probation lifecycle
 */
class ProbationRecordService
{
    /**
     * Create initial probation record when employment is created
     */
    public function createInitialRecord(Employment $employment): ProbationRecord
    {
        try {
            Log::info('Creating initial probation record', [
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
            ]);

            $record = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => ProbationRecord::EVENT_INITIAL,
                'event_date' => $employment->start_date,
                'probation_start_date' => $employment->start_date,
                'probation_end_date' => $employment->pass_probation_date,
                'extension_number' => 0,
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            Log::info('Initial probation record created', [
                'probation_record_id' => $record->id,
            ]);

            return $record;
        } catch (\Exception $e) {
            Log::error('Failed to create initial probation record', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create probation extension record
     */
    public function createExtensionRecord(
        Employment $employment,
        Carbon $newEndDate,
        ?string $reason = null,
        ?string $notes = null
    ): ProbationRecord {
        DB::beginTransaction();

        try {
            // Get current active probation record
            $currentRecord = $employment->activeProbationRecord;

            if (! $currentRecord) {
                throw new \Exception('No active probation record found for employment ID: '.$employment->id);
            }

            Log::info('Creating probation extension record', [
                'employment_id' => $employment->id,
                'current_record_id' => $currentRecord->id,
                'extension_number' => $currentRecord->extension_number + 1,
            ]);

            // Mark current record as inactive
            $currentRecord->update(['is_active' => false]);

            // Create new extension record
            $newRecord = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => ProbationRecord::EVENT_EXTENSION,
                'event_date' => now(),
                'decision_date' => now(),
                'probation_start_date' => $currentRecord->probation_start_date,
                'probation_end_date' => $newEndDate,
                'previous_end_date' => $currentRecord->probation_end_date,
                'extension_number' => $currentRecord->extension_number + 1,
                'decision_reason' => $reason,
                'evaluation_notes' => $notes,
                'approved_by' => Auth::user()?->name ?? 'system',
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            // Update employment table with new probation end date
            $employment->update([
                'pass_probation_date' => $newEndDate,
            ]);

            Log::info('Probation extension record created', [
                'probation_record_id' => $newRecord->id,
                'extension_number' => $newRecord->extension_number,
            ]);

            DB::commit();

            return $newRecord;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create probation extension record', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark probation as passed
     */
    public function markAsPassed(
        Employment $employment,
        ?string $notes = null
    ): ProbationRecord {
        DB::beginTransaction();

        try {
            $currentRecord = $employment->activeProbationRecord;

            if (! $currentRecord) {
                throw new \Exception('No active probation record found for employment ID: '.$employment->id);
            }

            Log::info('Marking probation as passed', [
                'employment_id' => $employment->id,
                'current_record_id' => $currentRecord->id,
            ]);

            // Mark current record as inactive
            $currentRecord->update(['is_active' => false]);

            // Create passed record
            $passedRecord = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => ProbationRecord::EVENT_PASSED,
                'event_date' => now(),
                'decision_date' => now(),
                'probation_start_date' => $currentRecord->probation_start_date,
                'probation_end_date' => $currentRecord->probation_end_date,
                'previous_end_date' => $currentRecord->probation_end_date,
                'extension_number' => $currentRecord->extension_number,
                'evaluation_notes' => $notes,
                'approved_by' => Auth::user()?->name ?? 'system',
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            // NOTE: probation_status field removed from employments table
            // Status is now tracked via active probation record's event_type

            Log::info('Probation marked as passed', [
                'probation_record_id' => $passedRecord->id,
            ]);

            DB::commit();

            return $passedRecord;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to mark probation as passed', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark probation as failed
     */
    public function markAsFailed(
        Employment $employment,
        ?string $reason = null,
        ?string $notes = null
    ): ProbationRecord {
        DB::beginTransaction();

        try {
            $currentRecord = $employment->activeProbationRecord;

            if (! $currentRecord) {
                throw new \Exception('No active probation record found for employment ID: '.$employment->id);
            }

            Log::info('Marking probation as failed', [
                'employment_id' => $employment->id,
                'current_record_id' => $currentRecord->id,
            ]);

            // Mark current record as inactive
            $currentRecord->update(['is_active' => false]);

            // Create failed record
            $failedRecord = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => ProbationRecord::EVENT_FAILED,
                'event_date' => now(),
                'decision_date' => now(),
                'probation_start_date' => $currentRecord->probation_start_date,
                'probation_end_date' => $currentRecord->probation_end_date,
                'previous_end_date' => $currentRecord->probation_end_date,
                'extension_number' => $currentRecord->extension_number,
                'decision_reason' => $reason,
                'evaluation_notes' => $notes,
                'approved_by' => Auth::user()?->name ?? 'system',
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            // NOTE: probation_status field removed from employments table
            // Status is now tracked via active probation record's event_type

            Log::info('Probation marked as failed', [
                'probation_record_id' => $failedRecord->id,
            ]);

            DB::commit();

            return $failedRecord;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to mark probation as failed', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get probation history for employment
     */
    public function getHistory(Employment $employment): array
    {
        $records = $employment->probationHistory;
        $activeRecord = $employment->activeProbationRecord;

        // Determine current status from the active probation record's event type
        // The active record's event_type IS the current status
        $currentStatus = $activeRecord?->event_type;

        // Map event types to status values for consistency
        // 'initial' and 'extension' both mean probation is 'ongoing'
        if (in_array($currentStatus, [ProbationRecord::EVENT_INITIAL, ProbationRecord::EVENT_EXTENSION])) {
            $currentStatus = 'ongoing';
        }

        return [
            'total_extensions' => $records->where('event_type', ProbationRecord::EVENT_EXTENSION)->count(),
            'current_extension_number' => $activeRecord?->extension_number ?? 0,
            'probation_start_date' => $records->first()?->probation_start_date,
            'initial_end_date' => $records->where('event_type', ProbationRecord::EVENT_INITIAL)->first()?->probation_end_date,
            'current_end_date' => $employment->pass_probation_date,
            'current_status' => $currentStatus, // Use active record's event type as current status
            'current_event_type' => $activeRecord?->event_type,
            'records' => $records,
        ];
    }

    /**
     * Get probation statistics for reporting
     * NOTE: Statistics now calculated from probation_records table instead of employments.probation_status
     */
    public function getStatistics(): array
    {
        return [
            'total_ongoing' => ProbationRecord::active()
                ->whereIn('event_type', [ProbationRecord::EVENT_INITIAL, ProbationRecord::EVENT_EXTENSION])
                ->count(),
            'total_extended' => ProbationRecord::active()
                ->where('event_type', ProbationRecord::EVENT_EXTENSION)
                ->count(),
            'total_passed' => ProbationRecord::active()
                ->where('event_type', ProbationRecord::EVENT_PASSED)
                ->count(),
            'total_failed' => ProbationRecord::active()
                ->where('event_type', ProbationRecord::EVENT_FAILED)
                ->count(),
            'employees_on_extension' => ProbationRecord::active()
                ->where('extension_number', '>', 0)
                ->count(),
            'employees_on_2nd_extension' => ProbationRecord::active()
                ->where('extension_number', '>=', 2)
                ->count(),
        ];
    }

    /**
     * Check if employment can be extended
     */
    public function canExtend(Employment $employment): bool
    {
        $activeRecord = $employment->activeProbationRecord;

        if (! $activeRecord) {
            return false;
        }

        // Cannot extend if already passed or failed
        if (in_array($activeRecord->event_type, [ProbationRecord::EVENT_PASSED, ProbationRecord::EVENT_FAILED])) {
            return false;
        }

        // Add business rules here (e.g., max 2 extensions)
        // if ($activeRecord->extension_number >= 2) {
        //     return false;
        // }

        return true;
    }
}
