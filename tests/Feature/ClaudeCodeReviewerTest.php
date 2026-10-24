<?php

namespace Tests\Feature;

use App\Ai\Agents\ProseReviewer;
use App\Ai\Reviewers\ChunkReviewer;
use App\Ai\Reviewers\ClaudeCodeReviewer;
use App\Jobs\ReviewProse;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class ClaudeCodeReviewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-review.runner' => 'claude-code', 'ai-review.claude_code.binary' => 'claude']);
        ProseReviewer::fake()->preventStrayPrompts();
    }

    public function test_it_runs_claude_code_with_no_tools_settings_or_project_context(): void
    {
        Process::fake(['*' => Process::result($this->answer(['text' => [], 'restructure' => []]))]);

        // kept alive: its temporary folder (with the instructions) goes when it does
        $reviewer = new ClaudeCodeReviewer;
        $answer = $reviewer->review('Review this chunk.', 'opus');

        $this->assertSame(['text' => [], 'restructure' => []], $answer['output']);
        $this->assertSame(['input_tokens' => 1600, 'output_tokens' => 40], $answer['usage']);
        Process::assertRan(function (PendingProcess $process) {
            $command = $process->command;
            $schema = json_decode($command[array_search('--json-schema', $command) + 1], true);
            $instructions = $command[array_search('--system-prompt-file', $command) + 1];

            return $command[0] === 'claude'
                && in_array('--print', $command, true)
                && in_array('--restricted', $command, true)
                && $command[array_search('--tools', $command) + 1] === ''
                && in_array('--strict-mcp-config', $command, true)
                && in_array('--no-session-persistence', $command, true)
                && $command[array_search('--model', $command) + 1] === 'opus'
                && $schema['required'] === ['text', 'restructure']
                && str_contains(file_get_contents($instructions), 'Laravel documentation')
                // an empty folder outside the project, so no CLAUDE.md or project settings apply
                && ! str_starts_with(realpath($process->path), base_path())
                && $process->input === 'Review this chunk.';
        });
    }

    public function test_a_failed_run_says_why(): void
    {
        Process::fake(['*' => Process::result(json_encode(['type' => 'result', 'is_error' => true, 'subtype' => 'error_during_execution', 'result' => 'Not logged in · Please run /login']), exitCode: 1)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not logged in');
        (new ClaudeCodeReviewer)->review('Review this chunk.', 'sonnet');
    }

    public function test_an_answer_without_structured_output_is_a_failure(): void
    {
        Process::fake(['*' => Process::result(json_encode(['type' => 'result', 'is_error' => false, 'subtype' => 'success', 'result' => 'Here are my thoughts…']))]);

        $this->expectExceptionMessage('did not return a structured answer');
        (new ClaudeCodeReviewer)->review('Review this chunk.', 'sonnet');
    }

    public function test_the_review_job_uses_claude_code_with_the_claude_code_runner(): void
    {
        Process::fake(['*' => Process::result($this->answer(['text' => [[
            'segment' => 'S2', 'original' => 'In order to install it', 'suggested' => 'To install it',
            'rule' => 'hedging', 'rationale' => 'Style guide.', 'severity' => 'minor',
        ]], 'restructure' => []]))]);
        $this->assertInstanceOf(ClaudeCodeReviewer::class, app(ChunkReviewer::class));

        $this->actingAs($admin = User::factory()->admin()->create());
        $document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Guide'])->json('document.slug'))->firstOrFail();
        $this->postJson("/api/documents/{$document->slug}/blocks/tree", ['blocks' => [
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'SETUP'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'In order to install it, run the installer.']]]],
            ]],
        ]]);
        $review = $document->proseReviews()->create(['requested_by' => $admin->id, 'model' => 'sonnet', 'kinds' => ['text'], 'status' => ProseReview::QUEUED]);

        ReviewProse::dispatch($review);

        $this->assertSame(ProseReview::DONE, $review->fresh()->status);
        $this->assertSame('To install it', $review->suggestions()->sole()->suggested);
        $this->assertSame(1600, $review->fresh()->input_tokens);
        ProseReviewer::assertNeverPrompted();
        Process::assertRan(fn (PendingProcess $process) => str_contains($process->input, '«In order to install it, run the installer.»'));
    }

    public function test_the_job_timeout_follows_the_runner(): void
    {
        $review = new ProseReview;

        $this->assertSame(600 * 9 + 60, (new ReviewProse($review))->timeout);
        $this->assertLessThan(config('queue.connections.ai-review.retry_after'), (new ReviewProse($review))->timeout);

        config(['ai-review.runner' => 'api']);
        $this->assertSame(180 * 9 + 60, (new ReviewProse($review))->timeout);
    }

    private function answer(array $output): string
    {
        return json_encode([
            'type' => 'result',
            'subtype' => 'success',
            'is_error' => false,
            'result' => json_encode($output),
            'structured_output' => $output,
            'usage' => ['input_tokens' => 100, 'cache_creation_input_tokens' => 1500, 'cache_read_input_tokens' => 0, 'output_tokens' => 40],
        ]);
    }
}
