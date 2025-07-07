<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeFundingAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employment_id',
        'org_funded_id',
        'position_slot_id',
        'level_of_effort',
        'allocation_type',
        'active',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    public function orgFunded()
    {
        return $this->belongsTo(OrgFundedAllocation::class, 'org_funded_id');
    }

    public function positionSlot()
    {
        return $this->belongsTo(PositionSlot::class);
    }

    
}
