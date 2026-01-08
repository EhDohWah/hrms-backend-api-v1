<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Grant',
    title: 'Grant',
    description: 'Grant model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Research Grant 2023'),
        new OA\Property(property: 'code', type: 'string', example: 'RG-2023-001'),
        new OA\Property(property: 'organization', type: 'string', example: 'Main Campus'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Funding for research activities'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true, example: '2023-12-31'),
        new OA\Property(property: 'status', type: 'string', example: 'Active', enum: ['Active', 'Expired', 'Ending Soon']),
        new OA\Property(property: 'created_by', type: 'string', nullable: true, example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true, example: 'admin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'grant_items', type: 'array', items: new OA\Items(ref: '#/components/schemas/GrantItem')),
    ]
)]
class Grant extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'organization', 'description', 'end_date', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function grantItems()
    {
        return $this->hasMany(GrantItem::class, 'grant_id');
    }

    public function employeeFundingAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'grant_id');
    }

    public function organizationHubFunds()
    {
        return $this->hasMany(SubsidiaryHubFund::class, 'hub_grant_id');
    }

    public function interSubsidiaryAdvances()
    {
        return $this->hasMany(InterOrganizationAdvance::class, 'via_grant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>', DB::raw('GETDATE()'));
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')->where('end_date', '<', DB::raw('GETDATE()'));
    }

    public function scopeEndingSoon($query)
    {
        return $query->whereNotNull('end_date')
            ->where('end_date', '>', DB::raw('GETDATE()'))
            ->where('end_date', '<=', DB::raw('DATEADD(day,30,GETDATE())'));
    }

    public function scopeSearch($query, $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('code', 'LIKE', "%{$term}%")
                ->orWhere('name', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhere('organization', 'LIKE', "%{$term}%");
        });
    }

    public function scopeBySubsidiary($query, $subsidiaries)
    {
        if (is_string($subsidiaries)) {
            $subsidiaries = explode(',', $subsidiaries);
        }

        return $query->whereIn('organization', array_filter($subsidiaries));
    }

    public function scopeByCodes($query, $codes)
    {
        if (is_string($codes)) {
            $codes = explode(',', $codes);
        }

        return $query->whereIn('code', array_filter($codes));
    }

    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate && $endDate) {
            return $query->whereBetween('end_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            return $query->where('end_date', '>=', $startDate);
        } elseif ($endDate) {
            return $query->where('end_date', '<=', $endDate);
        }

        return $query;
    }

    public function scopeHasItems($query, $hasItems = true)
    {
        return $hasItems ? $query->has('grantItems') : $query->doesntHave('grantItems');
    }

    public function scopeEndDateAfter($query, $date)
    {
        return $query->where('end_date', '>', $date);
    }

    public function scopeEndDateBefore($query, $date)
    {
        return $query->where('end_date', '<', $date);
    }

    public function scopeEndDateBetween($query, $dates)
    {
        if (is_string($dates)) {
            $dates = explode(',', $dates);
        }

        return $query->whereBetween('end_date', is_array($dates) ? $dates : [$dates]);
    }

    // Computed attributes
    public function getStatusAttribute(): string
    {
        if (! $this->end_date) {
            return 'Active';
        }
        $endDate = Carbon::parse($this->end_date);
        $now = Carbon::now();
        if ($endDate->isPast()) {
            return 'Expired';
        }
        if ($endDate->diffInDays($now) <= 30) {
            return 'Ending Soon';
        }

        return 'Active';
    }

    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }
        $endDate = Carbon::parse($this->end_date);
        $now = Carbon::now();

        return $endDate->isPast() ? 0 : $endDate->diffInDays($now);
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        $days = $this->days_until_expiration;

        return $days !== null && $days > 0 && $days <= 30;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'Active';
    }

    // Helpers to fetch distinct filter options
    public static function getUniqueOrganizations()
    {
        return self::select('organization')
            ->distinct()
            ->whereNotNull('organization')
            ->where('organization', '!=', '')
            ->orderBy('organization')
            ->pluck('organization');
    }

    public static function getUniqueCodes($limit = 200)
    {
        return self::select('code')
            ->distinct()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('code')
            ->limit($limit)
            ->pluck('code');
    }

    // Statistics
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'active' => self::active()->count(),
            'expired' => self::expired()->count(),
            'ending_soon' => self::endingSoon()->count(),
            'by_organization' => self::select('organization')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('organization')
                ->groupBy('organization')
                ->orderBy('organization')
                ->get()
                ->pluck('count', 'organization')
                ->toArray(),
        ];
    }

    // Query optimization scopes
    public function scopeForPagination($query)
    {
        return $query->select(['id', 'code', 'name', 'organization', 'description', 'end_date', 'created_at', 'updated_at', 'created_by', 'updated_by']);
    }

    public function scopeWithItemsCount($query)
    {
        return $query->withCount('grantItems');
    }

    public function scopeWithOptimizedItems($query)
    {
        return $query->with(['grantItems' => function ($q) {
            $q->select([
                'id',
                'grant_id',
                'grant_position',
                'grant_salary',
                'grant_benefit',
                'grant_level_of_effort',
                'grant_position_number',
                'budgetline_code',
                'created_at',
                'updated_at',
            ]);
        }]);
    }

    // Hub Grant Helper Methods
    public static function getHubGrantForOrganization(string $organization): ?Grant
    {
        $hubGrantCodes = [
            'SMRU' => 'S0031',  // Other Fund
            'BHF' => 'S22001',  // General Fund
        ];

        if (! isset($hubGrantCodes[$organization])) {
            return null;
        }

        return self::where('organization', $organization)
            ->where('code', $hubGrantCodes[$organization])
            ->first();
    }

    public function isHubGrant(): bool
    {
        return in_array($this->code, ['S0031', 'S22001']);
    }

    public static function getHubGrantCodes(): array
    {
        return [
            'SMRU' => 'S0031',  // Other Fund
            'BHF' => 'S22001',  // General Fund
        ];
    }

    public static function getAllHubGrants(): \Illuminate\Database\Eloquent\Collection
    {
        $hubCodes = array_values(self::getHubGrantCodes());

        return self::whereIn('code', $hubCodes)->get();
    }

    // Model events to set created_by/updated_by
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($grant) {
            if (auth()->check()) {
                $grant->created_by = auth()->user()->name ?? 'system';
                $grant->updated_by = auth()->user()->name ?? 'system';
            }
        });
        static::updating(function ($grant) {
            if (auth()->check()) {
                $grant->updated_by = auth()->user()->name ?? 'system';
            }
        });
    }
}
