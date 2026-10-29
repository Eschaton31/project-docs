<?php

namespace App\Ai\Reviewers;

use App\Ai\Agents\ProseReviewer;

/**
 * Runs the model on one chunk's prompt (see ReviewPrompt) with the reviewer's instructions and returns its
 * structured answer. Which one is bound depends on config('ai-review.runner').
 */
interface ChunkReviewer
{
    /**
     * @param  string  $model  sonnet or opus
     * @param  class-string  $agent  whose instructions and answer schema to use: ProseReviewer, or DocScaffolder for scaffolds
     * @return array{output: array, usage: array{input_tokens: int, output_tokens: int}}
     */
    public function review(string $prompt, string $model, string $agent = ProseReviewer::class): array;

    /**
     * Seconds one call may take.
     */
    public function timeout(): int;
}
