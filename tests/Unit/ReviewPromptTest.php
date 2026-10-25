<?php

namespace Tests\Unit;

use App\Ai\ProseExtractor;
use App\Ai\ProseSnapshot;
use App\Ai\ReviewPrompt;
use PHPUnit\Framework\TestCase;

class ReviewPromptTest extends TestCase
{
    private int $ids = 0;

    public function test_a_small_document_is_one_chunk_of_every_block(): void
    {
        $snapshot = $this->snapshot([
            $this->topic('OVERVIEW', [$this->text(str_repeat('a', 10)), $this->text(str_repeat('b', 10))]),
        ]);

        $this->assertSame([['B1', 'B2', 'B3', 'B4', 'B5']], ReviewPrompt::chunks($snapshot, 1000));
    }

    public function test_sibling_subtrees_are_packed_until_the_limit(): void
    {
        $snapshot = $this->snapshot([
            $this->topic('A', [$this->text(str_repeat('a', 40))]),
            $this->topic('B', [$this->text(str_repeat('b', 40))]),
            $this->topic('C', [$this->text(str_repeat('c', 40))]),
        ]);

        // each topic is 41 characters (title + text): two fit in 100
        $chunks = ReviewPrompt::chunks($snapshot, 100);

        $this->assertSame([['B1', 'B2', 'B3', 'B4', 'B5', 'B6'], ['B7', 'B8', 'B9']], $chunks);
    }

    public function test_a_subtree_over_the_limit_is_split_between_its_children(): void
    {
        $snapshot = $this->snapshot([
            $this->topic('BIG', [$this->text(str_repeat('a', 60)), $this->text(str_repeat('b', 60))]),
        ]);

        $chunks = ReviewPrompt::chunks($snapshot, 100);

        // the topic's own title goes with its first child
        $this->assertSame([['B1', 'B2', 'B3'], ['B4', 'B5']], $chunks);
    }

    public function test_chunks_with_nothing_to_review_are_dropped(): void
    {
        $snapshot = $this->snapshot([$this->node('Paragraph', [])]);

        $this->assertSame([], ReviewPrompt::chunks($snapshot, 100));
    }

    public function test_a_structure_without_text_is_still_reviewed(): void
    {
        $snapshot = $this->snapshot([$this->node('Mermaid', ['code' => "flowchart LR\n  A --> B"])]);

        $this->assertSame([['B1']], ReviewPrompt::chunks($snapshot, 100));
        $prompt = ReviewPrompt::render($snapshot, ['B1'], ['restructure']);
        // the outline previews the source; the block shows it in full
        $this->assertStringContainsString('* B1 Mermaid — flowchart LR A --> B', $prompt);
        $this->assertStringContainsString('  content: {"code":"flowchart LR\n  A --> B"}', $prompt);
    }

    public function test_structured_blocks_show_their_content_with_segment_refs(): void
    {
        $snapshot = $this->snapshot([$this->node('KeyValueTable', ['table' => [['id' => 'Host', 'value' => 'The database host']]])]);

        $prompt = ReviewPrompt::render($snapshot, ['B1'], ['text', 'restructure']);

        $this->assertStringContainsString('S1 [table.0.id]: «Host»', $prompt);
        $this->assertStringContainsString('content: {"table":[{"id":"«S1»","value":"«S2»"}]}', $prompt);
    }

    public function test_the_prompt_shows_the_outline_and_only_the_chunks_text(): void
    {
        $snapshot = $this->snapshot([
            $this->topic('OVERVIEW', [$this->text('Reviewed <b>text</b>.')]),
            $this->topic('OTHER', [$this->text('Not in this chunk.')]),
        ]);

        $prompt = ReviewPrompt::render($snapshot, ['B1', 'B2', 'B3'], ['text', 'restructure']);

        $this->assertStringContainsString('Suggestion kinds requested: text, restructure', $prompt);
        // outline lists every block, with a preview
        $this->assertStringContainsString('B4 PrimaryTopic — OTHER', $prompt);
        // full text only for the chunk, quoted exactly
        $this->assertStringContainsString('S2 [html]: «Reviewed <b>text</b>.»', $prompt);
        $this->assertStringNotContainsString('«Not in this chunk.»', $prompt);
        // plain prose blocks don't repeat their text as content
        $this->assertStringNotContainsString('  content:', $prompt);
    }

    public function test_the_prompt_says_when_restructures_are_not_wanted(): void
    {
        $snapshot = $this->snapshot([$this->text('Hello.')]);

        $prompt = ReviewPrompt::render($snapshot, ['B1', 'B2'], ['text']);

        $this->assertStringContainsString('Suggestion kinds requested: text', $prompt);
        $this->assertStringContainsString('Return an empty "restructure" list', $prompt);
        $this->assertStringContainsString('Return an empty "expand" list', $prompt);
        $this->assertStringNotContainsString('## Brief', $prompt);
    }

    public function test_the_prompt_carries_the_brief_when_there_is_one(): void
    {
        $snapshot = $this->snapshot([$this->text('Hello.')]);

        $prompt = ReviewPrompt::render($snapshot, ['B1', 'B2'], ['expand'], "  Explain retries: 3 tries, 30s apart.\n");

        $this->assertStringContainsString('Suggestion kinds requested: expand', $prompt);
        $this->assertStringContainsString("## Brief\n", $prompt);
        $this->assertStringContainsString('«Explain retries: 3 tries, 30s apart.»', $prompt);
        $this->assertStringNotContainsString('Return an empty "expand" list', $prompt);
    }

    private function snapshot(array $nodes): ProseSnapshot
    {
        return (new ProseExtractor)->extract($nodes);
    }

    private function topic(string $title, array $children): array
    {
        return $this->node('PrimaryTopic', ['title' => $title], $children);
    }

    /** A paragraph with one RichText: two blocks, one segment. */
    private function text(string $html): array
    {
        return $this->node('Paragraph', [], [$this->node('RichText', ['html' => $html])]);
    }

    private function node(string $type, array $props, array $children = []): array
    {
        return ['id' => 'id-'.++$this->ids, 'type' => $type, 'props' => $props, 'options' => [], 'version' => 1, 'children' => $children];
    }
}
