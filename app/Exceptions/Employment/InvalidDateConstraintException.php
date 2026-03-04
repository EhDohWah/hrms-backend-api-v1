<?php

namespace App\Exceptions\Employment;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidDateConstraintException extends Exception
{
    protected $message = 'End probation date must be after or equal to start date.';

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
