<?php

namespace App\Exceptions;

use App\Models\ProseSuggestion;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The blocks a suggestion targets changed since the review, so it can't be applied as suggested.
 */
class SuggestionStaleException extends RuntimeException
{
    public function __construct(public readonly ProseSuggestion $suggestion)
    {
        parent::__construct('The text changed since the review, so this suggestion can no longer be applied.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'suggestion' => $this->suggestion->toSummary()], 409);
    }
}
