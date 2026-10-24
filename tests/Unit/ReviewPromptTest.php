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

    public function test_chunks_without_any_text_are_dropped(): void
    {
        $snapshot = $this->snapshot([$this->node('CodeBlock', ['code' => 'SELECT 1;'])]);

        $this->assertSame([], ReviewPrompt::chunks($snapshot, 100));
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
    }

    public function test_the_prompt_says_when_restructures_are_not_wanted(): void
    {
        $snapshot = $this->snapshot([$this->text('Hello.')]);

        $prompt = ReviewPrompt::render($snapshot, ['B1', 'B2'], ['text']);

        $this->assertStringContainsString('Suggestion kinds requested: text', $prompt);
        $this->assertStringContainsString('Return an empty "restructure" list', $prompt);
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
