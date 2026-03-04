<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DeletionBlockedException extends Exception
{
    public function __construct(
        private readonly array $blockers,
        string $message = 'Cannot delete resource',
    ) {
        parent::__construct($message);
    }

    public function getBlockers(): array
    {
        return $this->blockers;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'blockers' => $this->blockers,
        ], 422);
    }
}
