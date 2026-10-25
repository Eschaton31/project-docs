<?php

namespace App\Ai;

use App\Ai\Agents\ProseReviewer;
use App\Models\ProseSuggestion;
use App\Services\DocumentTree;
use Illuminate\Validation\ValidationException;

/**
 * Turns the model's output for one chunk into suggestion rows, dropping any that couldn't be applied exactly
 * or would damage the document. What is dropped is counted by reason, never saved.
 */
final class SuggestionValidator
{
    /** Inline tags the editor's rich text keeps (builder/editor/richText.js), and the attributes allowed on each. */
    private const TAGS = ['b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 'br' => [], 'codetext' => [], 'span' => [], 'a' => ['href', 'class', 'target']];

    /** Props every block that has them keeps as a list (of items, rows or tree nodes). */
    private const LIST_PROPS = ['list', 'data', 'table'];

    private array $inChunk;

    private array $dropped = [];

    /**
     * @param  list<string>  $chunk  refs of the blocks under review
     * @param  list<string>  $kinds  text, restructure, expand
     */
    public function __construct(private ProseSnapshot $snapshot, array $chunk, private array $kinds)
    {
        $this->inChunk = array_flip($chunk);
    }

    /**
     * @param  array{text?: list<array>, restructure?: list<array>, expand?: list<array>}  $output
     * @return list<array> attributes for ProseSuggestion rows (without review_id)
     */
    public function validate(array $output): array
    {
        $rows = [];

        foreach ($output['text'] ?? [] as $suggestion) {
            $rows[] = $this->attempt(fn () => $this->text($suggestion), ProseSuggestion::TEXT);
        }
        foreach ($output['restructure'] ?? [] as $suggestion) {
            $rows[] = $this->attempt(fn () => $this->restructure($suggestion), ProseSuggestion::RESTRUCTURE);
        }
        foreach ($output['expand'] ?? [] as $suggestion) {
            $rows[] = $this->attempt(fn () => $this->expand($suggestion), ProseSuggestion::EXPAND);
        }

        return array_values(array_filter($rows));
    }

    /**
     * @return array<string, int> reason => how many suggestions were dropped for it
     */
    public function dropped(): array
    {
        return $this->dropped;
    }

    private function attempt(\Closure $build, string $kind): ?array
    {
        try {
            if (! in_array($kind, $this->kinds, true)) {
                throw new Rejected("{$kind} not requested");
            }

            return $build();
        } catch (Rejected $rejected) {
            $this->dropped[$rejected->getMessage()] = ($this->dropped[$rejected->getMessage()] ?? 0) + 1;

            return null;
        }
    }

    private function text(array $suggestion): array
    {
        $segment = $this->snapshot->segments[$suggestion['segment'] ?? ''] ?? throw new Rejected('unknown segment');
        $this->assert(isset($this->inChunk[$segment['block']]), 'segment not under review');

        [$original, $suggested] = [(string) ($suggestion['original'] ?? ''), (string) ($suggestion['suggested'] ?? '')];
        $this->assert($original !== '' && substr_count($segment['text'], $original) === 1, 'original not found once in the segment');
        $this->assert($original !== $suggested, 'no change');
        $this->assertSafeHtml($suggested);
        $this->assert($this->keepsProtectedSpans($original, $suggested), 'inline code or a link target changed');

        $block = $this->snapshot->blocks[$segment['block']];

        return [
            ...$this->common($suggestion),
            'kind' => ProseSuggestion::TEXT,
            'block_ids' => [$block['id']],
            'block_versions' => [$block['id'] => $block['version']],
            'field' => $segment['field'],
            'path' => $segment['path'],
            'original' => $original,
            'suggested' => $suggested,
        ];
    }

    private function restructure(array $suggestion): array
    {
        $refs = array_values((array) ($suggestion['blocks'] ?? []));
        $this->assert($refs !== [], 'no blocks');
        foreach ($refs as $ref) {
            $this->assert(isset($this->snapshot->blocks[$ref]), 'unknown block');
        }

        $replaced = array_merge(...array_map(fn ($ref) => $this->snapshot->subtreeRefs($ref), $refs));
        foreach ($replaced as $ref) {
            $this->assert(isset($this->inChunk[$ref]), 'block not under review');
        }

        $parent = $this->snapshot->blocks[$refs[0]]['parent'];
        $siblings = $parent === null ? $this->snapshot->roots : $this->snapshot->blocks[$parent]['children'];
        $start = array_search($refs[0], $siblings, true);
        $this->assert(array_slice($siblings, (int) $start, count($refs)) === $refs, 'blocks are not neighbouring siblings');

        $nodes = json_decode((string) ($suggestion['replacement_json'] ?? ''), true);
        $this->assert(is_array($nodes) && $nodes !== [] && array_is_list($nodes), 'replacement is not a list of nodes');

        $kept = [];
        $nodes = $this->resolveAll($nodes, array_flip($replaced), $kept);
        $edited = $this->editedInPlace($refs, $nodes);
        if ($edited !== null) {
            // props the edit leaves out stay as they were (a table's widths, a code block's label)
            $nodes[0]['props'] += $this->snapshot->blocks[$edited]['node']['props'];
            $this->assert($nodes[0]['props'] != $this->snapshot->blocks[$edited]['node']['props'], 'no change');
        }
        $this->assertNothingLost($replaced, $kept, $edited);

        $parentType = $parent === null ? null : $this->snapshot->blocks[$parent]['type'];
        try {
            app(DocumentTree::class)->assertValidNodes($nodes, $parentType);
        } catch (ValidationException) {
            throw new Rejected('replacement breaks nesting rules');
        }

        $versions = [];
        foreach ($refs as $ref) {
            $versions += $this->snapshot->subtreeVersions($ref);
        }

        return [
            ...$this->common($suggestion),
            'kind' => ProseSuggestion::RESTRUCTURE,
            'block_ids' => array_map(fn ($ref) => $this->snapshot->blocks[$ref]['id'], $refs),
            'block_versions' => $versions,
            'replacement' => $nodes,
            'parent_id' => $parent === null ? null : $this->snapshot->blocks[$parent]['id'],
        ];
    }

    /**
     * New blocks only, placed next to or inside the anchor; nothing already there is touched.
     */
    private function expand(array $suggestion): array
    {
        $anchor = (string) ($suggestion['anchor'] ?? '');
        $this->assert(isset($this->snapshot->blocks[$anchor]), 'unknown block');
        $this->assert(isset($this->inChunk[$anchor]), 'block not under review');

        $position = $suggestion['position'] ?? null;
        $this->assert(in_array($position, ProseSuggestion::POSITIONS, true), 'unknown position');
        $parent = in_array($position, ['start', 'end'], true) ? $anchor : $this->snapshot->blocks[$anchor]['parent'];

        $sources = array_values(array_unique((array) ($suggestion['sources'] ?? [])));
        $this->assert($sources !== [] && array_diff($sources, ProseSuggestion::SOURCES) === [], 'unknown sources');

        $nodes = json_decode((string) ($suggestion['blocks_json'] ?? ''), true);
        $this->assert(is_array($nodes) && $nodes !== [] && array_is_list($nodes), 'replacement is not a list of nodes');
        $none = [];
        // with nothing replaced, any {"keep": …} is refused
        $nodes = $this->resolveAll($nodes, [], $none);

        $parentType = $parent === null ? null : $this->snapshot->blocks[$parent]['type'];
        try {
            app(DocumentTree::class)->assertValidNodes($nodes, $parentType);
        } catch (ValidationException) {
            throw new Rejected('replacement breaks nesting rules');
        }

        $block = $this->snapshot->blocks[$anchor];

        return [
            ...$this->common($suggestion),
            'kind' => ProseSuggestion::EXPAND,
            'block_ids' => [$block['id']],
            'block_versions' => [$block['id'] => $block['version']],
            'replacement' => $nodes,
            'parent_id' => $parent === null ? null : $this->snapshot->blocks[$parent]['id'],
            'position' => $position,
            'sources' => $sources,
        ];
    }

    /**
     * A new node (checked against the catalogue), or {"keep": ref} expanded to a full copy of that block.
     *
     * @param  array<string, int>  $replaced  refs that may be kept
     * @param  array<string, true>  $kept  refs kept so far, including everything inside them
     */
    private function resolve(mixed $node, array $replaced, array &$kept): array
    {
        $this->assert(is_array($node), 'replacement is not a list of nodes');

        if (isset($node['keep'])) {
            $ref = (string) $node['keep'];
            $this->assert(isset($replaced[$ref]), 'keeps a block it does not replace');
            foreach ($this->snapshot->subtreeRefs($ref) as $each) {
                $this->assert(! isset($kept[$each]), 'keeps a block twice');
                $kept[$each] = true;
            }

            return $this->copy($ref);
        }

        $type = (string) ($node['type'] ?? '');
        $this->assert(BlockCatalogue::creatable($type), 'type cannot be created');
        $props = $node['props'] ?? [];
        $this->assert(is_array($props) && array_diff(array_keys($props), BlockCatalogue::props($type)) === [], 'unknown props');
        foreach (array_intersect_key($props, array_flip(self::LIST_PROPS)) as $list) {
            $this->assert(is_array($list) && array_is_list($list), 'prop has the wrong shape');
        }
        foreach (BlockCatalogue::rawProps($type) as $raw) {
            $this->assert(is_string($props[$raw] ?? ''), 'prop has the wrong shape');
        }
        // a block's content shows its segments as «S12»; a rewrite has to spell the text out
        array_walk_recursive($props, fn ($value) => $this->assert(! is_string($value) || ! preg_match('/«S\d+»/u', $value), 'leaves a segment ref in the text'));
        // source code is shown as text, not HTML
        $html = array_diff_key($props, array_flip(BlockCatalogue::rawProps($type)));
        array_walk_recursive($html, fn ($value) => is_string($value) && $this->assertSafeHtml($value));

        $children = $node['children'] ?? [];
        $this->assert(is_array($children) && array_is_list($children), 'replacement is not a list of nodes');

        return [
            'type' => $type,
            'props' => $props,
            'options' => [],
            'children' => $this->resolveAll($children, $replaced, $kept),
        ];
    }

    /**
     * A loop rather than array_map: an arrow function would capture $kept by value and lose what it records.
     */
    private function resolveAll(array $nodes, array $replaced, array &$kept): array
    {
        $resolved = [];
        foreach ($nodes as $node) {
            $resolved[] = $this->resolve($node, $replaced, $kept);
        }

        return $resolved;
    }

    private function copy(string $ref): array
    {
        $block = $this->snapshot->blocks[$ref];

        return [
            ...$block['node'],
            'keptFrom' => $block['id'],
            'children' => array_map($this->copy(...), $block['children']),
        ];
    }

    /**
     * The ref of a data block (code, diagram, dictionary…) the restructure rewrites as one new block of the same
     * type, the only way to change one; null when it isn't such an edit.
     */
    private function editedInPlace(array $refs, array $nodes): ?string
    {
        $block = $this->snapshot->blocks[$refs[0]];
        if (count($refs) !== 1 || count($nodes) !== 1 || isset($nodes[0]['keptFrom'])
            || $nodes[0]['type'] !== $block['type'] || ! BlockCatalogue::isData($block['type'])) {
            return null;
        }

        $this->assert(! $block['truncated'], 'rewrites a block it could not see in full');

        return $refs[0];
    }

    /**
     * Prose can be rewritten into new blocks, but anything else (code, diagrams, images, query results) has to be
     * kept, unless the suggestion is an in-place edit of that one data block.
     */
    private function assertNothingLost(array $replaced, array $kept, ?string $edited): void
    {
        foreach ($replaced as $ref) {
            $this->assert(
                isset($kept[$ref]) || $ref === $edited || BlockCatalogue::rewritable($this->snapshot->blocks[$ref]['type']),
                'drops a block that is not prose',
            );
        }
    }

    private function assertSafeHtml(string $html): void
    {
        preg_match_all('/<\s*(\/?)\s*([a-z0-9-]*)([^>]*)>/i', $html, $tags, PREG_SET_ORDER);
        foreach ($tags as [, $closing, $name, $attributes]) {
            $name = strtolower($name);
            $this->assert(array_key_exists($name, self::TAGS), 'html not allowed');
            if ($closing) {
                continue;
            }

            preg_match_all('/([a-z-]+)\s*=/i', $attributes, $names);
            $this->assert(array_diff(array_map('strtolower', $names[1]), self::TAGS[$name]) === [], 'html not allowed');
            $this->assert(! preg_match('/javascript:/i', $attributes), 'html not allowed');
        }
    }

    private function keepsProtectedSpans(string $original, string $suggested): bool
    {
        preg_match_all('#<codetext>.*?</codetext>|href\s*=\s*"[^"]*"#is', $original, $spans);

        foreach ($spans[0] as $span) {
            if (! str_contains($suggested, $span)) {
                return false;
            }
        }

        return true;
    }

    private function common(array $suggestion): array
    {
        $rule = $suggestion['rule'] ?? null;
        $this->assert(in_array($rule, ProseReviewer::RULES, true), 'unknown rule');

        return [
            'rule' => $rule,
            'rationale' => mb_substr(trim((string) ($suggestion['rationale'] ?? '')), 0, 1000),
            'severity' => ($suggestion['severity'] ?? null) === 'major' ? 'major' : 'minor',
        ];
    }

    private function assert(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new Rejected($reason);
        }
    }
}
