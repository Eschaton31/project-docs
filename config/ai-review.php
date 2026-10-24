<?php

return [

    // Off hides the editor button and makes the review routes 404.
    'enabled' => (bool) env('AI_REVIEW_ENABLED', true),

    // The models an admin can pick per review; 'default' is used when they don't.
    'models' => [
        'sonnet' => env('AI_REVIEW_MODEL', 'claude-sonnet-5-5'),
        'opus' => env('AI_REVIEW_OPUS_MODEL', 'claude-opus-5-5'),
    ],
    'default' => 'sonnet',

    // Text sent per model call (the outline and style guide come on top), and the most one review may send.
    'max_chars_per_chunk' => 24_000,
    'max_chars_per_review' => 200_000,

    // Seconds per model call; the job's timeout is derived from it.
    'timeout' => 180,

    // Reviews each admin may start per hour.
    'rate_limit_per_hour' => 10,

    'style_guide' => resource_path('ai/style-guides/laravel-docs.md'),

];
