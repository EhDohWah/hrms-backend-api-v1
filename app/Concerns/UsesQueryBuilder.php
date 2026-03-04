<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Bridges the existing flat parameter format (filter_name, sort_by, sort_order)
 * with Spatie QueryBuilder's expected nested format (filter[name], sort).
 */
trait UsesQueryBuilder
{
    /**
     * Build a Spatie QueryBuilder from a validated parameter array.
     *
     * Transforms flat parameters (filter_status, sort_by, sort_order)
     * into the nested format QueryBuilder expects (filter[status], sort).
     *
     * @param  class-string|EloquentBuilder|Relation  $subject  Model class or query builder
     * @param  array  $params  Validated request parameters
     */
    protected function buildQuery(string|EloquentBuilder|Relation $subject, array $params): QueryBuilder
    {
        $request = $this->transformParamsToRequest($params);

        return QueryBuilder::for($subject, $request);
    }

    /**
     * Transform flat parameter array into a Request with spatie-compatible format.
     *
     * Mapping rules:
     *  - filter_*       → filter[*]       (strip prefix)
     *  - search         → filter[search]  (search is treated as a filter)
     *  - sort_by + sort_order/sort_direction → sort  (e.g. sort_by=name&sort_order=desc → sort=-name)
     *  - per_page, page → kept as-is (pagination meta)
     *  - Everything else that isn't meta → filter[key]
     */
    private function transformParamsToRequest(array $params): Request
    {
        $metaKeys = ['per_page', 'page', 'sort_by', 'sort_order', 'sort_direction'];

        $transformed = [];
        $filters = [];

        foreach ($params as $key => $value) {
            if (in_array($key, $metaKeys, true)) {
                $transformed[$key] = $value;
            } elseif (str_starts_with($key, 'filter_')) {
                $filters[substr($key, 7)] = $value;
            } else {
                $filters[$key] = $value;
            }
        }

        // Transform sort_by + sort_order/sort_direction → sort parameter
        if (isset($transformed['sort_by'])) {
            $sortBy = $transformed['sort_by'];
            $sortOrder = $transformed['sort_order'] ?? $transformed['sort_direction'] ?? 'asc';
            $transformed['sort'] = ($sortOrder === 'desc' ? '-' : '').$sortBy;
        }

        if (! empty($filters)) {
            $transformed['filter'] = $filters;
        }

        return new Request($transformed);
    }
}
