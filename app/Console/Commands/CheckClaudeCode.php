<?php

namespace App\Console\Commands;

use App\Ai\Reviewers\ClaudeCodeReviewer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Run on the server as the account that runs the ai-review queue worker (composer prod): reviews started from
 * the editor run Claude Code as that account, with its Claude login.
 */
#[Signature('prose-review:check-claude')]
#[Description('Check that this account can run Claude Code for AI prose reviews')]
class CheckClaudeCode extends Command
{
    public function handle(ClaudeCodeReviewer $claude): int
    {
        $owner = config('ai-review.claude_code.user');
        if (config('ai-review.runner') !== 'claude-code') {
            $this->warn('AI_REVIEW_RUNNER is not claude-code, so reviews use the API; checking Claude Code anyway.');
        }
        if (! $owner) {
            $this->warn('AI_REVIEW_CLAUDE_CODE_USER is not set, so nobody can start reviews from the editor.');
        }

        try {
            $version = $claude->check();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            $this->line('Install Claude Code and sign in (run `claude` once) as this account, or set AI_REVIEW_CLAUDE_BINARY to its full path.');

            return self::FAILURE;
        }

        $this->info("Claude Code {$version} answered.".($owner ? " {$owner} can start reviews from the editor." : ''));

        return self::SUCCESS;
    }
}
