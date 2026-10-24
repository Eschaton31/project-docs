<?php

namespace App\Ai\Reviewers;

use App\Ai\Agents\ProseReviewer;

/**
 * The Anthropic API, with ANTHROPIC_API_KEY (runner "api").
 */
class ApiReviewer implements ChunkReviewer
{
    public function review(string $prompt, string $model): array
    {
        $response = ProseReviewer::make()->prompt(
            $prompt,
            model: config('ai-review.models')[$model],
            timeout: $this->timeout(),
        );

        return [
            'output' => $response->structured,
            'usage' => ['input_tokens' => $response->usage->inputTokens, 'output_tokens' => $response->usage->outputTokens],
        ];
    }

    public function timeout(): int
    {
        return config('ai-review.timeout');
    }
}
