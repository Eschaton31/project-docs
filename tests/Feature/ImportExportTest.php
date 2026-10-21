<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_import_then_export_round_trips_the_tree(): void
    {
        $payload = [
            'schemaVersion' => 1,
            'title' => 'OHASIS Servers Migration Plan',
            'meta' => ['fileName' => 'oh-server-migration-2026_{yyyyMMdd}', 'runningTitle' => ['bold' => 'OHASIS SERVERS', 'light' => 'MIGRATION PLAN'], 'toc' => true],
            'titlePage' => ['title' => 'OHASIS Servers', 'subtitle' => 'Migration Plan', 'version' => '1.0'],
            'blocks' => [
                ['type' => 'PrimaryTopic', 'props' => ['title' => 'OVERVIEW'], 'options' => [], 'children' => [
                    ['type' => 'Paragraph', 'props' => [], 'options' => [], 'children' => [
                        ['type' => 'RichText', 'props' => ['html' => 'Domain <codetext>ohasis.doh.gov.ph</codetext>'], 'options' => [], 'children' => []],
                        ['type' => 'NumberedList', 'props' => ['list' => ['Offline', 'Migrate']], 'options' => [], 'children' => []],
                    ]],
                ]],
                ['type' => 'PrimaryTopic', 'props' => ['title' => 'TIMELINE & PLANS'], 'options' => ['pageBreakBefore' => true], 'children' => []],
            ],
        ];

        $slug = $this->postJson('/api/documents/import', $payload)
            ->assertCreated()
            ->assertJsonPath('document.slug', 'ohasis-servers-migration-plan')
            ->json('document.slug');

        $export = $this->getJson("/api/documents/{$slug}/export")
            ->assertOk()
            ->assertHeader('Content-Disposition', "attachment; filename=\"{$slug}.json\"")
            ->json();

        $this->assertSame($payload['blocks'], $export['blocks']);
        $this->assertSame($payload['titlePage'], $export['titlePage']);

        // importing the export again creates a second, independent document
        $this->postJson('/api/documents/import', $export)->assertCreated()->assertJsonPath('document.slug', "{$slug}-2");
    }

    public function test_import_reports_every_invalid_block_with_its_path(): void
    {
        $this->postJson('/api/documents/import', [
            'schemaVersion' => 1,
            'title' => 'Broken',
            'blocks' => [
                ['type' => 'Paragraph'],
                ['type' => 'PrimaryTopic', 'children' => [['type' => 'Mystery'], ['props' => []]]],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'blocks.0.type' => 'A Paragraph cannot be placed in the top level of a document.',
            'blocks.1.children.0.type' => 'Unknown block type [Mystery].',
            'blocks.1.children.1.type' => 'Each block needs a type.',
        ]);
    }

    public function test_import_requires_a_supported_schema_version(): void
    {
        $this->postJson('/api/documents/import', ['schemaVersion' => 2, 'title' => 'Future', 'blocks' => []])
            ->assertJsonValidationErrors('schemaVersion');
    }
}
