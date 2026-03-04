<?php

namespace App\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

class EmploymentListResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator,
        public readonly array $appliedFilters,
    ) {}
}
