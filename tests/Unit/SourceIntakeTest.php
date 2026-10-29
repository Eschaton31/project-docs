<?php

namespace Tests\Unit;

use App\Ai\Scaffold\SourceIntake;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceIntakeTest extends TestCase
{
    public function test_scripts_are_normalized_and_redacted(): void
    {
        $prepared = (new SourceIntake)->prepare([
            ['name' => 'R\\clean.R', 'contents' => "\xEF\xBB\xBFx <- 1\r\npassword <- \"hunter2\"\r\n"],
            ['name' => 'C:/work/./load.r', 'contents' => "caf\xE9 <- 2\n"],
        ]);

        $this->assertSame('R/clean.R', $prepared[0]['name']);
        $this->assertSame("x <- 1\npassword <- \"<redacted>\"\n", $prepared[0]['text']);
        $this->assertSame([['line' => 2, 'kind' => 'assignment']], $prepared[0]['redactions']);
        $this->assertSame('work/load.r', $prepared[1]['name']);
        // Windows-1252 é converted to UTF-8
        $this->assertSame("café <- 2\n", $prepared[1]['text']);
    }

    /**
     * @return array<string, array{0: list<array{name: string, contents: string}>, 1: string}>
     */
    public static function refused(): array
    {
        return [
            'nothing' => [[], 'at least one'],
            'not an R script' => [[['name' => 'run.sh', 'contents' => 'Rscript main.R']], "isn't an R script"],
            'R Markdown' => [[['name' => 'report.Rmd', 'contents' => '# Report']], "isn't an R script"],
            'binary' => [[['name' => 'data.R', 'contents' => "RDX3\0\0"]], "isn't a text file"],
            'empty' => [[['name' => 'empty.R', 'contents' => "\n  \n"]], 'is empty'],
            'twice' => [[['name' => 'a.R', 'contents' => 'x'], ['name' => './a.R', 'contents' => 'y']], 'added twice'],
            'outside the upload' => [[['name' => '../secret/a.R', 'contents' => 'x']], "isn't a usable file name"],
        ];
    }

    #[DataProvider('refused')]
    public function test_unusable_files_are_refused(array $files, string $message): void
    {
        try {
            (new SourceIntake)->prepare($files);
            $this->fail('Expected the files to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()['files'][0]);
        }
    }

    public function test_limits_apply(): void
    {
        config(['ai-review.scaffold.max_files' => 2, 'ai-review.scaffold.max_file_kb' => 1, 'ai-review.scaffold.max_source_chars' => 1500]);
        $intake = new SourceIntake;

        $this->assertRefused(fn () => $intake->prepare(array_map(fn ($i) => ['name' => "{$i}.R", 'contents' => 'x'], [1, 2, 3])), 'at most 2');
        $this->assertRefused(fn () => $intake->prepare([['name' => 'big.R', 'contents' => str_repeat('x', 1025)]]), 'larger than 1 KB');
        $this->assertRefused(fn () => $intake->prepare([['name' => 'a.R', 'contents' => str_repeat('x', 1000)], ['name' => 'b.R', 'contents' => str_repeat('x', 1000)]]), 'too long');
    }

    public function test_folders_are_read_with_their_relative_paths_and_only_r_files(): void
    {
        $dir = sys_get_temp_dir().'/source-intake-'.uniqid();
        File::ensureDirectoryExists("{$dir}/R");
        File::put("{$dir}/main.R", 'source("R/clean.R")');
        File::put("{$dir}/R/clean.R", 'x <- 1');
        File::put("{$dir}/notes.txt", 'ignored');

        try {
            $files = SourceIntake::fromPaths([$dir]);
        } finally {
            File::deleteDirectory($dir);
        }

        $this->assertSame(['R/clean.R', 'main.R'], array_map(fn ($file) => str_replace('\\', '/', $file['name']), $files));
    }

    private function assertRefused(\Closure $call, string $message): void
    {
        try {
            $call();
            $this->fail('Expected the files to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()['files'][0]);
        }
    }
}
