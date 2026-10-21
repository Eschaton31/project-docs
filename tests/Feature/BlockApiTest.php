<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockApiTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private User $ben;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = User::factory()->create(['name' => 'Ana']);
        $this->ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($this->ana);

        $slug = $this->postJson('/api/documents', ['title' => 'Data Management'])->assertCreated()->json('document.slug');
        $this->document = Document::where('slug', $slug)->firstOrFail();
    }

    public function test_blocks_are_ordered_by_after_and_before_anchors(): void
    {
        $overview = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW']]);
        $setup = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'SET-UP']]);
        $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'PIPELINE DESIGN'], 'afterId' => $overview['id']]);
        $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'PREFACE'], 'beforeId' => $overview['id']]);
        $this->createBlock(['type' => 'SecondaryTopic', 'props' => ['title' => 'Infrastructure'], 'parentId' => $setup['id']]);

        $tree = $this->getJson("/api/documents/{$this->document->slug}")->assertOk()->json('blocks');

        $this->assertSame(['PREFACE', 'OVERVIEW', 'PIPELINE DESIGN', 'SET-UP'], array_column(array_column($tree, 'props'), 'title'));
        $this->assertSame('Infrastructure', $tree[3]['children'][0]['props']['title']);
    }

    public function test_nesting_rules_come_from_the_shared_block_types_file(): void
    {
        $this->postJson("/api/documents/{$this->document->slug}/blocks", ['type' => 'Paragraph'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'A Paragraph cannot be placed in the top level of a document.']);

        $topic = $this->createBlock(['type' => 'PrimaryTopic']);
        $paragraph = $this->createBlock(['type' => 'Paragraph', 'parentId' => $topic['id']]);

        $this->postJson("/api/documents/{$this->document->slug}/blocks", ['type' => 'SecondaryTopic', 'parentId' => $paragraph['id']])
            ->assertJsonValidationErrors(['type' => 'A SecondaryTopic cannot be placed in a Paragraph.']);
        $this->postJson("/api/documents/{$this->document->slug}/blocks", ['type' => 'Nonsense', 'parentId' => $topic['id']])
            ->assertJsonValidationErrors('type');
    }

    public function test_stale_version_is_rejected_with_the_current_block(): void
    {
        $topic = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'Draft']]);

        // Ana saves first...
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'OVERVIEW']])
            ->assertOk()
            ->assertJsonPath('block.version', 2);

        // ...then Ben saves on top of the version he loaded
        $this->actingAs($this->ben);
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'Overview (Ben)']])
            ->assertConflict()
            ->assertJsonPath('current.version', 2)
            ->assertJsonPath('current.props.title', 'OVERVIEW');
    }

    public function test_unknown_option_keys_are_rejected(): void
    {
        $topic = $this->createBlock(['type' => 'PrimaryTopic']);

        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'options' => ['pageBreakBefore' => true, 'colour' => 'red']])
            ->assertJsonValidationErrors('options');
    }

    public function test_move_reorders_and_reparents(): void
    {
        $a = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'A']]);
        $b = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'B']]);
        $child = $this->createBlock(['type' => 'SecondaryTopic', 'props' => ['title' => 'child'], 'parentId' => $a['id']]);

        $this->patchJson("/api/blocks/{$b['id']}/move", ['version' => 1, 'parentId' => null, 'beforeId' => $a['id']])->assertOk();
        $this->patchJson("/api/blocks/{$child['id']}/move", ['version' => 1, 'parentId' => $b['id']])->assertOk();

        $tree = $this->getJson("/api/documents/{$this->document->slug}")->json('blocks');
        $this->assertSame(['B', 'A'], array_column(array_column($tree, 'props'), 'title'));
        $this->assertSame('child', $tree[0]['children'][0]['props']['title']);
        $this->assertSame([], $tree[1]['children']);
    }

    public function test_cannot_move_a_block_inside_itself(): void
    {
        $topic = $this->createBlock(['type' => 'SecondaryTopic', 'parentId' => $this->createBlock(['type' => 'PrimaryTopic'])['id']]);
        $inner = $this->createBlock(['type' => 'TertiaryTopic', 'parentId' => $topic['id']]);

        $this->patchJson("/api/blocks/{$topic['id']}/move", ['version' => 1, 'parentId' => $inner['id']])
            ->assertJsonValidationErrors(['parentId' => 'A block cannot be moved inside itself.']);
    }

    public function test_delete_removes_the_subtree_and_keeps_it_in_the_revision(): void
    {
        $topic = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'DATA SHARING']]);
        $this->createBlock(['type' => 'SecondaryTopic', 'props' => ['title' => 'ICAP'], 'parentId' => $topic['id']]);

        $this->deleteJson("/api/blocks/{$topic['id']}", ['version' => 1])->assertOk();

        $this->assertSame([], $this->getJson("/api/documents/{$this->document->slug}")->json('blocks'));
        $this->assertSame(0, $this->document->blocks()->count());

        $revision = $this->document->revisions()->where('op', 'block.delete')->sole();
        $this->assertSame('ICAP', $revision->before['children'][0]['props']['title']);
    }

    public function test_events_since_a_seq_replay_other_peoples_changes(): void
    {
        $topic = $this->createBlock(['type' => 'PrimaryTopic']);
        $seq = $this->getJson("/api/documents/{$this->document->slug}")->json('document.seq');

        $this->actingAs($this->ben);
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'By Ben']])->assertOk();
        $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'Also Ben']]);

        $response = $this->getJson("/api/documents/{$this->document->slug}/events?since={$seq}")->assertOk();

        $this->assertSame(['block.update', 'block.create'], array_column($response->json('events'), 'op'));
        $this->assertSame([$seq + 1, $seq + 2], array_column($response->json('events'), 'seq'));
        $this->assertSame($this->ben->id, $response->json('events.0.userId'));
        $this->assertSame('By Ben', $response->json('events.0.after.props.title'));
        $this->assertSame($seq + 2, $response->json('seq'));
    }

    public function test_document_settings_use_optimistic_versions_too(): void
    {
        $url = "/api/documents/{$this->document->slug}";

        $this->patchJson($url, ['version' => 1, 'meta' => ['toc' => false, 'runningTitle' => ['bold' => 'OHASIS', 'light' => 'MANUAL']]])
            ->assertOk()
            ->assertJsonPath('document.version', 2)
            ->assertJsonPath('document.meta.runningTitle.bold', 'OHASIS');

        $this->patchJson($url, ['version' => 1, 'titlePage' => ['version' => '2.0']])->assertConflict();
    }

    public function test_only_admins_can_delete_documents(): void
    {
        $this->deleteJson("/api/documents/{$this->document->slug}")->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/documents/{$this->document->slug}")->assertNoContent();
        $this->assertSoftDeleted($this->document);
    }

    public function test_insert_tree_places_a_subtree_between_siblings(): void
    {
        $first = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'FIRST']]);
        $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'LAST']]);

        $response = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", [
            'afterId' => $first['id'],
            'blocks' => [
                ['type' => 'PrimaryTopic', 'props' => ['title' => 'HIV TREATMENT REGISTRY'], 'children' => [
                    ['type' => 'SecondaryTopic', 'props' => ['title' => 'Data Catalog'], 'children' => [
                        ['type' => 'TertiaryTopic', 'props' => ['title' => 'Correction Tables']],
                    ]],
                    ['type' => 'Paragraph', 'options' => ['draft' => true, 'bogus' => 1], 'children' => [
                        ['type' => 'RichText', 'props' => ['html' => '[description and case definition]']],
                    ]],
                ]],
                ['type' => 'PrimaryTopic', 'props' => ['title' => 'MORTALITY REGISTRY']],
            ],
        ])->assertCreated();

        $this->assertSame(['HIV TREATMENT REGISTRY', 'MORTALITY REGISTRY'], array_column(array_column($response->json('blocks'), 'props'), 'title'));
        $this->assertSame('Correction Tables', $response->json('blocks.0.children.0.children.0.props.title'));

        $tree = $this->getJson("/api/documents/{$this->document->slug}")->json('blocks');
        $this->assertSame(['FIRST', 'HIV TREATMENT REGISTRY', 'MORTALITY REGISTRY', 'LAST'], array_column(array_column($tree, 'props'), 'title'));
        // unknown option keys are dropped, like on single-block saves
        $this->assertSame(['draft' => true], $tree[1]['children'][1]['options']);
        $this->assertSame(1, $this->document->revisions()->where('op', 'block.insert')->count());
    }

    public function test_insert_tree_enforces_nesting(): void
    {
        $topic = $this->createBlock(['type' => 'PrimaryTopic']);

        $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", [
            'parentId' => $topic['id'],
            'blocks' => [['type' => 'Paragraph', 'children' => [['type' => 'SecondaryTopic']]]],
        ])->assertJsonValidationErrors(['blocks.0.children.0.type' => 'A SecondaryTopic cannot be placed in a Paragraph.']);

        $this->assertSame(1, $this->document->blocks()->count());
    }

    public function test_restore_brings_back_a_deleted_subtree(): void
    {
        $topic = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'DATA SHARING']]);
        $this->createBlock(['type' => 'SecondaryTopic', 'props' => ['title' => 'ICAP'], 'parentId' => $topic['id']]);
        $this->deleteJson("/api/blocks/{$topic['id']}", ['version' => 1])->assertOk();

        $this->postJson("/api/blocks/{$topic['id']}/restore")
            ->assertOk()
            ->assertJsonPath('block.version', 2)
            ->assertJsonPath('block.children.0.props.title', 'ICAP');

        $this->assertSame(2, $this->document->blocks()->count());
        $this->postJson("/api/blocks/{$topic['id']}/restore")->assertJsonValidationErrors('block');
    }

    public function test_restore_steps_past_a_block_that_took_its_position(): void
    {
        $a = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'A']]);
        $b = $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'B']]);
        $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'C']]);

        $this->deleteJson("/api/blocks/{$b['id']}", ['version' => 1])->assertOk();
        // inserted between the same neighbours, so it gets B's old position
        $this->createBlock(['type' => 'PrimaryTopic', 'props' => ['title' => 'B2'], 'afterId' => $a['id']]);
        $this->postJson("/api/blocks/{$b['id']}/restore")->assertOk();

        $tree = $this->getJson("/api/documents/{$this->document->slug}")->json('blocks');
        $this->assertSame(['A', 'B2', 'B', 'C'], array_column(array_column($tree, 'props'), 'title'));
        $this->assertSame(4, count(array_unique(array_column($tree, 'position'))));
    }

    private function createBlock(array $data): array
    {
        return $this->postJson("/api/documents/{$this->document->slug}/blocks", $data)->assertCreated()->json('block');
    }
}
