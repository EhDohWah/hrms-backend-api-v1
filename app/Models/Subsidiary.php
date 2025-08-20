<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subsidiary extends Model
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
