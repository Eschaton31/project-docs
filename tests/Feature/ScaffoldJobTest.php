<?php

namespace Tests\Feature;

use App\Ai\Agents\DocScaffolder;
use App\Ai\Scaffold\SourceIntake;
use App\Jobs\ReviewProse;
use App\Models\Block;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\User;
use App\Services\DocumentTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScaffoldJobTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = "library(dplyr)\nsource(\"R/clean.R\")\nraw <- read.csv(\"in.csv\")\nresult <- clean_data(raw)\n";

    private const CLEAN = "clean_data <- function(df) {\n  df |> filter(!is.na(id))\n}\n";

    private User $admin;

    private Document $document;

    /** top-level blocks: B1 OVERVIEW, B4 USAGE (not a template section) */
    private array $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-review.runner' => 'api']);
        Storage::fake('local');
        $this->actingAs($this->admin = User::factory()->admin()->create());
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Monthly Report'])->json('document.slug'))->firstOrFail();
        $this->blocks = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", ['blocks' => [
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'The scripts build the monthly report.']]]],
            ]],
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'USAGE'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'Ask the data team for access.']]]],
            ]],
        ]])->assertCreated()->json('blocks');
    }

    public function test_the_overview_and_script_calls_carry_the_run_notes_index_document_and_source(): void
    {
        $this->fakeAnswers();

        ReviewProse::dispatch($review = $this->scaffold());

        DocScaffolder::assertPromptedTimes(2);
        DocScaffolder::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Draft the template\'s sections other than script-reference')
            && $prompt->contains('«Open main.R, set the month at the top, then run it all.»')
            && $prompt->contains('"packages": [')
            && $prompt->contains('«The scripts build the monthly report.»')
            && $prompt->contains('1| library(dplyr)')
            && $prompt->contains('F1 main.R (4 lines), opened first')
            && $prompt->contains('B1 OVERVIEW'));
        DocScaffolder::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Write the script-reference topic for: F1 main.R, F2 R/clean.R')
            && $prompt->contains('A script\'s topic is a SecondaryTopic.'));

        $review->refresh();
        $this->assertSame(ProseReview::DONE, $review->status);
        $this->assertSame($this->document->fresh()->seq, $review->document_seq);
    }

    public function test_suggestions_are_saved_in_template_order_and_bad_ones_dropped(): void
    {
        $this->fakeAnswers(extraSections: [
            // R code that isn't in the scripts
            $this->section('configuration', 'B4', 'CONFIGURATION', [['type' => 'CodeBlock', 'props' => ['lang' => 'r', 'code' => 'month <- "2026-09"']]]),
        ]);

        ReviewProse::dispatch($review = $this->scaffold());

        $suggestions = $review->suggestions()->orderBy('id')->get();
        $this->assertSame(['overview', 'before-you-start', 'dependencies', 'script-reference', 'troubleshooting'], $suggestions->pluck('rule')->all());
        $this->assertSame([ProseSuggestion::EXPAND, ProseSuggestion::SCAFFOLD, ProseSuggestion::SCAFFOLD, ProseSuggestion::SCAFFOLD, ProseSuggestion::SCAFFOLD], $suggestions->pluck('kind')->all());
        $this->assertSame([['file' => 'F1', 'from' => 1, 'to' => 1]], $suggestions[2]->citations);
    }

    public function test_an_empty_document_gets_new_sections(): void
    {
        Block::query()->where('document_id', $this->document->id)->delete();
        DocScaffolder::fake([
            ['sections' => [$this->section('overview', '', 'OVERVIEW'), $this->section('dependencies', '', 'DEPENDENCIES')], 'expand' => [], 'script_topics' => []],
            ['sections' => [], 'expand' => [], 'script_topics' => []],
        ])->preventStrayPrompts();

        ReviewProse::dispatch($review = $this->scaffold());

        DocScaffolder::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('The document is empty.'));
        $this->acceptInOrder($review, ['dependencies', 'overview']);
        $this->assertSame(['OVERVIEW', 'DEPENDENCIES'], $this->titles());
    }

    /**
     * @return array<string, array{0: list<string>}>
     */
    public static function acceptOrders(): array
    {
        return [
            'template order' => [['before-you-start', 'dependencies', 'script-reference', 'troubleshooting']],
            'reversed' => [['troubleshooting', 'script-reference', 'dependencies', 'before-you-start']],
            'mixed' => [['dependencies', 'troubleshooting', 'before-you-start', 'script-reference']],
            'mixed again' => [['script-reference', 'before-you-start', 'troubleshooting', 'dependencies']],
        ];
    }

    #[DataProvider('acceptOrders')]
    public function test_sections_land_in_template_order_whichever_order_they_are_accepted_in(array $order): void
    {
        $this->fakeAnswers();
        ReviewProse::dispatch($review = $this->scaffold());

        $this->acceptInOrder($review, $order);

        $this->assertSame(['OVERVIEW', 'BEFORE YOU START', 'DEPENDENCIES', 'SCRIPT REFERENCE', 'USAGE', 'TROUBLESHOOTING'], $this->titles());
    }

    public function test_accepting_a_section_records_its_block_and_one_insert_revision(): void
    {
        $this->fakeAnswers();
        ReviewProse::dispatch($review = $this->scaffold());
        $suggestion = $review->suggestions()->where('rule', 'dependencies')->first();

        $this->postJson("/api/suggestions/{$suggestion->id}/accept")->assertOk()->assertJsonPath('change.op', 'block.insert');

        $this->assertSame(ProseSuggestion::ACCEPTED, $suggestion->fresh()->status);
        $this->assertSame('DEPENDENCIES', Block::find($suggestion->fresh()->inserted_id)->props['title']);
    }

    public function test_when_the_anchor_is_gone_sections_are_placed_by_the_ones_already_accepted(): void
    {
        $this->fakeAnswers();
        ReviewProse::dispatch($review = $this->scaffold());
        $this->acceptInOrder($review, ['dependencies']);

        // OVERVIEW, which before-you-start was to follow, is deleted
        $this->deleteJson("/api/blocks/{$this->blocks[0]['id']}", ['version' => Block::find($this->blocks[0]['id'])->version])->assertOk();
        $this->acceptInOrder($review, ['before-you-start']);

        $this->assertSame(['BEFORE YOU START', 'DEPENDENCIES', 'USAGE'], $this->titles());
    }

    public function test_when_the_anchor_moved_sections_are_placed_by_the_ones_already_accepted(): void
    {
        // inside USAGE, both sections follow its paragraph (B5)
        DocScaffolder::fake([
            ['sections' => [
                $this->section('before-you-start', 'B5', 'Before You Start', type: 'SecondaryTopic'),
                $this->section('dependencies', 'B5', 'Dependencies', type: 'SecondaryTopic'),
            ], 'expand' => [], 'script_topics' => []],
            ['sections' => [], 'expand' => [], 'script_topics' => []],
        ])->preventStrayPrompts();
        ReviewProse::dispatch($review = $this->scaffold(['scope_block_id' => $this->blocks[1]['id']]));
        $this->acceptInOrder($review, ['before-you-start']);

        // the paragraph moves into OVERVIEW: dependencies follows the section before it in the template instead
        $paragraph = Block::find($this->blocks[1]['children'][0]['id']);
        $this->patchJson("/api/blocks/{$paragraph->id}/move", ['parentId' => $this->blocks[0]['id'], 'version' => $paragraph->version])->assertOk();
        $this->acceptInOrder($review, ['dependencies']);

        $usage = app(DocumentTree::class)->build($this->document->fresh())[1];
        $this->assertSame(['Before You Start', 'Dependencies'], array_map(fn ($child) => $child['props']['title'], $usage['children']));
    }

    public function test_an_accepted_section_that_was_undone_is_not_a_neighbour(): void
    {
        $this->fakeAnswers();
        ReviewProse::dispatch($review = $this->scaffold());
        $this->acceptInOrder($review, ['dependencies']);

        $dependencies = Block::find($review->suggestions()->where('rule', 'dependencies')->value('inserted_id'));
        $this->deleteJson("/api/blocks/{$dependencies->id}", ['version' => $dependencies->version])->assertOk();
        $this->acceptInOrder($review, ['before-you-start']);

        $this->assertSame(['OVERVIEW', 'BEFORE YOU START', 'USAGE'], $this->titles());
    }

    public function test_accept_all_inserts_every_section_and_addition_in_template_order(): void
    {
        $this->fakeAnswers();
        ReviewProse::dispatch($review = $this->scaffold());

        $this->postJson("/api/reviews/{$review->id}/accept-all")
            ->assertOk()
            ->assertJsonPath('accepted', 5)
            ->assertJsonCount(5, 'changes');

        $this->assertSame(['OVERVIEW', 'BEFORE YOU START', 'DEPENDENCIES', 'SCRIPT REFERENCE', 'USAGE', 'TROUBLESHOOTING'], $this->titles());
        // the overview addition went in at the end of the existing topic
        $overview = app(DocumentTree::class)->build($this->document->fresh())[0];
        $this->assertSame('Placeholder', end($overview['children'])['type']);
    }

    public function test_inside_a_topic_sections_are_placed_among_its_children(): void
    {
        DocScaffolder::fake([
            ['sections' => [
                // after USAGE's paragraph (B5), and first
                $this->section('troubleshooting', 'B5', 'Troubleshooting', type: 'SecondaryTopic'),
                $this->section('dependencies', '', 'Dependencies', type: 'SecondaryTopic'),
            ], 'expand' => [], 'script_topics' => []],
            ['sections' => [], 'expand' => [], 'script_topics' => []],
        ])->preventStrayPrompts();

        ReviewProse::dispatch($review = $this->scaffold(['scope_block_id' => $this->blocks[1]['id']]));
        $this->acceptInOrder($review, ['troubleshooting', 'dependencies']);

        $usage = app(DocumentTree::class)->build($this->document->fresh())[1];
        $this->assertSame(['SecondaryTopic', 'Paragraph', 'SecondaryTopic'], array_column($usage['children'], 'type'));
        $this->assertSame(['Dependencies', 'Troubleshooting'], array_values(array_filter(array_map(fn ($child) => ((array) $child['props'])['title'] ?? null, $usage['children']))));
    }

    public function test_a_section_for_a_deleted_topic_is_stale(): void
    {
        DocScaffolder::fake([
            ['sections' => [$this->section('dependencies', '', 'Dependencies', type: 'SecondaryTopic')], 'expand' => [], 'script_topics' => []],
            ['sections' => [], 'expand' => [], 'script_topics' => []],
        ])->preventStrayPrompts();
        ReviewProse::dispatch($review = $this->scaffold(['scope_block_id' => $this->blocks[1]['id']]));
        $this->deleteJson("/api/blocks/{$this->blocks[1]['id']}", ['version' => Block::find($this->blocks[1]['id'])->version])->assertOk();

        $suggestion = $review->suggestions()->first();
        $this->postJson("/api/suggestions/{$suggestion->id}/accept")->assertConflict();
        $this->assertSame(ProseSuggestion::STALE, $suggestion->fresh()->status);
    }

    private function scaffold(array $attributes = []): ProseReview
    {
        $review = $this->document->proseReviews()->create([
            'mode' => ProseReview::SCAFFOLD,
            'requested_by' => $this->admin->id,
            'model' => 'sonnet',
            'kinds' => [],
            'run_notes' => 'Open main.R, set the month at the top, then run it all.',
            'status' => ProseReview::QUEUED,
            ...$attributes,
        ]);
        $intake = new SourceIntake;
        $intake->store($review, $intake->prepare([['name' => 'main.R', 'contents' => self::MAIN], ['name' => 'R/clean.R', 'contents' => self::CLEAN]]), 'main.R');

        return $review;
    }

    /**
     * Overview: an addition to OVERVIEW, Before You Start and Dependencies after it, Troubleshooting after USAGE;
     * scripts: a topic per script.
     */
    private function fakeAnswers(array $extraSections = []): void
    {
        DocScaffolder::fake([
            [
                'sections' => [
                    $this->section('troubleshooting', 'B4', 'TROUBLESHOOTING'),
                    $this->section('dependencies', 'B1', 'DEPENDENCIES', [['type' => 'ItemDescriptionTable', 'props' => ['itemName' => 'Package', 'descriptionName' => 'Used for', 'data' => [['item' => 'dplyr', 'description' => 'Filtering']]]]], ['code'], [['file' => 'F1', 'from' => 1, 'to' => 1]]),
                    $this->section('before-you-start', 'B1', 'BEFORE YOU START'),
                    ...$extraSections,
                ],
                'expand' => [[
                    'section' => 'overview', 'anchor' => 'B1', 'position' => 'end',
                    'blocks_json' => json_encode([['type' => 'Placeholder', 'props' => ['text' => 'Who uses the report?']]]),
                    'sources' => ['document'], 'citations' => [], 'rationale' => 'The overview does not say who reads the report.',
                ]],
                'script_topics' => [],
            ],
            [
                'sections' => [],
                'expand' => [],
                'script_topics' => [
                    $this->scriptTopic('F1', 'main.R'),
                    $this->scriptTopic('F2', 'R/clean.R'),
                ],
            ],
        ])->preventStrayPrompts();
    }

    private function section(string $key, string $after, string $title, array $children = [], array $sources = ['run_notes'], array $citations = [], string $type = 'PrimaryTopic'): array
    {
        return [
            'section' => $key, 'after' => $after,
            'blocks_json' => json_encode([['type' => $type, 'props' => ['title' => $title], 'children' => $children ?: [['type' => 'Placeholder', 'props' => ['text' => 'to be written']]]]]),
            'sources' => $sources, 'citations' => $citations, 'rationale' => 'The document has no such section.',
        ];
    }

    private function scriptTopic(string $file, string $title): array
    {
        return [
            'file' => $file, 'into' => '',
            'blocks_json' => json_encode([['type' => 'SecondaryTopic', 'props' => ['title' => $title], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'What it does.']]]],
            ]]]),
            'sources' => ['code'], 'citations' => [['file' => $file, 'from' => 1, 'to' => 2]], 'rationale' => 'Documents the script.',
        ];
    }

    private function acceptInOrder(ProseReview $review, array $rules): void
    {
        foreach ($rules as $rule) {
            $suggestion = $review->suggestions()->where('kind', ProseSuggestion::SCAFFOLD)->where('rule', $rule)->firstOrFail();
            $this->postJson("/api/suggestions/{$suggestion->id}/accept")->assertOk();
        }
    }

    /**
     * @return list<string> the document's top-level titles, in order
     */
    private function titles(): array
    {
        return array_map(fn (array $node) => $node['props']['title'], app(DocumentTree::class)->build($this->document->fresh()));
    }
}
