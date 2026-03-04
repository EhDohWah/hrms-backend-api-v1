<?php

namespace App\Exceptions\Employment;

use Exception;
use Illuminate\Http\JsonResponse;

class ActiveEmploymentExistsException extends Exception
{
    protected $message = 'Employee already has an active employment record. Please end the existing employment first.';

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
