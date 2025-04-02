<?php

namespace App\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeBeneficiary extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'employee_id',
        'beneficiary_name',
        'beneficiary_relationship',
        'phone_number',
        'created_by',
        'updated_by'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}



