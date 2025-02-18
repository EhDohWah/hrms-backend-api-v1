<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\GrantItem;

class Grant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'created_by',
        'updated_by'
    ];

    public function grantItems()
    {
        return $this->hasMany(GrantItem::class);
    }
}
