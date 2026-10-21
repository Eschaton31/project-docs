<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Someone else saved the block (or document settings) after the client loaded it.
 */
class StaleVersionException extends RuntimeException
{
    public function __construct(public readonly array $current)
    {
        parent::__construct('This was changed by someone else since you loaded it.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'current' => $this->current], 409);
    }
}
