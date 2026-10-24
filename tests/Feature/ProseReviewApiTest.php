<?php

namespace Tests\Feature;

use App\Ai\Agents\ProseReviewer;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ProseReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Document $document;

    private array $topic;

    protected function setUp(): void
    {
        parent::setUp();

        // these tests start reviews from the editor, which only the API runner does
        config(['ai-review.runner' => 'api']);
        $this->actingAs($this->admin = User::factory()->admin()->create());
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Setup Guide'])->json('document.slug'))->firstOrFail();
        [$this->topic] = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", ['blocks' => [
            ['type' => 'PrimaryTopic', 'props' => ['title' => 'SETUP'], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'In order to install it, you need PHP.']]]],
            ]],
        ]])->json('blocks');
    }

    public function test_an_admin_starts_a_review_and_reads_its_suggestions(): void
    {
        ProseReviewer::fake([['text' => [[
            'segment' => 'S2', 'original' => 'In order to install it', 'suggested' => 'To install it',
            'rule' => 'hedging', 'rationale' => 'Cut "in order to".', 'severity' => 'minor',
        ]], 'restructure' => []]]);

        $review = $this->postJson("/api/documents/{$this->document->slug}/reviews", ['model' => 'opus', 'kinds' => ['text']])
            ->assertAccepted()
            ->assertJsonPath('review.model', 'opus')
            ->assertJsonPath('review.kinds', ['text'])
            ->json('review');

        // the queue is sync in tests, so it has already run
        $this->getJson("/api/reviews/{$review['id']}")
            ->assertOk()
            ->assertJsonPath('review.status', 'done')
            ->assertJsonPath('suggestions.0.original', 'In order to install it')
            ->assertJsonPath('suggestions.0.suggested', 'To install it')
            ->assertJsonPath('suggestions.0.status', 'pending');
        $this->getJson("/api/documents/{$this->document->slug}/reviews")->assertJsonPath('reviews.0.id', $review['id']);
    }

    public function test_an_expansion_review_sends_the_brief_and_marks_where_content_came_from(): void
    {
        ProseReviewer::fake([['text' => [], 'restructure' => [], 'expand' => [[
            'anchor' => 'B1', 'position' => 'start', 'sources' => ['brief', 'general'],
            'blocks_json' => json_encode([['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'You need PHP 8.4 or later.']]]]]),
            'rule' => 'introduction', 'rationale' => 'The section has no introduction.', 'severity' => 'minor',
        ]]]]);

        $review = $this->postJson("/api/documents/{$this->document->slug}/reviews", ['kinds' => ['expand'], 'brief' => ' We need PHP 8.4. '])
            ->assertAccepted()
            ->assertJsonPath('review.kinds', ['expand'])
            ->assertJsonPath('review.brief', 'We need PHP 8.4.')
            ->json('review');

        ProseReviewer::assertPrompted(fn ($prompt) => $prompt->contains('«We need PHP 8.4.»'));
        $suggestion = $this->getJson("/api/reviews/{$review['id']}")
            ->assertJsonPath('suggestions.0.kind', 'expand')
            ->assertJsonPath('suggestions.0.position', 'start')
            ->assertJsonPath('suggestions.0.sources', ['brief', 'general'])
            ->json('suggestions.0');

        $this->postJson("/api/suggestions/{$suggestion['id']}/accept")
            ->assertOk()
            ->assertJsonPath('change.op', 'block.insert')
            ->assertJsonPath('change.after.blocks.0.children.0.props.html', 'You need PHP 8.4 or later.');
    }

    public function test_the_brief_has_a_limit(): void
    {
        $this->postJson("/api/documents/{$this->document->slug}/reviews", ['kinds' => ['expand'], 'brief' => str_repeat('a', ProseReview::MAX_BRIEF + 1)])
            ->assertJsonValidationErrors('brief');
    }

    public function test_accepting_returns_the_change_for_the_tab_to_apply(): void
    {
        $suggestion = $this->suggestion();

        $this->postJson("/api/suggestions/{$suggestion->id}/accept")
            ->assertOk()
            ->assertJsonPath('suggestion.status', 'accepted')
            ->assertJsonPath('change.op', 'block.update')
            ->assertJsonPath('change.after.props.html', 'To install it, you need PHP.');

        $this->postJson("/api/suggestions/{$suggestion->id}/dismiss")->assertUnprocessable();
    }

    public function test_a_stale_suggestion_is_a_conflict(): void
    {
        $suggestion = $this->suggestion(original: 'Not there');

        $this->postJson("/api/suggestions/{$suggestion->id}/accept")
            ->assertConflict()
            ->assertJsonPath('suggestion.status', 'stale');
    }

    public function test_accept_rule_reports_counts_and_changes(): void
    {
        $this->suggestion();

        $this->postJson("/api/reviews/{$this->review()->id}/accept-rule", ['rule' => 'hedging'])
            ->assertOk()
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('changes.0.op', 'block.update');
        $this->postJson("/api/reviews/{$this->review()->id}/accept-rule", ['rule' => 'vibes'])->assertUnprocessable();
    }

    public function test_non_admins_cannot_use_any_review_route(): void
    {
        $suggestion = $this->suggestion();
        $this->actingAs(User::factory()->create());
        $slug = $this->document->slug;

        $this->postJson("/api/documents/{$slug}/reviews")->assertForbidden();
        $this->getJson("/api/documents/{$slug}/reviews")->assertForbidden();
        $this->getJson("/api/reviews/{$suggestion->review_id}")->assertForbidden();
        $this->postJson("/api/reviews/{$suggestion->review_id}/accept-rule", ['rule' => 'hedging'])->assertForbidden();
        $this->postJson("/api/suggestions/{$suggestion->id}/accept")->assertForbidden();
        $this->postJson("/api/suggestions/{$suggestion->id}/dismiss")->assertForbidden();
        $this->assertSame(ProseSuggestion::PENDING, $suggestion->fresh()->status);
    }

    public function test_the_scope_must_be_a_block_of_this_document_with_text(): void
    {
        $other = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Other'])->json('document.slug'))->firstOrFail();
        [$foreign] = $this->postJson("/api/documents/{$other->slug}/blocks/tree", ['blocks' => [['type' => 'PrimaryTopic', 'props' => ['title' => 'X']]]])->json('blocks');
        [$code] = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", [
            'parentId' => $this->topic['id'],
            'blocks' => [['type' => 'CodeBlock', 'props' => ['code' => 'SELECT 1;']]],
        ])->json('blocks');

        $this->postJson("/api/documents/{$this->document->slug}/reviews", ['blockId' => $foreign['id']])->assertJsonValidationErrors('blockId');
        $this->postJson("/api/documents/{$this->document->slug}/reviews", ['blockId' => $code['id']])->assertJsonValidationErrors('blockId');
        $this->postJson("/api/documents/{$this->document->slug}/reviews", ['model' => 'gpt'])->assertJsonValidationErrors('model');
        $this->postJson("/api/documents/{$this->document->slug}/reviews", ['kinds' => ['tone']])->assertJsonValidationErrors('kinds.0');
        ProseReviewer::assertNeverPrompted();
    }

    public function test_too_much_text_or_a_running_review_is_refused(): void
    {
        ProseReviewer::fake();
        config(['ai-review.max_chars_per_review' => 10]);
        $this->postJson("/api/documents/{$this->document->slug}/reviews")->assertJsonValidationErrors('blockId');

        config(['ai-review.max_chars_per_review' => 200_000]);
        $this->review(ProseReview::RUNNING);
        $this->postJson("/api/documents/{$this->document->slug}/reviews")->assertJsonValidationErrors('review');
        ProseReviewer::assertNeverPrompted();
    }

    public function test_reviews_are_rate_limited_per_admin(): void
    {
        ProseReviewer::fake();
        config(['ai-review.rate_limit_per_hour' => 2]);

        $this->postJson("/api/documents/{$this->document->slug}/reviews")->assertAccepted();
        $this->postJson("/api/documents/{$this->document->slug}/reviews")->assertAccepted();
        $this->postJson("/api/documents/{$this->document->slug}/reviews")->assertTooManyRequests();
    }

    public function test_with_the_claude_code_runner_and_no_owner_nobody_starts_reviews_from_the_editor(): void
    {
        Process::fake();
        config(['ai-review.runner' => 'claude-code', 'ai-review.claude_code.user' => null]);

        $this->getJson("/api/documents/{$this->document->slug}/reviews")
            ->assertJsonPath('runner', 'claude-code')
            ->assertJsonPath('canStart', false);
        $this->postJson("/api/documents/{$this->document->slug}/reviews")
            ->assertJsonValidationErrors(['review' => "run /prose-review {$this->document->slug}"]);
        Process::assertNothingRan();
    }

    public function test_with_the_claude_code_runner_only_the_plan_owner_starts_reviews(): void
    {
        Process::fake(['*' => Process::result(json_encode([
            'type' => 'result', 'subtype' => 'success', 'is_error' => false,
            'structured_output' => ['text' => [], 'restructure' => []], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]))]);
        config(['ai-review.runner' => 'claude-code', 'ai-review.claude_code.user' => strtoupper($this->admin->email)]);

        $this->getJson("/api/documents/{$this->document->slug}/reviews")->assertJsonPath('canStart', true);
        $review = $this->postJson("/api/documents/{$this->document->slug}/reviews", ['model' => 'opus'])->assertAccepted()->json('review');
        $this->getJson("/api/reviews/{$review['id']}")->assertJsonPath('review.status', 'done');
        Process::assertRanTimes(fn ($process) => in_array('opus', $process->command, true), 1);

        // another admin sees the suggestions but can't spend the owner's plan
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson("/api/documents/{$this->document->slug}/reviews")->assertJsonPath('canStart', false);
        $this->postJson("/api/documents/{$this->document->slug}/reviews")
            ->assertJsonValidationErrors(['review' => 'only they can start one here']);
        ProseReviewer::assertNeverPrompted();
    }

    public function test_the_routes_are_gone_when_the_feature_is_off(): void
    {
        config(['ai-review.enabled' => false]);

        $this->getJson("/api/documents/{$this->document->slug}/reviews")->assertNotFound();
    }

    public function test_the_user_payload_says_whether_to_offer_reviews(): void
    {
        $this->getJson('/api/user')->assertJsonPath('abilities.aiReview', true);

        config(['ai-review.enabled' => false]);
        $this->getJson('/api/user')->assertJsonPath('abilities.aiReview', false);

        config(['ai-review.enabled' => true]);
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/user')->assertJsonPath('abilities.aiReview', false);
    }

    public function test_only_admins_may_join_a_documents_review_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'key',
            'broadcasting.connections.reverb.secret' => 'secret',
            'broadcasting.connections.reverb.app_id' => 'app',
        ]);
        // a broadcaster that really checks channel access (tests use the null one), with the app's channels
        app(BroadcastManager::class)->purge();
        require base_path('routes/channels.php');
        $auth = fn () => $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-document.{$this->document->id}.reviews"]);

        $auth()->assertOk();
        $this->actingAs(User::factory()->create());
        $auth()->assertForbidden();
    }

    private function review(string $status = ProseReview::DONE): ProseReview
    {
        return ProseReview::query()->firstOrCreate(['document_id' => $this->document->id, 'status' => $status], [
            'requested_by' => $this->admin->id, 'model' => 'sonnet', 'kinds' => ['text', 'restructure'],
        ]);
    }

    private function suggestion(string $original = 'In order to install it'): ProseSuggestion
    {
        $text = $this->topic['children'][0]['children'][0]['id'];

        return $this->review()->suggestions()->create([
            'kind' => ProseSuggestion::TEXT, 'severity' => 'minor', 'rule' => 'hedging', 'rationale' => 'Style guide.',
            'block_ids' => [$text], 'block_versions' => [$text => 1],
            'field' => 'html', 'path' => 'html', 'original' => $original, 'suggested' => 'To install it',
            'status' => ProseSuggestion::PENDING,
        ]);
    }
}
