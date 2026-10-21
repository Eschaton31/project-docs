<?php

namespace Tests\Feature;

use App\Events\DocumentChanged;
use App\Events\LocksChanged;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private User $ben;

    private string $anaTab;

    private string $benTab;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = User::factory()->create(['name' => 'Ana']);
        $this->ben = User::factory()->create(['name' => 'Ben']);
        $this->anaTab = (string) Str::uuid();
        $this->benTab = (string) Str::uuid();

        $this->as($this->ana);
        $slug = $this->postJson('/api/documents', ['title' => 'Handover'])->json('document.slug');
        $this->document = Document::where('slug', $slug)->firstOrFail();
    }

    public function test_a_lock_belongs_to_one_tab_until_it_expires(): void
    {
        $topic = $this->block(['type' => 'PrimaryTopic']);

        $this->lock("block:{$topic['id']}")->assertOk()->assertJsonPath('lock.name', 'Ana')->assertJsonPath('lock.ttl', 60);
        // renewing from the same tab is fine
        $this->lock("block:{$topic['id']}")->assertOk();

        $this->as($this->ben, $this->benTab);
        $this->lock("block:{$topic['id']}")
            ->assertStatus(423)
            ->assertJsonPath('message', 'Ana is editing this block.')
            ->assertJsonPath('holder.clientId', $this->anaTab);

        $this->travel(61)->seconds();
        $this->lock("block:{$topic['id']}")->assertOk()->assertJsonPath('lock.name', 'Ben');
    }

    public function test_the_same_person_in_another_tab_is_also_locked_out(): void
    {
        $topic = $this->block(['type' => 'PrimaryTopic']);
        $this->lock("block:{$topic['id']}")->assertOk();

        $this->as($this->ana, (string) Str::uuid());
        $this->lock("block:{$topic['id']}")->assertStatus(423);
    }

    public function test_edits_to_a_block_someone_else_holds_are_refused_with_what_is_saved(): void
    {
        $topic = $this->block(['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW']]);
        $this->lock("block:{$topic['id']}")->assertOk();

        $this->as($this->ben, $this->benTab);
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'Mine']])
            ->assertStatus(423)
            ->assertJsonPath('current.props.title', 'OVERVIEW');

        $this->as($this->ana, $this->anaTab);
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'Ana edits']])->assertOk();
    }

    public function test_moving_or_deleting_is_refused_while_someone_holds_a_block_inside(): void
    {
        $topic = $this->block(['type' => 'PrimaryTopic']);
        $other = $this->block(['type' => 'PrimaryTopic']);
        $section = $this->block(['type' => 'SecondaryTopic', 'parentId' => $topic['id']]);
        $paragraph = $this->block(['type' => 'Paragraph', 'parentId' => $section['id']]);
        $this->lock("block:{$paragraph['id']}")->assertOk();

        $this->as($this->ben, $this->benTab);
        $this->deleteJson("/api/blocks/{$topic['id']}", ['version' => 1])->assertStatus(423);
        $this->patchJson("/api/blocks/{$section['id']}/move", ['version' => 1, 'parentId' => $other['id']])->assertStatus(423);
        // blocks outside the locked one are free
        $this->patchJson("/api/blocks/{$other['id']}", ['version' => 1, 'props' => ['title' => 'Free']])->assertOk();

        $this->as($this->ana, $this->anaTab);
        $this->deleteJson("/api/documents/{$this->document->slug}/locks", ['target' => "block:{$paragraph['id']}"])->assertNoContent();

        $this->as($this->ben, $this->benTab);
        $this->deleteJson("/api/blocks/{$topic['id']}", ['version' => 1])->assertOk();
    }

    public function test_document_settings_have_their_own_lock(): void
    {
        $this->lock('settings')->assertOk();

        $this->as($this->ben, $this->benTab);
        $this->patchJson("/api/documents/{$this->document->slug}", ['version' => 1, 'title' => 'Ben was here'])
            ->assertStatus(423)
            ->assertJsonPath('message', 'Ana is editing the document settings.')
            ->assertJsonPath('current.title', 'Handover');
    }

    public function test_closing_a_tab_drops_all_its_locks(): void
    {
        $a = $this->block(['type' => 'PrimaryTopic']);
        $b = $this->block(['type' => 'PrimaryTopic']);
        $this->lock("block:{$a['id']}")->assertOk();
        $this->lock('settings')->assertOk();
        $this->as($this->ben, $this->benTab);
        $this->lock("block:{$b['id']}")->assertOk();

        $this->as($this->ana, $this->anaTab);
        $this->deleteJson("/api/documents/{$this->document->slug}/locks/mine")->assertNoContent();

        $locks = $this->getJson("/api/documents/{$this->document->slug}")->json('locks');
        $this->assertSame(["block:{$b['id']}"], array_column($locks, 'target'));
    }

    public function test_locks_need_a_client_id_and_a_block_of_this_document(): void
    {
        $this->as($this->ana, null);
        $this->lock('settings')->assertJsonValidationErrors('client');

        $this->as($this->ana, $this->anaTab);
        $this->lock('block:'.Str::uuid())->assertJsonValidationErrors('target');
        $this->lock('everything')->assertJsonValidationErrors('target');
    }

    public function test_events_say_which_tab_made_each_change(): void
    {
        $seq = $this->getJson("/api/documents/{$this->document->slug}")->json('document.seq');
        $topic = $this->block(['type' => 'PrimaryTopic']);

        $this->as($this->ben, $this->benTab);
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'Ben']])->assertOk();

        $events = $this->getJson("/api/documents/{$this->document->slug}/events?since={$seq}")->json('events');
        $this->assertSame([$this->anaTab, $this->benTab], array_column($events, 'clientId'));
    }

    public function test_other_editors_are_notified_after_each_committed_change(): void
    {
        Event::fake([DocumentChanged::class, LocksChanged::class]);
        $topic = $this->block(['type' => 'PrimaryTopic']);

        Event::assertDispatched(DocumentChanged::class, function (DocumentChanged $event) use ($topic) {
            $this->assertSame("presence-document.{$this->document->id}", $event->broadcastOn()->name);
            $this->assertSame(['seq' => 2, 'op' => 'block.create', 'blockId' => $topic['id'], 'userId' => $this->ana->id, 'clientId' => $this->anaTab], $event->broadcastWith());

            return true;
        });

        // a refused change is not announced
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 9, 'props' => []])->assertConflict();
        Event::assertDispatchedTimes(DocumentChanged::class, 1);

        $this->lock("block:{$topic['id']}")->assertOk();
        Event::assertDispatched(LocksChanged::class, fn (LocksChanged $event) => $event->broadcastWith()['action'] === 'locked');
    }

    public function test_a_broadcasting_failure_does_not_fail_the_save(): void
    {
        Event::listen(DocumentChanged::class, fn () => throw new RuntimeException('Reverb is down'));
        Event::listen(LocksChanged::class, fn () => throw new RuntimeException('Reverb is down'));

        $topic = $this->block(['type' => 'PrimaryTopic']);
        $this->lock("block:{$topic['id']}")->assertOk();
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'Saved anyway']])->assertOk();

        $this->assertSame('Saved anyway', $this->document->blocks()->first()->props['title']);
    }

    private function as(User $user, ?string $tab = 'default'): void
    {
        $this->actingAs($user);
        $tab = $tab === 'default' ? ($user->is($this->ana) ? $this->anaTab : $this->benTab) : $tab;
        $this->defaultHeaders = $tab ? ['X-Client-Id' => $tab] : [];
    }

    private function lock(string $target)
    {
        return $this->postJson("/api/documents/{$this->document->slug}/locks", ['target' => $target]);
    }

    private function block(array $data): array
    {
        return $this->postJson("/api/documents/{$this->document->slug}/blocks", $data)->assertCreated()->json('block');
    }
}
