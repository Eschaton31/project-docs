<?php

namespace App\Ai\Reviewers;

/**
 * Runs the model on one chunk's prompt (see ReviewPrompt) with the reviewer's instructions and returns its
 * structured answer. Which one is bound depends on config('ai-review.runner').
 */
interface ChunkReviewer
{
    /**
     * @param  string  $model  sonnet or opus
     * @return array{output: array, usage: array{input_tokens: int, output_tokens: int}}
     */
    public function review(string $prompt, string $model): array;

    /**
     * Seconds one call may take.
     */
    public function timeout(): int;
}
