<?php

return [

    // Off hides the editor button and makes the review routes 404.
    'enabled' => (bool) env('AI_REVIEW_ENABLED', true),

    // Who runs the model:
    //   claude-code  in Claude Code with your own Claude plan: /prose-review in this repo exports the text,
    //                reviews it and imports the results (php artisan prose-review:export / :import)
    //   api          from the queue, with ANTHROPIC_API_KEY; the editor's Review button starts it
    'runner' => env('AI_REVIEW_RUNNER', 'claude-code'),

    // Where prose-review:export writes the instructions and prompts, and :import reads the answers.
    'work_dir' => env('AI_REVIEW_WORK_DIR', storage_path('app/prose-reviews')),

    // The models an admin can pick per review; 'default' is used when they don't.
    'models' => [
        'sonnet' => env('AI_REVIEW_MODEL', 'claude-sonnet-5-5'),
        'opus' => env('AI_REVIEW_OPUS_MODEL', 'claude-opus-5-5'),
    ],
    'default' => 'sonnet',

    // Text sent per model call (the outline and style guide come on top), and the most one review may send.
    'max_chars_per_chunk' => 24_000,
    'max_chars_per_review' => 200_000,

    // Seconds per model call; the job's timeout is derived from it and must stay under the
    // ai-review queue connection's retry_after.
    'timeout' => 180,

    // 'ai-review' in every environment but tests (phpunit.xml sets sync).
    'queue_connection' => env('AI_REVIEW_QUEUE_CONNECTION', 'ai-review'),

    // Reviews each admin may start per hour.
    'rate_limit_per_hour' => 10,

    'style_guide' => resource_path('ai/style-guides/laravel-docs.md'),

];
