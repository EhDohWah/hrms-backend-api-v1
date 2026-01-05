<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'code',
        'created_by',
        'updated_by',
    ];

    public function grants()
    {
        return $this->hasMany(Grant::class);
    }
}
