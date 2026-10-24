<?php

namespace Tests\Feature;

use App\Exceptions\LockedException;
use App\Exceptions\SuggestionStaleException;
use App\Models\Block;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\User;
use App\Services\ProseReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProseSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = 'In order to install it, you need PHP, a database and Chrome.';

    private User $admin;

    private Document $document;

    private ProseReview $review;

    /** @var array{topic: array, paragraph: array, text: array, code: array} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin = User::factory()->admin()->create());
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Setup Guide'])->json('document.slug'))->firstOrFail();
        [$topic] = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", ['blocks' => [
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'SETUP'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => self::TEXT]]]],
                ['type' => 'CodeBlock', 'props' => ['lang' => 'bash', 'code' => 'composer install']],
            ]],
        ]])->json('blocks');
        $this->ids = [
            'topic' => $topic['id'],
            'paragraph' => $topic['children'][0]['id'],
            'text' => $topic['children'][0]['children'][0]['id'],
            'code' => $topic['children'][1]['id'],
        ];

        $this->review = $this->document->proseReviews()->create([
            'requested_by' => $this->admin->id, 'model' => 'sonnet', 'kinds' => ['text', 'restructure'], 'status' => ProseReview::DONE,
        ]);
    }

    public function test_accepting_a_text_suggestion_changes_only_that_text(): void
    {
        $suggestion = $this->textSuggestion('In order to install it', 'To install it');

        $revision = $this->service()->accept($suggestion, $this->admin);

        $this->assertSame('block.update', $revision->op);
        $this->assertSame('To install it, you need PHP, a database and Chrome.', $this->props('text')['html']);
        $this->assertSame(ProseSuggestion::ACCEPTED, $suggestion->fresh()->status);
        $this->assertSame($this->admin->id, $suggestion->fresh()->resolved_by);
    }

    public function test_a_text_suggestion_still_applies_after_unrelated_edits_to_its_block(): void
    {
        $suggestion = $this->textSuggestion('In order to install it', 'To install it');
        $this->patchJson("/api/blocks/{$this->ids['text']}", ['version' => 1, 'props' => ['html' => self::TEXT.' Then run it.']])->assertOk();

        $this->service()->accept($suggestion, $this->admin);

        $this->assertSame('To install it, you need PHP, a database and Chrome. Then run it.', $this->props('text')['html']);
    }

    public function test_a_text_suggestion_whose_original_is_gone_becomes_stale(): void
    {
        $suggestion = $this->textSuggestion('In order to install it', 'To install it');
        $this->patchJson("/api/blocks/{$this->ids['text']}", ['version' => 1, 'props' => ['html' => 'Rewritten by hand.']])->assertOk();

        try {
            $this->service()->accept($suggestion, $this->admin);
            $this->fail('Expected the suggestion to be stale.');
        } catch (SuggestionStaleException $exception) {
            $this->assertSame(409, $exception->render()->getStatusCode());
        }

        $this->assertSame(ProseSuggestion::STALE, $suggestion->fresh()->status);
        $this->assertSame('Rewritten by hand.', $this->props('text')['html']);
    }

    public function test_accepting_a_restructure_replaces_the_blocks_in_place_as_one_change(): void
    {
        $other = $this->textSuggestion('In order to install it', 'To install it');
        $suggestion = $this->restructureSuggestion();
        $seq = $this->document->fresh()->seq;

        $revision = $this->service()->accept($suggestion, $this->admin);

        $this->assertSame('block.replace', $revision->op);
        $this->assertSame($seq + 1, $this->document->fresh()->seq);
        $this->assertSame([$this->ids['paragraph']], $revision->after['removed']);
        $this->assertSoftDeleted('blocks', ['id' => $this->ids['paragraph']]);
        $this->assertSoftDeleted('blocks', ['id' => $this->ids['text']]);

        $children = $this->tree()[0]['children'];
        $this->assertSame(['Paragraph', 'BulletList', 'CodeBlock'], array_column($children, 'type'));
        $this->assertSame('To install it, you need:', $children[0]['children'][0]['props']['html']);
        $this->assertSame($this->ids['code'], $children[2]['id']);

        $this->assertSame(ProseSuggestion::ACCEPTED, $suggestion->fresh()->status);
        $this->assertSame(ProseSuggestion::STALE, $other->fresh()->status);
    }

    public function test_a_restructure_becomes_stale_when_anything_inside_changed(): void
    {
        $suggestion = $this->restructureSuggestion();
        $this->patchJson("/api/blocks/{$this->ids['text']}", ['version' => 1, 'props' => ['html' => self::TEXT.' Edited.']])->assertOk();

        $this->expectException(SuggestionStaleException::class);
        try {
            $this->service()->accept($suggestion, $this->admin);
        } finally {
            $this->assertSame(ProseSuggestion::STALE, $suggestion->fresh()->status);
            $this->assertSame(['Paragraph', 'CodeBlock'], array_column($this->tree()[0]['children'], 'type'));
        }
    }

    public function test_a_block_another_tab_is_editing_is_refused(): void
    {
        $suggestion = $this->textSuggestion('In order to install it', 'To install it');
        $this->withHeader('X-Client-Id', (string) Str::uuid())
            ->postJson("/api/documents/{$this->document->slug}/locks", ['target' => "block:{$this->ids['text']}"])
            ->assertSuccessful();
        // the service reads the tab from the current request; make it a different one from the lock holder
        $this->app->instance('request', Request::create('/', server: ['HTTP_X_CLIENT_ID' => (string) Str::uuid()]));

        $this->expectException(LockedException::class);
        try {
            $this->service()->accept($suggestion, $this->admin);
        } finally {
            $this->assertSame(ProseSuggestion::PENDING, $suggestion->fresh()->status);
        }
    }

    public function test_a_resolved_suggestion_cannot_be_resolved_again(): void
    {
        $suggestion = $this->textSuggestion('In order to install it', 'To install it');
        $this->service()->dismiss($suggestion, $this->admin);

        $this->assertSame(ProseSuggestion::DISMISSED, $suggestion->fresh()->status);
        $this->expectException(ValidationException::class);
        $this->service()->accept($suggestion->fresh(), $this->admin);
    }

    public function test_accepting_a_rule_applies_its_pending_text_suggestions_and_counts_the_rest(): void
    {
        $this->textSuggestion('In order to install it', 'To install it', 'hedging');
        $this->textSuggestion('a database and Chrome', 'a database, and Chrome', 'grammar');
        $this->textSuggestion('not there any more', 'x', 'hedging');

        $result = $this->service()->acceptRule($this->review, 'hedging', $this->admin);

        $this->assertSame([1, 1, 0], [$result['accepted'], $result['stale'], $result['locked']]);
        $this->assertSame('To install it, you need PHP, a database and Chrome.', $this->props('text')['html']);
    }

    public function test_an_accepted_restructure_can_be_undone_and_redone_through_the_replace_endpoint(): void
    {
        $revision = $this->service()->accept($this->restructureSuggestion(), $this->admin);
        $inserted = $revision->after['blocks'];
        $original = $revision->before['blocks'];

        $undo = $this->postJson("/api/documents/{$this->document->slug}/blocks/replace", [
            'blocks' => array_map(fn ($block) => ['id' => $block['id'], 'version' => $block['version']], $inserted),
            'nodes' => $original,
        ])->assertOk();

        $children = $this->tree()[0]['children'];
        $this->assertSame(['Paragraph', 'CodeBlock'], array_column($children, 'type'));
        $this->assertSame(self::TEXT, $children[0]['children'][0]['props']['html']);
        $this->assertSame(array_column($inserted, 'id'), $undo->json('removed'));

        $restored = $undo->json('blocks');
        $this->postJson("/api/documents/{$this->document->slug}/blocks/replace", [
            'blocks' => array_map(fn ($block) => ['id' => $block['id'], 'version' => $block['version']], $restored),
            'nodes' => $inserted,
        ])->assertOk();
        $this->assertSame(['Paragraph', 'BulletList', 'CodeBlock'], array_column($this->tree()[0]['children'], 'type'));
    }

    public function test_accepting_an_expansion_inserts_blocks_and_leaves_the_rest_alone(): void
    {
        $other = $this->textSuggestion('In order to install it', 'To install it');
        $suggestion = $this->expandSuggestion($this->ids['paragraph'], 'after', $this->ids['topic']);

        $revision = $this->service()->accept($suggestion, $this->admin);

        $this->assertSame('block.insert', $revision->op);
        $children = $this->tree()[0]['children'];
        $this->assertSame(['Paragraph', 'Paragraph', 'CodeBlock'], array_column($children, 'type'));
        // the existing blocks are the same blocks, not copies
        $this->assertSame([$this->ids['paragraph'], $this->ids['code']], [$children[0]['id'], $children[2]['id']]);
        $this->assertSame('Composer installs the PHP packages.', $children[1]['children'][0]['props']['html']);
        $this->assertSame(ProseSuggestion::ACCEPTED, $suggestion->fresh()->status);

        // so suggestions on them still apply
        $this->assertSame(ProseSuggestion::PENDING, $other->fresh()->status);
        $this->service()->accept($other, $this->admin);
        $this->assertSame('To install it, you need PHP, a database and Chrome.', $this->props('text')['html']);
    }

    public function test_an_expansion_can_open_a_section(): void
    {
        $this->service()->accept($this->expandSuggestion($this->ids['topic'], 'start', $this->ids['topic']), $this->admin);

        $this->assertSame(['Paragraph', 'Paragraph', 'CodeBlock'], array_column($this->tree()[0]['children'], 'type'));
        $this->assertSame('Composer installs the PHP packages.', $this->tree()[0]['children'][0]['children'][0]['props']['html']);
    }

    public function test_an_expansion_still_applies_after_edits_but_not_once_its_block_is_gone(): void
    {
        $this->patchJson("/api/blocks/{$this->ids['code']}", ['version' => 1, 'props' => ['lang' => 'bash', 'code' => 'composer install --no-dev']])->assertOk();
        $this->service()->accept($this->expandSuggestion($this->ids['code'], 'before', $this->ids['topic']), $this->admin);
        $this->assertSame(['Paragraph', 'Paragraph', 'CodeBlock'], array_column($this->tree()[0]['children'], 'type'));

        $gone = $this->expandSuggestion($this->ids['paragraph'], 'after', $this->ids['topic']);
        $this->deleteJson("/api/blocks/{$this->ids['paragraph']}", ['version' => 1])->assertSuccessful();

        $this->expectException(SuggestionStaleException::class);
        try {
            $this->service()->accept($gone, $this->admin);
        } finally {
            $this->assertSame(ProseSuggestion::STALE, $gone->fresh()->status);
        }
    }

    public function test_the_replace_endpoint_refuses_blocks_that_are_not_neighbours(): void
    {
        $this->postJson("/api/documents/{$this->document->slug}/blocks/replace", [
            'blocks' => [['id' => $this->ids['paragraph'], 'version' => 1], ['id' => $this->ids['text'], 'version' => 1]],
            'nodes' => [['type' => 'Paragraph']],
        ])->assertUnprocessable()->assertJsonValidationErrors('blockIds');
    }

    private function service(): ProseReviews
    {
        return app(ProseReviews::class);
    }

    private function textSuggestion(string $original, string $suggested, string $rule = 'hedging'): ProseSuggestion
    {
        return $this->review->suggestions()->create([
            'kind' => ProseSuggestion::TEXT, 'severity' => 'minor', 'rule' => $rule, 'rationale' => 'Style guide.',
            'block_ids' => [$this->ids['text']], 'block_versions' => [$this->ids['text'] => 1],
            'field' => 'html', 'path' => 'html', 'original' => $original, 'suggested' => $suggested,
            'status' => ProseSuggestion::PENDING,
        ]);
    }

    private function restructureSuggestion(): ProseSuggestion
    {
        return $this->review->suggestions()->create([
            'kind' => ProseSuggestion::RESTRUCTURE, 'severity' => 'major', 'rule' => 'list', 'rationale' => 'Parallel items.',
            'block_ids' => [$this->ids['paragraph']],
            'block_versions' => [$this->ids['paragraph'] => 1, $this->ids['text'] => 1],
            'parent_id' => $this->ids['topic'],
            'replacement' => [
                ['type' => 'Paragraph', 'props' => [], 'options' => [], 'children' => [['type' => 'RichText', 'props' => ['html' => 'To install it, you need:'], 'options' => [], 'children' => []]]],
                ['type' => 'BulletList', 'props' => ['list' => ['PHP', 'A database', 'Chrome']], 'options' => [], 'children' => []],
            ],
            'status' => ProseSuggestion::PENDING,
        ]);
    }

    private function expandSuggestion(string $anchor, string $position, ?string $parent): ProseSuggestion
    {
        return $this->review->suggestions()->create([
            'kind' => ProseSuggestion::EXPAND, 'severity' => 'minor', 'rule' => 'depth', 'rationale' => 'Composer is never explained.',
            'block_ids' => [$anchor], 'block_versions' => [$anchor => 1],
            'parent_id' => $parent, 'position' => $position, 'sources' => ['general'],
            'replacement' => [
                ['type' => 'Paragraph', 'props' => [], 'options' => [], 'children' => [['type' => 'RichText', 'props' => ['html' => 'Composer installs the PHP packages.'], 'options' => [], 'children' => []]]],
            ],
            'status' => ProseSuggestion::PENDING,
        ]);
    }

    private function props(string $block): array
    {
        return Block::query()->findOrFail($this->ids[$block])->props;
    }

    private function tree(): array
    {
        return $this->getJson("/api/documents/{$this->document->slug}")->json('blocks');
    }
}
