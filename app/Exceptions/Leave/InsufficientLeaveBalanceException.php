<?php

namespace App\Exceptions\Leave;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientLeaveBalanceException extends Exception
{
    public function __construct(
        string $message,
        protected array $balanceData = []
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'data' => $this->balanceData,
        ], 400);
    }
}
