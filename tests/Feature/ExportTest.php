<?php

namespace Tests\Feature;

use App\Jobs\GeneratePdf;
use App\Models\Document;
use App\Models\Export;
use App\Models\User;
use App\Pdf\PdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    /** The URLs the fake headless browser was asked to print. */
    private array $printed = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app->instance(PdfRenderer::class, new class($this->printed) implements PdfRenderer
        {
            public function __construct(private array &$printed) {}

            public function render(string $url, string $path): void
            {
                $this->printed[] = $url;
                file_put_contents($path, '%PDF-1.7 fake');
            }
        });

        $this->actingAs(User::factory()->create());
        $this->document = Document::where('slug', $this->postJson('/api/documents', ['title' => 'Migration Plan'])->json('document.slug'))->firstOrFail();
        $this->document->update(['meta' => [...$this->document->meta, 'fileName' => 'oh-server-migration_{yyyyMMdd}']]);
        $this->postJson("/api/documents/{$this->document->slug}/blocks", ['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW']]);
    }

    public function test_one_click_export_renders_the_print_view_and_downloads_with_the_document_file_name(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21));

        $export = $this->postJson("/api/documents/{$this->document->slug}/exports", ['mode' => 'final'])
            ->assertAccepted()
            ->json('export');

        // the queue is sync in tests, so it has already run
        $this->getJson("/api/exports/{$export['id']}")
            ->assertJsonPath('export.status', 'done')
            ->assertJsonPath('export.fileName', 'oh-server-migration_20261021.pdf')
            ->assertJsonPath('export.downloadUrl', "/api/exports/{$export['id']}/download");

        $download = $this->get("/api/exports/{$export['id']}/download")->assertOk();
        $this->assertStringContainsString('oh-server-migration_20261021.pdf', $download->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.7 fake', $download->streamedContent());

        $this->assertCount(1, $this->printed);
        $this->assertStringStartsWith(config('services.pdf.base_url').'/render/migration-plan?mode=final&source=', $this->printed[0]);
    }

    public function test_the_print_data_url_works_without_a_login_but_only_while_signed(): void
    {
        $this->postJson("/api/documents/{$this->document->slug}/exports")->assertAccepted();
        parse_str(parse_url($this->printed[0], PHP_URL_QUERY), $query);
        $source = $query['source'];
        $this->assertStringStartsWith('/api/print/documents/migration-plan?', $source);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->getJson($source)->assertOk()->assertJsonPath('blocks.0.props.title', 'OVERVIEW');
        $this->getJson(str_replace('signature=', 'signature=0', $source))->assertForbidden();
        $this->getJson('/api/print/documents/migration-plan')->assertForbidden();

        $this->travel(10)->minutes();
        $this->getJson($source)->assertForbidden();
    }

    public function test_a_failed_render_is_reported_on_the_export(): void
    {
        Queue::fake();
        $export = Export::find($this->postJson("/api/documents/{$this->document->slug}/exports")->json('export.id'));
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $url, string $path): void
            {
                throw new RuntimeException('Chrome could not reach the print view');
            }
        });

        $job = new GeneratePdf($export);
        try {
            $this->app->call([$job, 'handle']);
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $this->getJson("/api/exports/{$export->id}")
            ->assertJsonPath('export.status', 'failed')
            ->assertJsonPath('export.error', 'Chrome could not reach the print view')
            ->assertJsonPath('export.downloadUrl', null);
        $this->get("/api/exports/{$export->id}/download")->assertNotFound();
    }

    public function test_publishing_a_version_freezes_the_document_and_renders_its_pdf(): void
    {
        $snapshot = $this->postJson("/api/documents/{$this->document->slug}/snapshots", ['label' => 'v1.0 submitted'])
            ->assertCreated()
            ->assertJsonPath('snapshot.label', 'v1.0 submitted')
            ->assertJsonPath('snapshot.pdf.status', 'done')
            ->json('snapshot');

        // later edits don't change the published version
        $topic = $this->getJson("/api/documents/{$this->document->slug}")->json('blocks.0');
        $this->patchJson("/api/blocks/{$topic['id']}", ['version' => 1, 'props' => ['title' => 'CHANGED LATER']])->assertOk();

        $this->getJson("/api/snapshots/{$snapshot['id']}")
            ->assertJsonPath('blocks.0.props.title', 'OVERVIEW')
            ->assertJsonPath('document.title', 'Migration Plan');
        $this->getJson("/api/documents/{$this->document->slug}/snapshots")->assertJsonPath('data.0.label', 'v1.0 submitted');

        $pdf = $this->get("/api/snapshots/{$snapshot['id']}/pdf")->assertOk();
        $this->assertStringContainsString('_v1-0-submitted.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('source=%2Fapi%2Fprint%2Fsnapshots%2F'.$snapshot['id'], $this->printed[0]);
        $this->assertStringContainsString('mode=final', $this->printed[0]);
    }
}
