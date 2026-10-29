<?php

namespace Tests\Feature;

use App\Ai\Agents\DocScaffolder;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProseScaffoldApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Document $document;

    private array $topic;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-review.runner' => 'api']);
        Storage::fake('local');
        $this->actingAs($this->admin = User::factory()->admin()->create());
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Monthly Report'])->json('document.slug'))->firstOrFail();
        [$this->topic] = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", ['blocks' => [
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'The scripts build the report.']]]],
            ]],
        ]])->json('blocks');
    }

    public function test_an_admin_uploads_scripts_and_the_scaffold_runs(): void
    {
        DocScaffolder::fake([
            ['sections' => [], 'expand' => [], 'script_topics' => []],
            ['sections' => [], 'expand' => [], 'script_topics' => []],
        ])->preventStrayPrompts();

        $response = $this->post("/api/documents/{$this->document->slug}/scaffolds", [
            'files' => [
                UploadedFile::fake()->createWithContent('main.R', "db_password <- \"hunter2\"\nsource(\"R/clean.R\")\n"),
                UploadedFile::fake()->createWithContent('clean.R', "x <- 1\n"),
            ],
            'paths' => ['scripts/main.R', 'scripts/R/clean.R'],
            'entry' => 'scripts/main.R',
            'runNotes' => 'Open main.R and run it.',
            'model' => 'opus',
        ], ['Accept' => 'application/json']);

        $response->assertAccepted()
            ->assertJsonPath('review.mode', ProseReview::SCAFFOLD)
            ->assertJsonPath('review.runNotes', 'Open main.R and run it.')
            ->assertJsonPath('review.sources.0.name', 'scripts/main.R')
            ->assertJsonPath('review.sources.0.entry', true)
            ->assertJsonPath('review.sources.0.redactions', [['line' => 1, 'kind' => 'assignment']])
            ->assertJsonPath('review.sources.1.name', 'scripts/R/clean.R')
            ->assertJsonPath('command', null);

        $review = ProseReview::find($response->json('review.id'));
        $this->assertSame(ProseReview::DONE, $review->status);
        $this->assertSame('opus', $review->model);
        DocScaffolder::assertPromptedTimes(2);

        // stored privately, redacted
        $source = $review->sources->first();
        Storage::disk('local')->assertExists($source->path);
        $this->assertStringStartsWith("db_password <- \"<redacted>\"\n", $source->text());
        $this->assertFalse(str_contains($source->path, 'public'));

        $this->getJson("/api/reviews/{$review->id}/sources/{$source->id}")
            ->assertOk()
            ->assertJsonPath('source.name', 'scripts/main.R')
            ->assertJsonPath('text', $source->text());
    }

    public function test_unusable_uploads_are_refused(): void
    {
        $upload = fn (array $files, array $extra = []) => $this->post("/api/documents/{$this->document->slug}/scaffolds", ['files' => $files, ...$extra], ['Accept' => 'application/json']);

        $upload([])->assertUnprocessable()->assertJsonValidationErrors('files');
        $upload([UploadedFile::fake()->createWithContent('run.sh', 'Rscript main.R')])->assertUnprocessable()->assertJsonValidationErrors('files');
        $upload([UploadedFile::fake()->createWithContent('a.R', 'x <- 1')], ['paths' => ['../a.R']])->assertUnprocessable()->assertJsonValidationErrors('files');
        $upload([UploadedFile::fake()->createWithContent('a.R', 'x <- 1')], ['entry' => 'b.R'])->assertUnprocessable()->assertJsonValidationErrors('entry');

        config(['ai-review.scaffold.max_files' => 1]);
        $upload([UploadedFile::fake()->createWithContent('a.R', 'x'), UploadedFile::fake()->createWithContent('b.R', 'y')])->assertUnprocessable()->assertJsonValidationErrors('files');

        // an overview call and a script call is two
        config(['ai-review.scaffold.max_calls' => 1]);
        $upload([UploadedFile::fake()->createWithContent('a.R', 'x <- 1')])->assertUnprocessable()->assertJsonPath('errors.files.0', fn (string $message) => str_contains($message, 'would take 2 model calls'));
        config(['ai-review.scaffold.max_calls' => 13]);

        config(['ai-review.scaffold.max_file_kb' => 1]);
        $upload([UploadedFile::fake()->createWithContent('big.R', str_repeat('x', 4096))])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $this->assertSame(0, ProseReview::query()->count());
    }

    public function test_the_scope_must_be_a_primary_or_secondary_topic_of_this_document(): void
    {
        $upload = fn (string $blockId) => $this->post("/api/documents/{$this->document->slug}/scaffolds", [
            'files' => [UploadedFile::fake()->createWithContent('a.R', 'x <- 1')],
            'blockId' => $blockId,
        ], ['Accept' => 'application/json']);

        $upload($this->topic['children'][0]['id'])->assertUnprocessable()->assertJsonValidationErrors('blockId');
        $other = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Other'])->json('document.slug'))->firstOrFail();
        [$otherTopic] = $this->postJson("/api/documents/{$other->slug}/blocks/tree", ['blocks' => [['type' => 'PrimaryTopic', 'props' => ['title' => 'X']]]])->json('blocks');
        $upload($otherTopic['id'])->assertUnprocessable()->assertJsonValidationErrors('blockId');
    }

    public function test_without_the_editor_runner_the_upload_waits_for_claude_code(): void
    {
        config(['ai-review.runner' => 'claude-code', 'ai-review.claude_code.user' => null]);
        DocScaffolder::fake()->preventStrayPrompts();

        $response = $this->post("/api/documents/{$this->document->slug}/scaffolds", [
            'files' => [UploadedFile::fake()->createWithContent('a.R', 'x <- 1')],
        ], ['Accept' => 'application/json']);

        $id = $response->assertAccepted()->json('review.id');
        $response->assertJsonPath('review.status', ProseReview::AWAITING)->assertJsonPath('command', "/doc-scaffold --review={$id}");
        DocScaffolder::assertNeverPrompted();
    }

    public function test_non_admins_cannot_upload_or_read_sources(): void
    {
        DocScaffolder::fake([['sections' => [], 'expand' => [], 'script_topics' => []], ['sections' => [], 'expand' => [], 'script_topics' => []]]);
        $review = ProseReview::find($this->post("/api/documents/{$this->document->slug}/scaffolds", [
            'files' => [UploadedFile::fake()->createWithContent('a.R', 'x <- 1')],
        ], ['Accept' => 'application/json'])->json('review.id'));
        $source = $review->sources->first();

        $this->actingAs(User::factory()->create());
        $this->post("/api/documents/{$this->document->slug}/scaffolds", ['files' => [UploadedFile::fake()->createWithContent('a.R', 'x')]], ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson("/api/reviews/{$review->id}/sources/{$source->id}")->assertForbidden();
        $this->postJson("/api/reviews/{$review->id}/accept-all")->assertForbidden();
    }

    public function test_a_source_is_only_read_through_its_own_scaffold(): void
    {
        DocScaffolder::fake(array_fill(0, 4, ['sections' => [], 'expand' => [], 'script_topics' => []]));
        $upload = fn () => ProseReview::find($this->post("/api/documents/{$this->document->slug}/scaffolds", [
            'files' => [UploadedFile::fake()->createWithContent('a.R', 'x <- 1')],
        ], ['Accept' => 'application/json'])->json('review.id'));
        [$first, $second] = [$upload(), $upload()];

        $this->getJson("/api/reviews/{$first->id}/sources/{$second->sources->first()->id}")->assertNotFound();
    }

    public function test_scaffolds_share_the_review_rate_limit(): void
    {
        config(['ai-review.rate_limit_per_hour' => 1]);
        DocScaffolder::fake(array_fill(0, 2, ['sections' => [], 'expand' => [], 'script_topics' => []]));
        $upload = fn () => $this->post("/api/documents/{$this->document->slug}/scaffolds", ['files' => [UploadedFile::fake()->createWithContent('a.R', 'x <- 1')]], ['Accept' => 'application/json']);

        $upload()->assertAccepted();
        $upload()->assertTooManyRequests();
    }

    public function test_the_feature_can_be_turned_off(): void
    {
        $this->getJson('/api/user')->assertJsonPath('abilities.aiScaffold', true);
        $this->getJson("/api/documents/{$this->document->slug}/reviews")->assertJsonPath('canScaffold', true);

        config(['ai-review.scaffold.enabled' => false]);
        $this->getJson('/api/user')->assertJsonPath('abilities.aiScaffold', false);
        $this->getJson("/api/documents/{$this->document->slug}/reviews")->assertJsonPath('canScaffold', false);
        $this->post("/api/documents/{$this->document->slug}/scaffolds", ['files' => [UploadedFile::fake()->createWithContent('a.R', 'x')]], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_old_scripts_are_pruned_and_recent_ones_kept(): void
    {
        DocScaffolder::fake(array_fill(0, 4, ['sections' => [], 'expand' => [], 'script_topics' => []]));
        $upload = fn () => ProseReview::find($this->post("/api/documents/{$this->document->slug}/scaffolds", [
            'files' => [UploadedFile::fake()->createWithContent('a.R', 'x <- 1')],
        ], ['Accept' => 'application/json'])->json('review.id'));
        [$old, $recent] = [$upload(), $upload()];
        $old->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
        $oldPath = $old->sources->first()->path;

        $this->artisan('prose-review:prune-sources')->expectsOutputToContain('Deleted 1 uploaded scripts from 1 scaffolds.')->assertSuccessful();

        Storage::disk('local')->assertMissing($oldPath);
        $this->assertSame(0, $old->sources()->count());
        $this->assertSame(1, $recent->sources()->count());
    }
}
