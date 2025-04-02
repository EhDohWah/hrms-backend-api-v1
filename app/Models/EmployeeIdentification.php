<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeIdentification extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'id_type',
        'document_number',
        'issue_date',
        'expiry_date',
        'created_by',
        'updated_by'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
