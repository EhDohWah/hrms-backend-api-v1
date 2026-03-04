<?php

namespace App\Exceptions\Resignation;

use App\Exceptions\BusinessRuleException;

class ResignationNotAcknowledgedException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Recommendation letter can only be generated for acknowledged resignations', 400);
    }
}
