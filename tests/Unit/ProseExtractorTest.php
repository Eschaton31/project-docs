<?php

namespace Tests\Unit;

use App\Ai\ProseExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProseExtractorTest extends TestCase
{
    private int $ids = 0;

    /**
     * @return array<string, array{0: string, 1: array, 2: list<string>}>
     */
    public static function blockTypes(): array
    {
        return [
            'topic title' => ['SecondaryTopic', ['title' => 'Installing'], ['title']],
            'tertiary title' => ['TertiaryTitle', ['text' => 'Heading'], ['text']],
            'placeholder' => ['Placeholder', ['text' => 'to be written'], ['text']],
            'rich text' => ['RichText', ['html' => 'Run <codetext>composer install</codetext> first.'], ['html']],
            'alert title only (its text is a RichText child)' => ['Alert', ['type' => 'warn', 'title' => 'Warning'], ['title']],
            'bullet list with plain and bold sub-lists' => [
                'BulletList',
                ['list' => ['One', ['One.1', 'One.2'], 'Two', ['bold' => true, 'list' => ['Two.1']]], 'bold' => true],
                ['list.0', 'list.1.0', 'list.1.1', 'list.2', 'list.3.list.0'],
            ],
            'numbered list' => ['NumberedList', ['list' => ['Step one', 'Step two']], ['list.0', 'list.1']],
            'concept list: definitions, not terms' => [
                'ConceptList',
                ['list' => [['id' => 'Draft', 'value' => 'Left out of the final version', 'children' => [['id' => 'Sub', 'value' => 'Nested']]]]],
                ['list.0.value', 'list.0.children.0.value'],
            ],
            'item description table: headings, descriptions and items' => [
                'ItemDescriptionTable',
                ['itemName' => 'Setting', 'descriptionName' => 'Meaning', 'data' => [['item' => 'Retention', 'description' => ['Kept for', '30 days']]]],
                ['itemName', 'descriptionName', 'data.0.description.0', 'data.0.description.1', 'data.0.item'],
            ],
            'item description table: monospace items are identifiers' => [
                'ItemDescriptionTable',
                ['mono' => true, 'data' => [['item' => 'APP_KEY', 'description' => 'Encryption key']]],
                ['data.0.description'],
            ],
            'key value table: values only' => ['KeyValueTable', ['table' => [['id' => 'Host', 'value' => 'The database host']]], ['table.0.value']],
            'conversion dictionary' => ['ConversionDictionary', ['data' => [['item' => 'dob', 'description' => 'Date of birth', 'formats' => 'Y-m-d']]], ['data.0.description']],
            'data dictionary' => ['DataDictionary', ['data' => [['field' => 'id', 'description' => 'Primary key', 'type' => 'INT', 'values' => '']]], ['data.0.description']],
            'test scenarios: not the grouping column' => [
                'TestScenarios',
                ['data' => [['functionality' => 'Login', 'details' => 'Wrong password shows an error', 'remarks' => 'Passed']]],
                ['data.0.details', 'data.0.remarks'],
            ],
            'image caption' => ['Image', ['src' => '/a.png', 'description' => 'The export dialog'], ['description']],
            'sql table caption' => ['SqlTable', ['data' => [['id' => 1, 'name' => 'example']], 'description' => 'Active clients'], ['description']],
            'file tree notes and caption' => [
                'FileTree',
                ['description' => 'Project layout', 'list' => [['type' => 'folder', 'name' => 'app', 'note' => 'Application code', 'children' => [['name' => 'x.php', 'note' => 'Entry point']]]]],
                ['description', 'list.0.note', 'list.0.children.0.note'],
            ],
            'code block label, not code' => ['CodeBlock', ['lang' => 'sql', 'label' => 'Find active clients', 'code' => 'SELECT 1;'], ['label']],
            'mermaid' => ['Mermaid', ['code' => 'flowchart LR'], []],
            'tech stack' => ['TechStack', ['data' => [['component' => 'Queue', 'tech' => 'Redis', 'framework' => '']]], []],
            'breadcrumbs (must match the real UI)' => ['Breadcrumbs', ['list' => ['Menu', 'Submenu']], []],
        ];
    }

    #[DataProvider('blockTypes')]
    public function test_it_extracts_the_reviewable_paths_of_each_block_type(string $type, array $props, array $paths): void
    {
        $snapshot = (new ProseExtractor)->extract([$this->node($type, $props)]);

        $this->assertEqualsCanonicalizing($paths, array_column($snapshot->segments, 'path'));
    }

    public function test_segments_keep_the_stored_text_and_point_back_to_their_block(): void
    {
        $block = $this->node('RichText', ['html' => 'Click <b>Export</b>.'], version: 4);
        $snapshot = (new ProseExtractor)->extract([$block]);

        $segment = $snapshot->segments['S1'];
        $this->assertSame(['ref' => 'S1', 'block' => 'B1', 'field' => 'html', 'path' => 'html', 'text' => 'Click <b>Export</b>.'], $segment);
        $this->assertSame($block['id'], $snapshot->blocks['B1']['id']);
        $this->assertSame(4, $snapshot->blocks['B1']['version']);
    }

    public function test_it_skips_empty_and_letterless_strings(): void
    {
        $snapshot = (new ProseExtractor)->extract([$this->node('BulletList', ['list' => ['', '   ', '42', '—', 'Real item']])]);

        $this->assertSame(['list.4'], array_column($snapshot->segments, 'path'));
    }

    public function test_blocks_keep_their_nesting_order_and_depth(): void
    {
        $tree = [
            $this->node('PrimaryTopic', ['title' => 'OVERVIEW'], children: [
                $this->node('Paragraph', [], children: [$this->node('RichText', ['html' => 'Intro.'])]),
                $this->node('CodeBlock', ['code' => 'x']),
            ]),
        ];

        $snapshot = (new ProseExtractor)->extract($tree);

        $this->assertSame(['B1', 'B2', 'B3', 'B4'], array_keys($snapshot->blocks));
        $this->assertSame([null, 'B1', 'B2', 'B1'], array_column($snapshot->blocks, 'parent'));
        $this->assertSame([0, 1, 2, 1], array_column($snapshot->blocks, 'depth'));
        $this->assertSame(['B2', 'B4'], $snapshot->blocks['B1']['children']);
        $this->assertSame(['B1'], $snapshot->roots);
        // segments follow document order
        $this->assertSame(['B1', 'B3'], array_column($snapshot->segments, 'block'));
    }

    public function test_it_finds_blocks_by_id_and_lists_every_version_in_a_subtree(): void
    {
        $child = $this->node('RichText', ['html' => 'Text.'], version: 7);
        $parent = $this->node('Paragraph', [], version: 2, children: [$child]);
        $snapshot = (new ProseExtractor)->extract([$parent]);

        $this->assertSame('B2', $snapshot->refFor($child['id']));
        $this->assertNull($snapshot->refFor('missing'));
        $this->assertSame([$parent['id'] => 2, $child['id'] => 7], $snapshot->subtreeVersions('B1'));
    }

    private function node(string $type, array $props, int $version = 1, array $children = []): array
    {
        return [
            'id' => sprintf('00000000-0000-0000-0000-%012d', ++$this->ids),
            'parentId' => null,
            'position' => 'a0',
            'type' => $type,
            'props' => $props,
            'options' => [],
            'version' => $version,
            'children' => $children,
        ];
    }
}
