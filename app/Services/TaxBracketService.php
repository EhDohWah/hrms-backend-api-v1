<?php

namespace App\Services;

use App\Exceptions\Tax\DuplicateTaxBracketException;
use App\Models\TaxBracket;

class TaxBracketService
{
    /**
     * List tax brackets with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $perPage = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;

        $totalCount = TaxBracket::count();

        $query = TaxBracket::query();

        if (! empty($params['search']) && is_numeric($params['search'])) {
            $query->where('bracket_order', intval($params['search']));
        }

        if (! empty($params['filter_effective_year'])) {
            $years = array_map('intval', explode(',', $params['filter_effective_year']));
            $query->whereIn('effective_year', $years);
        }

        if (isset($params['filter_is_active'])) {
            $isActive = filter_var($params['filter_is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $sortBy = $params['sort_by'] ?? 'bracket_order';
        $sortOrder = $params['sort_order'] ?? 'asc';

        if (in_array($sortBy, ['effective_year', 'bracket_order', 'min_income', 'max_income', 'tax_rate'])) {
            $query->orderBy($sortBy, $sortOrder);
            if ($sortBy !== 'bracket_order') {
                $query->orderBy('bracket_order', 'asc');
            }
        } else {
            $query->orderBy('bracket_order', 'asc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $appliedFilters = [];
        if (! empty($params['filter_effective_year'])) {
            $appliedFilters['effective_year'] = array_map('intval', explode(',', $params['filter_effective_year']));
        }
        if (isset($params['filter_is_active'])) {
            $appliedFilters['is_active'] = filter_var($params['filter_is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'paginator' => $paginator,
            'applied_filters' => $appliedFilters,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Search tax brackets by bracket order.
     */
    public function search(array $params): array
    {
        $orderId = $params['order_id'];
        $query = TaxBracket::byOrder($orderId);

        if (isset($params['effective_year'])) {
            $query->where('effective_year', $params['effective_year']);
        }

        if (isset($params['is_active'])) {
            $query->where('is_active', $params['is_active']);
        }

        $taxBrackets = $query->orderBy('effective_year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $searchCriteria = ['order_id' => $orderId];
        if (isset($params['effective_year'])) {
            $searchCriteria['effective_year'] = $params['effective_year'];
        }
        if (isset($params['is_active'])) {
            $searchCriteria['is_active'] = $params['is_active'];
        }

        return [
            'brackets' => $taxBrackets,
            'search_criteria' => $searchCriteria,
        ];
    }

    /**
     * Create a new tax bracket. Throws if duplicate bracket order exists for the year.
     */
    public function store(array $data): TaxBracket
    {
        $this->guardAgainstDuplicate($data['effective_year'], $data['bracket_order']);

        return TaxBracket::create($data);
    }

    /**
     * Update an existing tax bracket. Throws if duplicate bracket order exists for the year.
     */
    public function update(TaxBracket $taxBracket, array $data): TaxBracket
    {
        $bracketOrder = $data['bracket_order'] ?? $taxBracket->bracket_order;
        $effectiveYear = $data['effective_year'] ?? $taxBracket->effective_year;

        // Only check for duplicates if order or year is changing
        if (
            ($data['bracket_order'] ?? null) !== null ||
            ($data['effective_year'] ?? null) !== null
        ) {
            $this->guardAgainstDuplicate($effectiveYear, $bracketOrder, $taxBracket->id);
        }

        $taxBracket->update($data);

        return $taxBracket->fresh();
    }

    /**
     * Delete a tax bracket.
     */
    public function destroy(TaxBracket $taxBracket): void
    {
        $taxBracket->delete();
    }

    /**
     * Calculate tax for a specific income amount using brackets.
     */
    public function calculateTax(float $income, int $year): ?array
    {
        $taxBrackets = TaxBracket::getBracketsForYear($year);

        if ($taxBrackets->isEmpty()) {
            return null;
        }

        $totalTax = 0;
        $breakdown = [];
        $remainingIncome = $income;

        foreach ($taxBrackets as $bracket) {
            if ($remainingIncome <= 0) {
                break;
            }

            $bracketMin = $bracket->min_income;
            $bracketMax = $bracket->max_income ?? PHP_FLOAT_MAX;
            $taxRate = $bracket->tax_rate;

            if ($income > $bracketMin) {
                $taxableInBracket = min($remainingIncome, $bracketMax - $bracketMin);
                $taxInBracket = $taxableInBracket * ($taxRate / 100);
                $totalTax += $taxInBracket;

                $breakdown[] = [
                    'bracket' => $bracket->income_range,
                    'rate' => $bracket->formatted_rate,
                    'taxable_amount' => $taxableInBracket,
                    'tax_amount' => $taxInBracket,
                ];

                $remainingIncome -= $taxableInBracket;
            }
        }

        $effectiveRate = $income > 0 ? ($totalTax / $income) * 100 : 0;

        return [
            'income' => $income,
            'total_tax' => $totalTax,
            'net_income' => $income - $totalTax,
            'effective_rate' => round($effectiveRate, 2),
            'breakdown' => $breakdown,
            'tax_year' => $year,
        ];
    }

    /**
     * Guard against duplicate bracket order for a given year.
     */
    private function guardAgainstDuplicate(int $effectiveYear, int $bracketOrder, ?int $excludeId = null): void
    {
        $query = TaxBracket::where('effective_year', $effectiveYear)
            ->where('bracket_order', $bracketOrder);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new DuplicateTaxBracketException($effectiveYear, $bracketOrder);
        }
    }
}
