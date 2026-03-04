<?php

namespace App\Exceptions\FundingAllocation;

class MissingSalaryException extends FundingAllocationException
{
    public function __construct(string $message = 'Employment must have a salary defined before funding allocations can be created. Please update the employment record with salary information first.')
    {
        parent::__construct($message);
    }
}
