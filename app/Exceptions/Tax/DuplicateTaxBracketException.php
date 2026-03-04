<?php

namespace App\Exceptions\Tax;

use App\Exceptions\BusinessRuleException;

class DuplicateTaxBracketException extends BusinessRuleException
{
    public function __construct(int $effectiveYear, int $bracketOrder)
    {
        parent::__construct(
            "A tax bracket with order {$bracketOrder} already exists for year {$effectiveYear}",
            422
        );
    }
}
