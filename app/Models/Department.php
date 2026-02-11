<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Department Model
 */
#[OA\Schema(
    schema: 'Department',
    title: 'Department',
    description: 'Department model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Human Resources'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'HR Department'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
class Department extends Model
{
    use HasFactory, LogsActivity, Prunable, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'created_by',
        'updated_by',
    ];

    /**
     * Prunable query: permanently delete soft-deleted records after 90 days.
     */
    public function prunable()
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }

    /**
     * Pre-deletion cleanup for children with NO_ACTION FK constraints.
     *
     * Department is referenced by:
     * - employments.department_id → NO ACTION (nullable, SET NULL manually)
     * - employment_histories.department_id → NO ACTION (nullable, SET NULL manually)
     * - personnel_actions.current/new_department_id → NO ACTION (nullable, SET NULL manually)
     * - resignations.department_id → SET NULL (DB-level, automatic)
     * - travel_requests.department_id → SET NULL (DB-level, automatic)
     * - positions.department_id → NO ACTION (must DELETE, after clearing position refs)
     * - section_departments.department_id → NO ACTION (must DELETE)
     *
     * Positions are also referenced by (all nullable, must SET NULL before deleting positions):
     * - employments.position_id, employment_histories.position_id
     * - personnel_actions.current/new_position_id
     * - resignations.position_id, travel_requests.position_id
     * - positions.reports_to_position_id (self-ref)
     *
     * Section departments are also referenced by (already SET NULL at DB-level):
     * - employments.section_department_id, employment_histories.section_department_id
     */
    protected function pruning(): void
    {
        // Nullify department references in NO_ACTION FK tables
        DB::table('employments')->where('department_id', $this->id)->update(['department_id' => null]);
        DB::table('employment_histories')->where('department_id', $this->id)->update(['department_id' => null]);
        DB::table('personnel_actions')->where('current_department_id', $this->id)->update(['current_department_id' => null]);
        DB::table('personnel_actions')->where('new_department_id', $this->id)->update(['new_department_id' => null]);

        // Clean up positions owned by this department
        $positionIds = DB::table('positions')->where('department_id', $this->id)->pluck('id');

        if ($positionIds->isNotEmpty()) {
            // Nullify all position references before deleting positions
            DB::table('positions')->whereIn('reports_to_position_id', $positionIds)->update(['reports_to_position_id' => null]);
            DB::table('employments')->whereIn('position_id', $positionIds)->update(['position_id' => null]);
            DB::table('employment_histories')->whereIn('position_id', $positionIds)->update(['position_id' => null]);
            DB::table('personnel_actions')->whereIn('current_position_id', $positionIds)->update(['current_position_id' => null]);
            DB::table('personnel_actions')->whereIn('new_position_id', $positionIds)->update(['new_position_id' => null]);
            DB::table('resignations')->whereIn('position_id', $positionIds)->update(['position_id' => null]);
            DB::table('travel_requests')->whereIn('position_id', $positionIds)->update(['position_id' => null]);

            DB::table('positions')->whereIn('id', $positionIds)->delete();
        }

        // Delete section_departments (employments/history section_department_id is SET NULL at DB-level)
        DB::table('section_departments')->where('department_id', $this->id)->delete();

        Log::info("Pruning department #{$this->id} ({$this->name}) and all related records");
    }

    /**
     * Get all positions in this department
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Get active positions only
     */
    public function activePositions(): HasMany
    {
        return $this->positions()->where('is_active', true);
    }

    /**
     * Get manager positions (department heads)
     */
    public function managerPositions(): HasMany
    {
        return $this->positions()->where('is_manager', true)->where('is_active', true);
    }

    /**
     * Get the department head (top-level manager)
     */
    public function departmentHead()
    {
        return $this->positions()
            ->where('level', 1)
            ->where('is_manager', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get positions count
     */
    public function getPositionsCountAttribute(): int
    {
        return $this->positions()->count();
    }

    /**
     * Get active positions count
     */
    public function getActivePositionsCountAttribute(): int
    {
        return $this->activePositions()->count();
    }

    /**
     * Check for conditions that prevent deletion.
     * Returns array of blocker messages. Empty = safe to delete.
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];

        $employmentCount = DB::table('employments')->where('department_id', $this->id)->count();
        if ($employmentCount > 0) {
            $blockers[] = "Cannot delete: {$employmentCount} employment record(s) are assigned to this department.";
        }

        $historyCount = DB::table('employment_histories')->where('department_id', $this->id)->count();
        if ($historyCount > 0) {
            $blockers[] = "Cannot delete: {$historyCount} employment history record(s) reference this department.";
        }

        $personnelCount = DB::table('personnel_actions')
            ->where(fn ($q) => $q->where('current_department_id', $this->id)->orWhere('new_department_id', $this->id))
            ->count();
        if ($personnelCount > 0) {
            $blockers[] = "Cannot delete: {$personnelCount} personnel action record(s) reference this department.";
        }

        return $blockers;
    }

    /**
     * Scope for active departments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to include positions count
     */
    public function scopeWithPositionsCount($query)
    {
        return $query->withCount(['positions', 'activePositions']);
    }
}
