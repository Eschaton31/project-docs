<?php

namespace Tests\Feature;

use App\Ai\Agents\DocScaffolder;
use App\Ai\Scaffold\SourceIntake;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScaffoldCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $workDir;

    private string $scripts;

    private User $admin;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->workDir = sys_get_temp_dir().'/prose-reviews-'.uniqid();
        $this->scripts = sys_get_temp_dir().'/r-scripts-'.uniqid();
        config(['ai-review.work_dir' => $this->workDir]);
        DocScaffolder::fake()->preventStrayPrompts();

        File::ensureDirectoryExists("{$this->scripts}/R");
        File::put("{$this->scripts}/main.R", "library(dplyr)\nsource(\"R/clean.R\")\ntoken <- \"abc\"\n");
        File::put("{$this->scripts}/R/clean.R", "clean_data <- function(df) {\n  df\n}\n");
        File::put("{$this->scripts}/README.txt", 'not a script');

        $this->actingAs($this->admin = User::factory()->admin()->create());
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Monthly Report'])->json('document.slug'))->firstOrFail();
        $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", ['blocks' => [
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'The scripts build the report.']]]],
            ]],
        ]]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);
        File::deleteDirectory($this->scripts);

        parent::tearDown();
    }

    public function test_export_from_a_folder_writes_the_instructions_and_one_prompt_per_call(): void
    {
        File::put("{$this->scripts}/notes.txt", 'Open main.R and run it top to bottom.');

        $this->artisan('prose-review:scaffold-export', [
            'document' => $this->document->slug,
            '--source' => [$this->scripts],
            '--entry' => 'main.R',
            '--run-notes-file' => "{$this->scripts}/notes.txt",
            '--user' => $this->admin->email,
        ])
            ->expectsOutputToContain('Call 2: ')
            ->expectsOutputToContain('Redacted a secret in main.R, line 3.')
            ->expectsOutputToContain('php artisan prose-review:import')
            ->assertSuccessful();

        $review = ProseReview::query()->sole();
        $this->assertTrue($review->isScaffold());
        $this->assertSame(ProseReview::AWAITING, $review->status);
        $this->assertSame(['R/clean.R', 'main.R'], $review->sources->pluck('name')->all());
        $this->assertSame([false, true], $review->sources->pluck('entry')->all());
        $this->assertCount(2, $review->snapshot['calls']);

        $dir = "{$this->workDir}/{$review->id}";
        $this->assertStringContainsString('# How to answer', File::get("{$dir}/instructions.md"));
        $this->assertStringContainsString('# Template', File::get("{$dir}/instructions.md"));
        $call = File::get("{$dir}/call-1.md");
        $this->assertStringContainsString('Write your answer to: '.str_replace('\\', '/', $dir).'/call-1.json', $call);
        $this->assertStringContainsString('«Open main.R and run it top to bottom.»', $call);
        $this->assertStringContainsString('token <- "<redacted>"', $call);
        $this->assertStringNotContainsString('abc', $call);
        DocScaffolder::assertNeverPrompted();
    }

    public function test_export_of_an_editor_upload_and_import_of_the_answers(): void
    {
        $review = $this->document->proseReviews()->create([
            'mode' => ProseReview::SCAFFOLD, 'requested_by' => $this->admin->id, 'model' => ProseReview::CLAUDE_CODE,
            'kinds' => [], 'status' => ProseReview::AWAITING,
        ]);
        (new SourceIntake)->store($review, (new SourceIntake)->prepare(SourceIntake::fromPaths([$this->scripts])), 'main.R');

        $this->artisan('prose-review:scaffold-export', ['--review' => $review->id])->assertSuccessful();
        // nothing written yet
        $this->artisan('prose-review:import', ['review' => $review->id])->expectsOutputToContain('missing')->assertFailed();

        File::put("{$this->workDir}/{$review->id}/call-1.json", json_encode([
            'sections' => [[
                'section' => 'dependencies', 'after' => 'B1',
                'blocks_json' => json_encode([['type' => 'PrimaryTopic', 'props' => ['title' => 'DEPENDENCIES'], 'children' => [
                    ['type' => 'ItemDescriptionTable', 'props' => ['itemName' => 'Package', 'descriptionName' => 'Used for', 'data' => [['item' => 'dplyr', 'description' => 'Data work']]]],
                ]]]),
                'sources' => ['code'], 'citations' => [['file' => 'F2', 'from' => 1, 'to' => 1]], 'rationale' => 'Lists the packages.',
            ]],
            'expand' => [],
            'script_topics' => [],
        ]));
        File::put("{$this->workDir}/{$review->id}/call-2.json", json_encode(['sections' => [], 'expand' => [], 'script_topics' => [[
            'file' => 'F2', 'into' => '',
            'blocks_json' => json_encode([['type' => 'SecondaryTopic', 'props' => ['title' => 'main.R'], 'children' => []]]),
            'sources' => ['code'], 'citations' => [['file' => 'F2', 'from' => 1, 'to' => 3]], 'rationale' => 'Documents main.R.',
        ]]]));

        $this->artisan('prose-review:import', ['review' => $review->id])
            ->expectsOutputToContain('Saved 2 suggestions')
            ->assertSuccessful();

        $review->refresh();
        $this->assertSame(ProseReview::DONE, $review->status);
        $this->assertSame(['dependencies', 'script-reference'], $review->suggestions()->orderBy('id')->pluck('rule')->all());
        $this->assertSame([ProseSuggestion::SCAFFOLD], $review->suggestions()->pluck('kind')->unique()->values()->all());
    }

    public function test_export_refuses_what_it_cannot_use(): void
    {
        $this->artisan('prose-review:scaffold-export', ['document' => 'nope', '--source' => [$this->scripts]])->assertFailed();
        $this->artisan('prose-review:scaffold-export', ['document' => $this->document->slug])->expectsOutputToContain('--source')->assertFailed();
        $this->artisan('prose-review:scaffold-export', ['document' => $this->document->slug, '--source' => ["{$this->scripts}/README.txt"]])->expectsOutputToContain("isn't an R script")->assertFailed();
        $this->artisan('prose-review:scaffold-export', ['--review' => 999])->expectsOutputToContain("isn't waiting for Claude Code")->assertFailed();

        $this->assertSame(0, ProseReview::query()->count());
    }
}
