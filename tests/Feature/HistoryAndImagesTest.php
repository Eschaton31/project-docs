<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistoryAndImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = User::factory()->create(['name' => 'Ana', 'color' => '#c2410c']);
        $this->actingAs($this->ana);
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Handover'])->json('document.slug'))->firstOrFail();
    }

    public function test_a_block_history_lists_every_change_with_its_content(): void
    {
        $topic = $this->block(['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW']]);
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'OVERVIEW v2']])->assertOk();
        $this->patchJson("/api/blocks/{$topic['id']}/move", ['version' => 2, 'parentId' => null])->assertOk();

        $history = $this->getJson("/api/blocks/{$topic['id']}/revisions")->assertOk()->json('data');

        $this->assertSame(['block.move', 'block.update', 'block.create'], array_column($history, 'op'));
        $this->assertSame('Ana', $history[1]['user']['name']);
        $this->assertSame('OVERVIEW', $history[1]['before']['props']['title']);
        $this->assertSame('OVERVIEW v2', $history[1]['after']['props']['title']);
    }

    public function test_activity_summarises_changes_and_offers_to_restore_deletions(): void
    {
        $topic = $this->block(['type' => 'PrimaryTopic', 'props' => ['title' => 'DATA SHARING']]);
        $paragraph = $this->postJson("/api/documents/{$this->document->slug}/blocks/tree", [
            'parentId' => $topic['id'],
            'blocks' => [['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'Shared with <b>ICAP</b> monthly.']]]]],
        ])->json('blocks.0');
        $this->deleteJson("/api/blocks/{$paragraph['id']}", ['version' => 1])->assertOk();

        $activity = $this->getJson("/api/documents/{$this->document->slug}/activity")->assertOk()->json();

        $this->assertSame(['block.delete', 'block.insert', 'block.create', 'document.create'], array_column($activity['data'], 'op'));
        $this->assertSame(['type' => 'Paragraph', 'label' => '', 'count' => null], $activity['data'][0]['block']);
        $this->assertTrue($activity['data'][0]['restorable']);
        $this->assertSame(['type' => 'Paragraph', 'label' => '', 'count' => 1], $activity['data'][1]['block']);
        $this->assertSame('DATA SHARING', $activity['data'][2]['block']['label']);
        $this->assertFalse($activity['hasMore']);

        $this->postJson("/api/blocks/{$paragraph['id']}/restore")->assertOk();
        $this->assertFalse($this->getJson("/api/documents/{$this->document->slug}/activity")->json('data.1.restorable'));
        // paging back
        $this->assertSame(['document.create'], array_column($this->getJson("/api/documents/{$this->document->slug}/activity?before=2")->json('data'), 'op'));
    }

    public function test_images_can_be_uploaded_and_listed(): void
    {
        Storage::fake('images', ['url' => '/img']);
        Storage::disk('images')->put('diagrams/epic-server-migration.png', 'png');

        $uploaded = $this->post('/api/images', ['file' => UploadedFile::fake()->image('Server Rack Photo.JPG', 40, 30)])
            ->assertCreated()
            ->json('image');

        $this->assertMatchesRegularExpression('#^/img/uploads/\d{4}-\d{2}/server-rack-photo-[a-z0-9]{6}\.jpg$#', $uploaded['path']);
        Storage::disk('images')->assertExists(substr($uploaded['path'], strlen('/img/')));
        $this->assertDatabaseHas('assets', ['original_name' => 'Server Rack Photo.JPG', 'uploaded_by' => $this->ana->id]);

        $images = $this->getJson('/api/images')->assertOk()->json('data');
        $this->assertSame(['/img/diagrams/epic-server-migration.png', $uploaded['path']], array_column($images, 'path'));
        $this->assertSame('diagrams', $images[0]['folder']);
    }

    public function test_images_can_be_uploaded_into_a_chosen_folder(): void
    {
        Storage::fake('images', ['url' => '/img']);
        Storage::disk('images')->put('screenshots/ohasis-linelists/existing.png', 'png');

        $this->post('/api/images', ['file' => UploadedFile::fake()->image('confirmatory.png'), 'folder' => 'screenshots/ohasis-linelists'])
            ->assertCreated()
            ->assertJsonPath('image.folder', 'screenshots/ohasis-linelists');

        // a folder that doesn't exist yet is created
        $new = $this->post('/api/images', ['file' => UploadedFile::fake()->image('rack.jpg'), 'folder' => 'diagrams/server migration 2026'])
            ->assertCreated()
            ->json('image.path');
        $this->assertStringStartsWith('/img/diagrams/server migration 2026/rack-', $new);

        $listing = $this->getJson('/api/images')->json();
        $this->assertSame(['diagrams', 'diagrams/server migration 2026', 'screenshots', 'screenshots/ohasis-linelists'], $listing['folders']);
        $this->assertCount(2, Storage::disk('images')->files('screenshots/ohasis-linelists'));
    }

    public function test_upload_folders_cannot_leave_the_image_directory(): void
    {
        Storage::fake('images');

        foreach (['../app', 'uploads/../../.env', '/etc', '.hidden', 'a\\b', 'C:/Windows', 'a/b/c/d/e/f'] as $folder) {
            $this->post('/api/images', ['file' => UploadedFile::fake()->image('x.png'), 'folder' => $folder], ['Accept' => 'application/json'])
                ->assertJsonValidationErrors('folder');
        }
        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_svg_and_non_images_are_refused(): void
    {
        Storage::fake('images');

        $this->post('/api/images', ['file' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');
        $this->post('/api/images', ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');
    }

    private function block(array $data): array
    {
        return $this->postJson("/api/documents/{$this->document->slug}/blocks", $data)->assertCreated()->json('block');
    }
}
