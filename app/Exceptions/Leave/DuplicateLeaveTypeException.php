<?php

namespace App\Exceptions\Leave;

use App\Exceptions\BusinessRuleException;

class DuplicateLeaveTypeException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Duplicate leave types are not allowed in a single request', 422);
    }
}
