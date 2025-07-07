<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgFundedAllocation extends Model
{
    protected $fillable = [
        'grant_id',
        'department_position_id',
        'description',
        'active',
        'created_by',
        'updated_by',
    ];

    // Relationships
    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }

    public function departmentPosition()
    {
        return $this->belongsTo(DepartmentPosition::class);
    }

    // Optionally, add global scopes for active status, auditing, etc.
}
