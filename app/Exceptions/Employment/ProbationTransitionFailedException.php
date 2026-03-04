<?php

namespace App\Exceptions\Employment;

use Exception;
use Illuminate\Http\JsonResponse;

class ProbationTransitionFailedException extends Exception
{
    protected $code = 400;

    public function __construct(string $message = 'Probation transition failed.', int $code = 400)
    {
        parent::__construct($message, $code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
