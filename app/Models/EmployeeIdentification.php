<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Get the employee that owns the identification.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the identification data.
     */
    public function getIdentificationAttribute()
    {
        return [
            'id_type' => $this->id_type,
            'document_number' => $this->document_number,
        ];
    }

    /**
     * Create a collection-like map method to make the model compatible with collection methods.
     *
     * @param callable $callback
     * @return array
     */
    public function map(callable $callback)
    {
        return [$callback($this)];
    }
}
