<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Grant",
 *     title="Grant",
 *     description="Grant model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Research Grant 2023"),
 *     @OA\Property(property="code", type="string", example="RG-2023-001"),
 *     @OA\Property(property="subsidiary", type="string", example="Main Campus"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Funding for research activities"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2023-12-31"),
 *     @OA\Property(property="status", type="string", example="Active", enum={"Active", "Expired", "Ending Soon"}),
 *     @OA\Property(property="created_by", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="grant_items",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/GrantItem")
 *     )
 * )
 */
class Grant extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'subsidiary', 'description', 'end_date', 'created_by', 'updated_by',
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

    public function orgFundedAllocations()
    {
        return $this->hasMany(OrgFundedAllocation::class, 'grant_id');
    }

    public function subsidiaryHubFunds()
    {
        return $this->hasMany(SubsidiaryHubFund::class, 'hub_grant_id');
    }

    public function interSubsidiaryAdvances()
    {
        return $this->hasMany(InterSubsidiaryAdvance::class, 'via_grant_id');
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
                ->orWhere('subsidiary', 'LIKE', "%{$term}%");
        });
    }

    public function scopeBySubsidiary($query, $subsidiaries)
    {
        if (is_string($subsidiaries)) {
            $subsidiaries = explode(',', $subsidiaries);
        }

        return $query->whereIn('subsidiary', array_filter($subsidiaries));
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
    public static function getUniqueSubsidiaries()
    {
        return self::select('subsidiary')
            ->distinct()
            ->whereNotNull('subsidiary')
            ->where('subsidiary', '!=', '')
            ->orderBy('subsidiary')
            ->pluck('subsidiary');
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
            'by_subsidiary' => self::select('subsidiary')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('subsidiary')
                ->groupBy('subsidiary')
                ->orderBy('subsidiary')
                ->get()
                ->pluck('count', 'subsidiary')
                ->toArray(),
        ];
    }

    // Query optimization scopes
    public function scopeForPagination($query)
    {
        return $query->select(['id', 'code', 'name', 'subsidiary', 'description', 'end_date', 'created_at', 'updated_at', 'created_by', 'updated_by']);
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
    public static function getHubGrantForSubsidiary(string $subsidiary): ?Grant
    {
        $hubGrantCodes = [
            'SMRU' => 'S0031',  // Other Fund
            'BHF' => 'S22001',  // General Fund
        ];

        if (! isset($hubGrantCodes[$subsidiary])) {
            return null;
        }

        return self::where('subsidiary', $subsidiary)
            ->where('code', $hubGrantCodes[$subsidiary])
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
