<?php

namespace App\Exceptions\Leave;

use App\Exceptions\BusinessRuleException;

class NegativeLeaveBalanceException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Operation would result in negative leave balance', 422);
    }
}
