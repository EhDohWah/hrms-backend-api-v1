<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeIdentification extends Model
{
    protected $fillable = [
        'employee_id',
        'id_type',
        'document_number',
        'issue_date',
        'expiry_date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
