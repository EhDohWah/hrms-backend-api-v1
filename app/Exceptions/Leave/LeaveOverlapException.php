<?php

namespace App\Exceptions\Leave;

use Exception;
use Illuminate\Http\JsonResponse;

class LeaveOverlapException extends Exception
{
    public function __construct(
        string $message,
        protected array $conflicts = []
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_type' => 'overlap',
            'conflicts' => $this->conflicts,
        ], 422);
    }
}
