<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employment;

class WorkLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'created_by',
        'updated_by'
    ];

    public function employments()
    {
        return $this->hasMany(Employment::class);
    }
}
