<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Grant;

class GrantItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grant_id', 'bg_line', 'grant_position', 'grant_salary', 'grant_benefit',
        'grant_level_of_effort', 'grant_position_number', 'grant_cost_by_monthly',
        'grant_total_cost_by_person', 'grant_benefit_fte', 'position_id', 'grant_total_amount',
        'created_by', 'updated_by'
    ];

    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }
}
