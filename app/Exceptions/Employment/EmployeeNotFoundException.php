<?php

namespace App\Exceptions\Employment;

use Exception;
use Illuminate\Http\JsonResponse;

class EmployeeNotFoundException extends Exception
{
    public function __construct(string $staffId)
    {
        parent::__construct("No employee found with staff ID: {$staffId}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 404);
    }
}
