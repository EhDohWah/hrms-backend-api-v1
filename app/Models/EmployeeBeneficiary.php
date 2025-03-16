<?php

namespace App\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class EmployeeBeneficiary extends Model
{
    protected $fillable = [
        'employee_id',
        'beneficiary_name',
        'beneficiary_relationship',
        'phone_number',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}



