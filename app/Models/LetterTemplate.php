<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    //
    protected $fillable = [
        'title',
        'content',
        'created_by',
        'updated_by',
    ];

}
