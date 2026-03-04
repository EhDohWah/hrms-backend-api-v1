<?php

namespace App\Services;

use App\Models\InterOrganizationAdvance;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterOrganizationAdvanceService
{
    /**
     * List advances with filtering and pagination, including summary.
     */
    public function list(array $filters): array
    {
        $perPage = $filters['per_page'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'advance_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query = InterOrganizationAdvance::with(['viaGrant:id,code,name,organization']);

        if (! empty($filters['from_organization'])) {
            $query->where('from_organization', $filters['from_organization']);
        }

        if (! empty($filters['to_organization'])) {
            $query->where('to_organization', $filters['to_organization']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->whereNull('settlement_date');
            } else {
                $query->whereNotNull('settlement_date');
            }
        }

        if (! empty($filters['date_range'])) {
            $dates = explode(',', $filters['date_range']);
            if (count($dates) === 2) {
                $query->whereBetween('advance_date', [trim($dates[0]), trim($dates[1])]);
            }
        }

        $query->orderBy($sortBy, $sortOrder);
        $advances = $query->paginate($perPage);

        return [
            'paginator' => $advances,
            'summary' => $this->calculateAdvanceSummary($advances->items()),
        ];
    }

    /**
     * Create a new advance.
     */
    public function store(array $data): InterOrganizationAdvance
    {
        $data['created_by'] = auth()->user()->username ?? null;
        $item = InterOrganizationAdvance::create($data);

        return $item->load('viaGrant');
    }

    /**
     * Show a single advance.
     */
    public function show(int $id): InterOrganizationAdvance
    {
        return InterOrganizationAdvance::with('viaGrant')->findOrFail($id);
    }

    /**
     * Update an advance.
     */
    public function update(int $id, array $data): InterOrganizationAdvance
    {
        $item = InterOrganizationAdvance::findOrFail($id);
        $item->update($data + ['updated_by' => auth()->user()->username ?? null]);

        return $item->load('viaGrant');
    }

    /**
     * Delete an advance.
     */
    public function destroy(int $id): void
    {
        InterOrganizationAdvance::findOrFail($id)->delete();
    }

    /**
     * Bulk settle multiple advances.
     */
    public function bulkSettle(array $advanceIds, string $settlementDate, ?string $notes): array
    {
        return DB::transaction(function () use ($advanceIds, $settlementDate, $notes) {
            $advances = InterOrganizationAdvance::whereIn('id', $advanceIds)
                ->whereNull('settlement_date')
                ->get();

            if ($advances->isEmpty()) {
                return ['settled_count' => 0, 'total_amount' => 0, 'empty' => true];
            }

            $totalAmount = 0;
            $settledCount = 0;

            foreach ($advances as $advance) {
                $advance->update([
                    'settlement_date' => $settlementDate,
                    'notes' => $notes ? $advance->notes.' | '.$notes : $advance->notes,
                    'updated_by' => auth()->user()->username ?? 'system',
                ]);

                $totalAmount += $advance->amount;
                $settledCount++;

                Log::info('Advance settled in bulk', [
                    'advance_id' => $advance->id,
                    'amount' => $advance->amount,
                    'settlement_date' => $settlementDate,
                    'settled_by' => auth()->user()->username ?? 'system',
                ]);
            }

            return [
                'settled_count' => $settledCount,
                'total_amount' => $totalAmount,
                'settlement_date' => $settlementDate,
                'empty' => false,
            ];
        });
    }

    /**
     * Get summary statistics for a given period.
     */
    public function summary(array $filters): array
    {
        $period = $filters['period'] ?? 'current_month';
        [$startDate, $endDate] = $this->getDateRange($period, $filters);

        $advances = InterOrganizationAdvance::with(['viaGrant:id,code,name'])
            ->whereBetween('advance_date', [$startDate, $endDate])
            ->get();

        return [
            'period' => [
                'type' => $period,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'totals' => [
                'total_advances' => $advances->count(),
                'total_amount' => $advances->sum('amount'),
                'pending_advances' => $advances->whereNull('settlement_date')->count(),
                'pending_amount' => $advances->whereNull('settlement_date')->sum('amount'),
                'settled_advances' => $advances->whereNotNull('settlement_date')->count(),
                'settled_amount' => $advances->whereNotNull('settlement_date')->sum('amount'),
            ],
            'by_organization' => [
                'from_subsidiaries' => $advances->groupBy('from_organization')
                    ->map(fn ($group) => [
                        'count' => $group->count(),
                        'total_amount' => $group->sum('amount'),
                        'pending_amount' => $group->whereNull('settlement_date')->sum('amount'),
                    ]),
                'to_subsidiaries' => $advances->groupBy('to_organization')
                    ->map(fn ($group) => [
                        'count' => $group->count(),
                        'total_amount' => $group->sum('amount'),
                        'pending_amount' => $group->whereNull('settlement_date')->sum('amount'),
                    ]),
            ],
            'by_grant' => $advances->groupBy('viaGrant.code')
                ->map(fn ($group) => [
                    'grant_name' => $group->first()->viaGrant->name ?? 'Unknown',
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'pending_amount' => $group->whereNull('settlement_date')->sum('amount'),
                ]),
            'aging_analysis' => $this->getAgingAnalysis($advances->whereNull('settlement_date')),
        ];
    }

    /**
     * Auto-create advances based on payroll period data.
     */
    public function autoCreateAdvances(string $payrollPeriodDate, bool $dryRun): array
    {
        $payrollDate = Carbon::parse($payrollPeriodDate);

        $payrolls = Payroll::with([
            'employment.employee',
            'employeeFundingAllocation.grantItem.grant',
            'employeeFundingAllocation.grant',
        ])
            ->whereDate('pay_period_date', $payrollDate)
            ->whereDoesntHave('interOrganizationAdvances')
            ->get();

        $advancesToCreate = [];
        $totalAmount = 0;

        foreach ($payrolls as $payroll) {
            $employee = $payroll->employment->employee;
            $allocation = $payroll->employeeFundingAllocation;

            $fundingOrganization = null;
            $grant = null;

            if ($allocation->grantItem) {
                $fundingOrganization = $allocation->grantItem->grant->organization ?? null;
                $grant = $allocation->grantItem->grant;
            }

            if ($fundingOrganization && $grant && $fundingOrganization !== $employee->organization) {
                $advanceData = [
                    'payroll_id' => $payroll->id,
                    'from_organization' => $fundingOrganization,
                    'to_organization' => $employee->organization,
                    'via_grant_id' => $grant->id,
                    'amount' => $payroll->net_salary,
                    'advance_date' => $payrollDate,
                    'notes' => "Auto-generated for {$employee->staff_id} payroll",
                    'created_by' => auth()->user()->username ?? 'system',
                    'updated_by' => auth()->user()->username ?? 'system',
                ];

                $advancesToCreate[] = $advanceData;
                $totalAmount += $payroll->net_salary;
            }
        }

        if (! $dryRun && ! empty($advancesToCreate)) {
            DB::transaction(function () use ($advancesToCreate) {
                foreach ($advancesToCreate as $advanceData) {
                    InterOrganizationAdvance::create($advanceData);
                }
            });

            Log::info('Auto-created inter-organization advances', [
                'payroll_period' => $payrollDate->format('Y-m-d'),
                'count' => count($advancesToCreate),
                'total_amount' => $totalAmount,
            ]);
        }

        return [
            'dry_run' => $dryRun,
            'created_count' => count($advancesToCreate),
            'total_amount' => $totalAmount,
            'payroll_period_date' => $payrollDate->format('Y-m-d'),
            'advances_preview' => $dryRun ? $advancesToCreate : null,
        ];
    }

    private function calculateAdvanceSummary(array $advances): array
    {
        $collection = collect($advances);

        return [
            'total_count' => $collection->count(),
            'total_amount' => $collection->sum('amount'),
            'pending_count' => $collection->whereNull('settlement_date')->count(),
            'pending_amount' => $collection->whereNull('settlement_date')->sum('amount'),
            'settled_count' => $collection->whereNotNull('settlement_date')->count(),
            'settled_amount' => $collection->whereNotNull('settlement_date')->sum('amount'),
            'by_organization' => $collection->groupBy('from_organization')->map->count()->toArray(),
        ];
    }

    private function getDateRange(string $period, array $validated): array
    {
        return match ($period) {
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'current_year' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [Carbon::parse($validated['start_date']), Carbon::parse($validated['end_date'])],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function getAgingAnalysis($pendingAdvances): array
    {
        $today = now();
        $analysis = [
            '0-30_days' => ['count' => 0, 'amount' => 0],
            '31-60_days' => ['count' => 0, 'amount' => 0],
            '61-90_days' => ['count' => 0, 'amount' => 0],
            'over_90_days' => ['count' => 0, 'amount' => 0],
        ];

        foreach ($pendingAdvances as $advance) {
            $daysOld = $today->diffInDays(Carbon::parse($advance->advance_date));

            if ($daysOld <= 30) {
                $analysis['0-30_days']['count']++;
                $analysis['0-30_days']['amount'] += $advance->amount;
            } elseif ($daysOld <= 60) {
                $analysis['31-60_days']['count']++;
                $analysis['31-60_days']['amount'] += $advance->amount;
            } elseif ($daysOld <= 90) {
                $analysis['61-90_days']['count']++;
                $analysis['61-90_days']['amount'] += $advance->amount;
            } else {
                $analysis['over_90_days']['count']++;
                $analysis['over_90_days']['amount'] += $advance->amount;
            }
        }

        return $analysis;
    }
}
