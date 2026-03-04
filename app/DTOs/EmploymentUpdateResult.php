<?php

namespace App\DTOs;

use App\Models\Employment;

class EmploymentUpdateResult
{
    public function __construct(
        public readonly Employment $employment,
        public readonly bool $earlyTermination = false,
        public readonly ?array $probationResult = null,
    ) {}
}
