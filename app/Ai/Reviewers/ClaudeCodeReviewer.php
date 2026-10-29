<?php

namespace App\Ai\Reviewers;

use App\Ai\Agents\ProseReviewer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\Anthropic\AnthropicSchemaSanitizer;
use Laravel\Ai\ObjectSchema;
use RuntimeException;

/**
 * Claude Code in print mode, signed in with the plan of whoever runs the ai-review queue worker
 * (runner "claude-code").
 *
 * The document text comes from editors, so Claude Code runs with no tools at all, without user or project
 * settings, hooks or MCP servers, and from an empty temporary folder: the most a hostile paragraph can do is
 * produce suggestions, which are still validated and need an admin to accept them.
 */
class ClaudeCodeReviewer implements ChunkReviewer
{
    private ?string $dir = null;

    public function review(string $prompt, string $model, string $agent = ProseReviewer::class): array
    {
        $result = Process::path($this->dir())
            ->timeout($this->timeout())
            ->input($prompt)
            ->run($this->command($model, $agent));

        $answer = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($answer) || ($answer['is_error'] ?? true)) {
            throw new RuntimeException('Claude Code failed: '.$this->reason($result->exitCode(), $answer, $result->errorOutput()));
        }
        if (! is_array($answer['structured_output'] ?? null)) {
            throw new RuntimeException('Claude Code did not return a structured answer.');
        }

        $usage = $answer['usage'] ?? [];

        return [
            'output' => $answer['structured_output'],
            'usage' => [
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            ],
        ];
    }

    public function timeout(): int
    {
        return config('ai-review.claude_code.timeout');
    }

    /**
     * A one-line call run the same way as a review, to see whether this account can use Claude Code (installed,
     * signed in). Returns the version; throws with what went wrong.
     */
    public function check(): string
    {
        $binary = config('ai-review.claude_code.binary');
        $version = Process::timeout(30)->run([$binary, '--version']);
        if (! $version->successful()) {
            throw new RuntimeException("Can't run [{$binary}]: ".Str::limit(trim($version->errorOutput()) ?: "exit code {$version->exitCode()}", 300));
        }

        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];
        $result = Process::path($this->dir())->timeout(120)->input('Answer with ok set to true.')->run([
            $binary, '--print', '--restricted', '--tools', '', '--strict-mcp-config', '--no-session-persistence',
            '--output-format', 'json', '--json-schema', json_encode($schema), '--model', 'haiku',
        ]);
        $answer = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($answer) || ($answer['is_error'] ?? true) || ! ($answer['structured_output']['ok'] ?? false)) {
            throw new RuntimeException('Claude Code ran but could not answer: '.$this->reason($result->exitCode(), $answer, $result->errorOutput()));
        }

        return trim($version->output());
    }

    /**
     * @return list<string>
     */
    public function command(string $model, string $agent = ProseReviewer::class): array
    {
        return [
            config('ai-review.claude_code.binary'),
            '--print',
            // no Bash, file, web or any other tool; user/project/local settings (and their hooks) ignored
            '--restricted', '--tools', '', '--strict-mcp-config',
            '--no-session-persistence',
            '--system-prompt-file', $this->instructionsPath($agent),
            '--output-format', 'json',
            '--json-schema', json_encode(self::schema($agent), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            '--model', config('ai-review.claude_code.models')[$model],
        ];
    }

    /**
     * The same answer schema the API agent is held to.
     */
    public static function schema(string $agent = ProseReviewer::class): array
    {
        return AnthropicSchemaSanitizer::sanitize(
            (new ObjectSchema((new $agent)->schema(new JsonSchemaTypeFactory)))->toSchema()
        );
    }

    public function __destruct()
    {
        if ($this->dir) {
            File::deleteDirectory($this->dir);
        }
    }

    /**
     * An empty folder outside the project, so Claude Code finds no CLAUDE.md or project settings.
     */
    private function dir(): string
    {
        if (! $this->dir) {
            $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prose-review-'.Str::random(12);
            File::ensureDirectoryExists($this->dir);
        }

        return $this->dir;
    }

    private function instructionsPath(string $agent): string
    {
        $path = $this->dir().DIRECTORY_SEPARATOR.class_basename($agent).'.md';
        if (! File::exists($path)) {
            File::put($path, (string) (new $agent)->instructions());
        }

        return $path;
    }

    private function reason(int $exitCode, mixed $answer, string $stderr): string
    {
        $message = is_array($answer) ? trim(($answer['subtype'] ?? '').' '.($answer['result'] ?? '')) : '';

        return Str::limit($message ?: trim($stderr) ?: "exit code {$exitCode}", 500);
    }
}
