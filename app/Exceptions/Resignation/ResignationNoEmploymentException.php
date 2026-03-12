<?php

namespace App\Exceptions\Resignation;

use App\Exceptions\BusinessRuleException;

class ResignationNoEmploymentException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Cannot acknowledge resignation: employee has no active employment record', 400);
    }
}
