<?php

namespace App\Ai;

/**
 * The blocks and reviewable text of a document (or one topic) at the moment it was read, with short refs
 * (B1, S1) the model cites instead of block ids.
 */
final class ProseSnapshot
{
    /**
     * @param  array<string, array{ref: string, id: string, type: string, parent: ?string, depth: int, version: int, children: list<string>, node: array, content: ?string, truncated: bool}>  $blocks  in document order; content is a structured block's props as JSON, with «S12» in place of each segment
     * @param  array<string, array{ref: string, block: string, field: string, path: string, text: string}>  $segments  in document order
     * @param  list<string>  $roots  refs of the top-level blocks
     */
    public function __construct(
        public readonly array $blocks,
        public readonly array $segments,
        public readonly array $roots,
    ) {}

    public function refFor(string $blockId): ?string
    {
        foreach ($this->blocks as $ref => $block) {
            if ($block['id'] === $blockId) {
                return $ref;
            }
        }

        return null;
    }

    /**
     * @return list<string> the block's ref and those of everything under it, in document order
     */
    public function subtreeRefs(string $ref): array
    {
        return [$ref, ...array_merge([], ...array_map(fn (string $child) => $this->subtreeRefs($child), $this->blocks[$ref]['children']))];
    }

    /**
     * @return array<string, int> block id => version for the block and everything under it
     */
    public function subtreeVersions(string $ref): array
    {
        $versions = [];
        foreach ($this->subtreeRefs($ref) as $each) {
            $versions[$this->blocks[$each]['id']] = $this->blocks[$each]['version'];
        }

        return $versions;
    }

    /**
     * @return list<array> segments of the given blocks
     */
    public function segmentsOf(array $blockRefs): array
    {
        $wanted = array_flip($blockRefs);

        return array_values(array_filter($this->segments, fn (array $segment) => isset($wanted[$segment['block']])));
    }

    /**
     * Characters of reviewable text and structured content in the block and everything under it.
     */
    public function size(string $ref): int
    {
        return $this->sizeOf($this->subtreeRefs($ref));
    }

    public function totalSize(): int
    {
        return $this->sizeOf(array_keys($this->blocks));
    }

    /**
     * Characters of the given blocks' own text and content, not counting anything under them.
     */
    public function sizeOf(array $blockRefs): int
    {
        $content = array_sum(array_map(fn (string $ref) => mb_strlen((string) $this->blocks[$ref]['content']), $blockRefs));

        return $content + array_sum(array_map(fn (array $segment) => mb_strlen($segment['text']), $this->segmentsOf($blockRefs)));
    }
}
