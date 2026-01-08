<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LetterTemplate',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string', maxLength: 200, nullable: true),
        new OA\Property(property: 'content', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_by', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', maxLength: 100, nullable: true),
    ]
)]
class LetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'created_by',
        'updated_by',
    ];
}
