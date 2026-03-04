<?php

namespace App\Exceptions\FundingAllocation;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\JsonResponse;

class FundingAllocationException extends BusinessRuleException
{
    public function __construct(
        string $message,
        protected array $extra = [],
        int $statusCode = 422,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->extra), $this->statusCode);
    }
}
