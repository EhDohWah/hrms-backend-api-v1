<?php

namespace App\Exceptions\Employment;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidDepartmentPositionException extends Exception
{
    protected $message = 'The selected position must belong to the selected department.';

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => ['position_id' => [$this->getMessage()]],
        ], 422);
    }
}
