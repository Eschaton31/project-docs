<?php

namespace App\Ai\Scaffold;

use App\Ai\ProseSnapshot;
use App\Ai\Rejected;
use App\Ai\SuggestionValidator;
use App\Models\ProseSuggestion;

/**
 * Turns a scaffold's answers into suggestion rows, dropping any that don't fit the document or can't be traced
 * to the scripts: R code that isn't an exact excerpt, packages or files that aren't there, citations to lines
 * that don't exist. One instance spans every call of a scaffold, so each template section is suggested once and
 * the script topics end up in one Script Reference section. What is dropped is counted by reason.
 */
final class ScaffoldValidator
{
    private SuggestionValidator $nodes;

    /** @var array<string, array{ref: string, name: string, text: string, lines: int}> */
    private array $sources;

    private ?string $sectionType;

    private ?string $scriptType;

    /** @var array<string, true> */
    private array $suggested = [];

    /** script topics going into a new Script Reference section: [{file, node, sources, citations, rationale}] */
    private array $scriptTopics = [];

    /** @var array<int, string> template order => the anchor ref each new section was given ("" = first) */
    private array $placedAnchors = [];

    private array $dropped = [];

    /**
     * @param  ProseSnapshot  $document  the whole document as it was read
     * @param  ?string  $scopeRef  the topic new sections go into; null = the top level
     * @param  list<array{ref: string, name: string, text: string}>  $sources
     */
    public function __construct(private ProseSnapshot $document, private ?string $scopeRef, array $sources, private array $index)
    {
        $this->nodes = new SuggestionValidator($document, array_keys($document->blocks), ProseSuggestion::KINDS);
        $this->sources = [];
        foreach ($sources as $source) {
            $this->sources[$source['ref']] = [...$source, 'lines' => count(explode("\n", rtrim($source['text'], "\n")))];
        }
        $this->sectionType = ScaffoldTemplate::topicUnder($scopeRef ? $document->blocks[$scopeRef]['type'] : null);
        $this->scriptType = ScaffoldTemplate::topicUnder($this->sectionType);
    }

    /**
     * @param  array{sections?: list<array>, expand?: list<array>, script_topics?: list<array>}  $output
     * @return list<array> attributes for ProseSuggestion rows (without review_id)
     */
    public function validate(array $output): array
    {
        $rows = [];
        foreach ($output['sections'] ?? [] as $section) {
            $rows[] = $this->attempt(fn () => $this->section($section));
        }
        foreach ($output['expand'] ?? [] as $expand) {
            $rows[] = $this->attempt(fn () => $this->expand($expand));
        }
        foreach ($output['script_topics'] ?? [] as $topic) {
            $rows[] = $this->attempt(fn () => $this->scriptTopic($topic));
        }

        return array_values(array_filter($rows));
    }

    /**
     * The new Script Reference section holding every script topic that had nowhere else to go, placed in the
     * template's order among the new sections; null when there are none.
     */
    public function scriptReference(): ?array
    {
        if ($this->scriptTopics === [] || isset($this->suggested[ScaffoldTemplate::SCRIPT_REFERENCE])) {
            return null;
        }

        $title = ScaffoldTemplate::sections()[ScaffoldTemplate::SCRIPT_REFERENCE];
        $node = [
            'type' => $this->sectionType,
            'props' => ['title' => $this->sectionType === 'PrimaryTopic' ? mb_strtoupper($title) : mb_convert_case(mb_strtolower($title), MB_CASE_TITLE)],
            'options' => [],
            'children' => array_column($this->scriptTopics, 'node'),
        ];

        return [
            ...$this->placement(ScaffoldTemplate::SCRIPT_REFERENCE, ''),
            'kind' => ProseSuggestion::SCAFFOLD,
            'rule' => ScaffoldTemplate::SCRIPT_REFERENCE,
            'severity' => 'minor',
            'rationale' => 'Documents each script: what it does and its main functions.',
            'replacement' => [$node],
            'sources' => array_values(array_unique(array_merge(...array_column($this->scriptTopics, 'sources')))),
            'citations' => array_merge(...array_column($this->scriptTopics, 'citations')),
        ];
    }

    /**
     * @return array<string, int> reason => how many suggestions were dropped for it
     */
    public function dropped(): array
    {
        return $this->dropped;
    }

    private function section(array $section): array
    {
        $key = (string) ($section['section'] ?? '');
        $this->assert(ScaffoldTemplate::order($key) !== null, 'unknown section');
        $this->assert($key !== ScaffoldTemplate::SCRIPT_REFERENCE, 'script reference outside a script call');
        $this->assert(! isset($this->suggested[$key]), 'section suggested twice');

        $nodes = $this->newNodes($section, $this->scopeRef ? $this->document->blocks[$this->scopeRef]['type'] : null);
        $this->assert(count($nodes) === 1 && $nodes[0]['type'] === $this->sectionType, 'not one topic of the section level');

        $after = (string) ($section['after'] ?? '');
        $this->assert($after === '' || in_array($after, $this->children(), true), 'after is not a block where sections go');

        $row = [
            ...$this->common($section, $nodes),
            ...$this->placement($key, $after),
            'kind' => ProseSuggestion::SCAFFOLD,
            'rule' => $key,
            'replacement' => $nodes,
        ];
        $this->suggested[$key] = true;
        $this->placedAnchors[$row['order']] = $after;

        return $row;
    }

    private function expand(array $expand): array
    {
        $key = (string) ($expand['section'] ?? '');
        $this->assert(ScaffoldTemplate::order($key) !== null, 'unknown section');

        $anchor = (string) ($expand['anchor'] ?? '');
        $this->assert(isset($this->document->blocks[$anchor]), 'unknown block');
        $position = $expand['position'] ?? null;
        $this->assert(in_array($position, ProseSuggestion::POSITIONS, true), 'unknown position');
        $parent = in_array($position, ['start', 'end'], true) ? $anchor : $this->document->blocks[$anchor]['parent'];

        $nodes = $this->newNodes($expand, $parent === null ? null : $this->document->blocks[$parent]['type']);
        $block = $this->document->blocks[$anchor];

        return [
            ...$this->common($expand, $nodes),
            'kind' => ProseSuggestion::EXPAND,
            'rule' => $key,
            'order' => ScaffoldTemplate::order($key),
            'block_ids' => [$block['id']],
            'block_versions' => [$block['id'] => $block['version']],
            'replacement' => $nodes,
            'parent_id' => $parent === null ? null : $this->document->blocks[$parent]['id'],
            'position' => $position,
        ];
    }

    /**
     * Into an existing Script Reference topic: an expansion at its end. Otherwise kept for the new section
     * scriptReference() builds; nothing is returned for it here.
     */
    private function scriptTopic(array $topic): ?array
    {
        $this->assert(isset($this->sources[$topic['file'] ?? '']), 'unknown file');
        $this->assert($this->scriptType !== null, 'no topic level for scripts here');

        $into = (string) ($topic['into'] ?? '');
        if ($into !== '') {
            $this->assert(isset($this->document->blocks[$into]), 'unknown block');

            return $this->expand([...$topic, 'section' => ScaffoldTemplate::SCRIPT_REFERENCE, 'anchor' => $into, 'position' => 'end']);
        }

        $nodes = $this->newNodes($topic, $this->sectionType);
        $this->assert(count($nodes) === 1 && $nodes[0]['type'] === $this->scriptType, 'not one topic of the script level');
        $common = $this->common($topic, $nodes);
        $this->scriptTopics[] = ['node' => $nodes[0], 'sources' => $common['sources'], 'citations' => $common['citations']];

        return null;
    }

    /**
     * Where a new section goes: after an existing block ("" = first), in the template's order. Script
     * Reference, which has no "after" of its own, shares the anchor of the closest new section around it in the
     * template, so placement puts it next to that one; with no new sections it goes last.
     */
    private function placement(string $key, string $afterRef): array
    {
        $order = ScaffoldTemplate::order($key);
        if ($key === ScaffoldTemplate::SCRIPT_REFERENCE) {
            $children = $this->children();
            $afterRef = $this->anchorOfNeighbour($order) ?? ($children ? end($children) : '');
        }
        $after = $afterRef !== '' ? $this->document->blocks[$afterRef] : null;

        return [
            'order' => $order,
            'block_ids' => $after ? [$after['id']] : [],
            'block_versions' => $after ? [$after['id'] => $after['version']] : [],
            'parent_id' => $this->scopeRef ? $this->document->blocks[$this->scopeRef]['id'] : null,
            'position' => 'after',
        ];
    }

    /**
     * The anchor of the closest new section before $order in the template, else the closest after it; null
     * when there are no new sections.
     */
    private function anchorOfNeighbour(int $order): ?string
    {
        $placed = $this->placedAnchors;
        ksort($placed);
        $before = array_filter($placed, fn (int $each) => $each < $order, ARRAY_FILTER_USE_KEY);
        if ($before) {
            return end($before);
        }
        $after = array_filter($placed, fn (int $each) => $each > $order, ARRAY_FILTER_USE_KEY);

        return $after ? reset($after) : null;
    }

    /**
     * Sources, citations and the checks every suggestion shares.
     */
    private function common(array $suggestion, array $nodes): array
    {
        $sources = array_values(array_unique((array) ($suggestion['sources'] ?? [])));
        $this->assert($sources !== [] && array_diff($sources, ProseSuggestion::SCAFFOLD_SOURCES) === [], 'unknown sources');

        $citations = [];
        foreach ((array) ($suggestion['citations'] ?? []) as $citation) {
            $file = $this->sources[$citation['file'] ?? ''] ?? throw new Rejected('cites an unknown file');
            [$from, $to] = [(int) ($citation['from'] ?? 0), (int) ($citation['to'] ?? 0)];
            $this->assert($from >= 1 && $from <= $to && $to <= $file['lines'], 'cites lines that are not in the file');
            $citations[] = ['file' => $file['ref'], 'from' => $from, 'to' => $to];
        }
        $this->assert(! in_array('code', $sources, true) || $citations !== [], 'code without citations');
        if ($citations && ! in_array('code', $sources, true)) {
            $sources[] = 'code';
        }

        $this->assertRCodeIsExcerpted($nodes, $citations);
        $this->assertFileTreesNameUploads($nodes);
        if (($suggestion['section'] ?? null) === 'dependencies') {
            $this->assertPackagesAreLoaded($nodes);
        }

        return [
            'severity' => 'minor',
            'rationale' => mb_substr(trim((string) ($suggestion['rationale'] ?? '')), 0, 1000),
            'sources' => $sources,
            'citations' => $citations,
        ];
    }

    private function newNodes(array $suggestion, ?string $parentType): array
    {
        return $this->nodes->newNodes($suggestion['blocks_json'] ?? '', $parentType);
    }

    /**
     * Every CodeBlock in R is an exact excerpt (ignoring indentation and spacing) of a script it cites.
     */
    private function assertRCodeIsExcerpted(array $nodes, array $citations): void
    {
        $cited = array_unique(array_column($citations, 'file'));
        foreach ($this->each($nodes, 'CodeBlock') as $node) {
            if (strtolower((string) ($node['props']['lang'] ?? '')) !== 'r') {
                continue;
            }
            $code = self::squash((string) ($node['props']['code'] ?? ''));
            $this->assert($code !== '', 'R code that is not in the scripts');
            $found = false;
            foreach ($cited as $ref) {
                $found = $found || str_contains(self::squash($this->sources[$ref]['text']), $code);
            }
            $this->assert($found, 'R code that is not in the scripts');
        }
    }

    private function assertFileTreesNameUploads(array $nodes): void
    {
        $names = [];
        foreach ($this->sources as $source) {
            $names[$source['name']] = true;
            $names[basename($source['name'])] = true;
        }

        foreach ($this->each($nodes, 'FileTree') as $node) {
            $walk = function (array $items) use (&$walk, $names) {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if (($item['type'] ?? 'file') !== 'folder') {
                        $this->assert(isset($names[trim(strip_tags((string) ($item['name'] ?? '')))]), 'file tree names a file that was not uploaded');
                    }
                    $walk((array) ($item['children'] ?? []));
                }
            };
            $walk((array) ($node['props']['list'] ?? []));
        }
    }

    private function assertPackagesAreLoaded(array $nodes): void
    {
        $packages = array_flip(array_map('strtolower', $this->index['packages']));
        foreach ($this->each($nodes, 'ItemDescriptionTable') as $node) {
            foreach ((array) ($node['props']['data'] ?? []) as $row) {
                $name = strtolower(trim(html_entity_decode(strip_tags((string) ($row['item'] ?? '')))));
                $this->assert($name === '' || isset($packages[$name]), 'package the scripts do not use');
            }
        }
    }

    /**
     * @return \Generator<array> every node of this type in the tree
     */
    private function each(array $nodes, string $type): \Generator
    {
        foreach ($nodes as $node) {
            if ($node['type'] === $type) {
                yield $node;
            }
            yield from $this->each($node['children'] ?? [], $type);
        }
    }

    /**
     * @return list<string> refs of the blocks directly where new sections go
     */
    private function children(): array
    {
        return $this->scopeRef ? $this->document->blocks[$this->scopeRef]['children'] : $this->document->roots;
    }

    private static function squash(string $code): string
    {
        $lines = array_map(fn (string $line) => preg_replace('/\s+/', ' ', trim($line)), explode("\n", str_replace("\r\n", "\n", $code)));

        return implode("\n", array_filter($lines, fn (string $line) => $line !== ''));
    }

    private function attempt(\Closure $build): ?array
    {
        try {
            return $build();
        } catch (Rejected $rejected) {
            $this->dropped[$rejected->getMessage()] = ($this->dropped[$rejected->getMessage()] ?? 0) + 1;

            return null;
        }
    }

    private function assert(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new Rejected($reason);
        }
    }
}
