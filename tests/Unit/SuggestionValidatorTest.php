<?php

namespace Tests\Unit;

use App\Ai\ProseExtractor;
use App\Ai\ProseSnapshot;
use App\Ai\SuggestionValidator;
use App\Models\ProseSuggestion;
use Tests\TestCase;

class SuggestionValidatorTest extends TestCase
{
    private int $ids = 0;

    private ProseSnapshot $snapshot;

    /** every block, so the whole snapshot is under review unless a test narrows it */
    private array $chunk;

    protected function setUp(): void
    {
        parent::setUp();

        // B1 topic; B2 paragraph > B3 text; B4 paragraph > B5 text; B6 code block; B7 topic > B8 paragraph > B9 text
        $this->snapshot = (new ProseExtractor)->extract([
            $this->node('PrimaryTopic', ['title' => 'SETUP'], [
                $this->node('Paragraph', [], [$this->node('RichText', ['html' => 'You need PHP, a database and Chrome.'], version: 3)]),
                $this->node('Paragraph', [], [$this->node('RichText', ['html' => 'Run <codetext>composer install</codetext> to basically install it. See <a class="text-link" href="/docs">the docs</a>.'])]),
                $this->node('CodeBlock', ['lang' => 'bash', 'code' => 'composer install']),
            ]),
            $this->node('PrimaryTopic', ['title' => 'OTHER'], [
                $this->node('Paragraph', [], [$this->node('RichText', ['html' => 'Elsewhere.'])]),
            ]),
        ]);
        $this->chunk = array_keys($this->snapshot->blocks);
    }

    public function test_a_valid_text_suggestion_is_kept_with_its_block_and_version(): void
    {
        [$saved] = $this->validate(text: [$this->text('S2', 'You need PHP, a database and Chrome.', 'You need PHP, a database, and Chrome.')]);

        $this->assertSame(ProseSuggestion::TEXT, $saved['kind']);
        $this->assertSame([$this->id('B3')], $saved['block_ids']);
        $this->assertSame([$this->id('B3') => 3], $saved['block_versions']);
        $this->assertSame(['html', 'html'], [$saved['field'], $saved['path']]);
        $this->assertSame('grammar', $saved['rule']);
    }

    public function test_text_suggestions_are_dropped_when_they_cannot_be_applied_safely(): void
    {
        $cases = [
            'unknown segment' => $this->text('S99', 'x', 'y'),
            'segment outside the chunk' => $this->text('S5', 'Elsewhere.', 'Somewhere else.'),
            'original not in the text' => $this->text('S2', 'You need Python', 'You need PHP'),
            'original appears twice' => $this->text('S3', 'install', 'set up'),
            'no change' => $this->text('S2', 'Chrome', 'Chrome'),
            'inline code changed' => $this->text('S3', 'Run <codetext>composer install</codetext> to basically install it.', 'Run <codetext>composer i</codetext> to install it.'),
            'link target changed' => $this->text('S3', 'See <a class="text-link" href="/docs">the docs</a>.', 'See <a class="text-link" href="/elsewhere">the docs</a>.'),
            'tag not allowed' => $this->text('S2', 'Chrome.', '<script>x</script>Chrome.'),
            'attribute not allowed' => $this->text('S2', 'Chrome.', '<b onclick="x">Chrome</b>.'),
            'unknown rule' => [...$this->text('S2', 'Chrome', 'Google Chrome'), 'rule' => 'vibes'],
        ];
        $this->chunk = ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'];

        foreach ($cases as $case => $suggestion) {
            $this->assertSame([], $this->validate(text: [$suggestion]), $case);
        }
    }

    public function test_text_suggestions_may_rephrase_around_inline_code_and_links(): void
    {
        $saved = $this->validate(text: [
            $this->text('S3', 'to basically install it', 'to install it'),
            $this->text('S3', 'See <a class="text-link" href="/docs">the docs</a>.', 'For details, see <a class="text-link" href="/docs">the docs</a>.'),
        ]);

        $this->assertCount(2, $saved);
    }

    public function test_text_suggestions_are_dropped_when_not_requested(): void
    {
        $this->assertSame([], $this->validate(text: [$this->text('S2', 'Chrome', 'Google Chrome')], kinds: ['restructure']));
    }

    public function test_a_restructure_keeps_its_blocks_versions_parent_and_resolved_nodes(): void
    {
        $replacement = [
            ['type' => 'Paragraph', 'props' => [], 'children' => [['type' => 'RichText', 'props' => ['html' => 'You need:']]]],
            ['type' => 'BulletList', 'props' => ['list' => ['PHP', 'A database', 'Chrome']]],
            ['keep' => 'B4'],
        ];

        [$saved] = $this->validate(restructure: [$this->restructure(['B2', 'B4'], $replacement)]);

        $this->assertSame(ProseSuggestion::RESTRUCTURE, $saved['kind']);
        $this->assertSame([$this->id('B2'), $this->id('B4')], $saved['block_ids']);
        $this->assertSame([$this->id('B2'), $this->id('B3'), $this->id('B4'), $this->id('B5')], array_keys($saved['block_versions']));
        $this->assertSame($this->id('B1'), $saved['parent_id']);
        // the kept paragraph is copied in full, marked with where it came from
        $kept = $saved['replacement'][2];
        $this->assertSame(['Paragraph', $this->id('B4')], [$kept['type'], $kept['keptFrom']]);
        $this->assertSame($this->snapshot->blocks['B5']['node']['props'], $kept['children'][0]['props']);
        $this->assertSame([], $saved['replacement'][1]['children']);
    }

    public function test_restructures_are_dropped_when_they_break_the_document(): void
    {
        $bullet = ['type' => 'BulletList', 'props' => ['list' => ['PHP']]];
        $cases = [
            'unknown block' => [['B99'], [$bullet]],
            'block outside the chunk' => [['B8'], [$bullet]],
            'not siblings' => [['B2', 'B3'], [$bullet]],
            'siblings with a gap' => [['B2', 'B6'], [$bullet, ['keep' => 'B6']]],
            'out of order' => [['B4', 'B2'], [$bullet]],
            'not a JSON list' => [['B2'], '{"type": "BulletList"}'],
            'invalid JSON' => [['B2'], '[{'],
            'empty replacement' => [['B2'], []],
            'type that cannot be created' => [['B2'], [['type' => 'SqlTable', 'props' => ['data' => []]]]],
            'prop the type does not have' => [['B2'], [['type' => 'BulletList', 'props' => ['list' => ['PHP'], 'color' => 'red']]]],
            'breaks nesting' => [['B2'], [['type' => 'PrimaryTopic', 'props' => ['title' => 'X']]]],
            'html not allowed' => [['B2'], [['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => '<img src=x>']]]]]],
            'keeps a block it does not replace' => [['B2'], [['keep' => 'B4']]],
            'keeps a block twice' => [['B2'], [['keep' => 'B2'], ['keep' => 'B2']]],
            'drops a code block' => [['B4', 'B6'], [$bullet]],
        ];
        $this->chunk = ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'];

        foreach ($cases as $case => [$blocks, $replacement]) {
            $this->assertSame([], $this->validate(restructure: [$this->restructure($blocks, $replacement)]), $case);
        }
        $this->chunk = ['B1', 'B2', 'B3'];
        $this->assertSame([], $this->validate(restructure: [$this->restructure(['B1'], [$bullet])]), 'subtree partly outside the chunk');
    }

    public function test_a_code_block_kept_inside_a_new_topic_is_allowed(): void
    {
        $replacement = [['type' => 'SecondaryTopic', 'props' => ['title' => 'Installing'], 'children' => [['keep' => 'B4'], ['keep' => 'B6']]]];

        $this->assertCount(1, $this->validate(restructure: [$this->restructure(['B4', 'B6'], $replacement)]));
    }

    public function test_restructures_are_dropped_when_not_requested(): void
    {
        $replacement = [['type' => 'BulletList', 'props' => ['list' => ['PHP']]]];

        $this->assertSame([], $this->validate(restructure: [$this->restructure(['B2'], $replacement)], kinds: ['text']));
    }

    public function test_it_counts_why_suggestions_were_dropped(): void
    {
        $validator = new SuggestionValidator($this->snapshot, $this->chunk, ['text', 'restructure']);
        $validator->validate(['text' => [$this->text('S99', 'x', 'y'), $this->text('S2', 'nope', 'y')], 'restructure' => []]);

        $this->assertSame(['unknown segment' => 1, 'original not found once in the segment' => 1], $validator->dropped());
    }

    private function validate(array $text = [], array $restructure = [], array $kinds = ['text', 'restructure']): array
    {
        return (new SuggestionValidator($this->snapshot, $this->chunk, $kinds))->validate(['text' => $text, 'restructure' => $restructure]);
    }

    private function text(string $segment, string $original, string $suggested): array
    {
        return ['segment' => $segment, 'original' => $original, 'suggested' => $suggested, 'rule' => 'grammar', 'rationale' => 'Because.', 'severity' => 'minor'];
    }

    private function restructure(array $blocks, array|string $replacement): array
    {
        return [
            'blocks' => $blocks,
            'replacement_json' => is_string($replacement) ? $replacement : json_encode($replacement),
            'rule' => 'list', 'rationale' => 'Parallel items read better as a list.', 'severity' => 'major',
        ];
    }

    private function id(string $ref): string
    {
        return $this->snapshot->blocks[$ref]['id'];
    }

    private function node(string $type, array $props, array $children = [], int $version = 1): array
    {
        return ['id' => sprintf('00000000-0000-0000-0000-%012d', ++$this->ids), 'type' => $type, 'props' => $props, 'options' => [], 'version' => $version, 'children' => $children];
    }
}
