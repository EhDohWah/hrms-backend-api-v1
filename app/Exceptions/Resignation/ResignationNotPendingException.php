<?php

namespace App\Exceptions\Resignation;

use App\Exceptions\BusinessRuleException;

class ResignationNotPendingException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Only pending resignations can be acknowledged or rejected', 400);
    }
}
