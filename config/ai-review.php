<?php

return [

    // Off hides the editor button and makes the review routes 404.
    'enabled' => (bool) env('AI_REVIEW_ENABLED', true),

    // Who runs the model:
    //   claude-code  Claude Code with your own Claude plan. The account in claude_code.user starts reviews from
    //                the editor (the ai-review queue worker runs `claude -p`); anyone with Claude Code in this
    //                repo can also run /prose-review (php artisan prose-review:export / :import).
    //   api          the API with ANTHROPIC_API_KEY; any admin starts reviews from the editor.
    'runner' => env('AI_REVIEW_RUNNER', 'claude-code'),

    'claude_code' => [
        // Email of the one account whose Claude plan this is: only it may start reviews from the editor, since
        // a personal plan is for its owner's own use. Unset: reviews only run through /prose-review.
        'user' => env('AI_REVIEW_CLAUDE_CODE_USER'),
        // The Claude Code executable, as the queue worker's Windows/Unix account finds it; a full path if needed
        // (on Windows an npm install is claude.cmd: give the path to it).
        'binary' => env('AI_REVIEW_CLAUDE_BINARY', 'claude'),
        // Claude Code model aliases for the editor's Sonnet / Opus choice.
        'models' => ['sonnet' => 'sonnet', 'opus' => 'opus'],
        // Seconds per chunk; Claude Code is slower to start than an API call.
        'timeout' => 600,
    ],

    // Where prose-review:export writes the instructions and prompts, and :import reads the answers.
    'work_dir' => env('AI_REVIEW_WORK_DIR', storage_path('app/prose-reviews')),

    // The API models an admin can pick per review; 'default' is used when they don't.
    'models' => [
        'sonnet' => env('AI_REVIEW_MODEL', 'claude-sonnet-5-5'),
        'opus' => env('AI_REVIEW_OPUS_MODEL', 'claude-opus-5-5'),
    ],
    'default' => 'sonnet',

    // Text sent per model call (the outline and style guide come on top), and the most one review may send.
    'max_chars_per_chunk' => 24_000,
    'max_chars_per_review' => 200_000,

    // Seconds per API call. The job's timeout is derived from the runner's per-call timeout and must stay
    // under the ai-review queue connection's retry_after.
    'timeout' => 180,

    // 'ai-review' in every environment but tests (phpunit.xml sets sync).
    'queue_connection' => env('AI_REVIEW_QUEUE_CONNECTION', 'ai-review'),

    // Reviews each admin may start per hour.
    'rate_limit_per_hour' => 10,

    'style_guide' => resource_path('ai/style-guides/laravel-docs.md'),

];
