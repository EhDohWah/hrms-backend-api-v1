<?php

namespace App\Exceptions\Payroll;

use App\Exceptions\BusinessRuleException;

class BudgetHistoryDateRangeException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Date range cannot exceed 12 months', 422);
    }
}
