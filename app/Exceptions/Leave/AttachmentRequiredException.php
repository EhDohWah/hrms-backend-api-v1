<?php

namespace App\Exceptions\Leave;

use App\Exceptions\BusinessRuleException;

class AttachmentRequiredException extends BusinessRuleException
{
    public function __construct(string $leaveTypeNames)
    {
        parent::__construct(
            "This request includes leave types that require attachment notes: {$leaveTypeNames}",
            422
        );
    }
}
