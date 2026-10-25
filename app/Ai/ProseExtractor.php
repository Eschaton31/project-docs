<?php

namespace App\Ai;

/**
 * Reads the reviewable text out of a block tree (the nested nodes DocumentTree builds).
 *
 * Text is kept exactly as stored, inline HTML included, so a suggestion's "original" can be matched against
 * the block and replaced without converting anything. Code, diagrams, query results, product names, people's
 * names and UI labels (breadcrumbs) are not reviewed as text; structured blocks carry them as read-only content
 * instead, so a restructure can see (and rewrite) the whole block.
 */
final class ProseExtractor
{
    /** Types whose props are fully shown by their segments and flags, so they carry no content. */
    private const PLAIN = [
        'PrimaryTopic', 'SecondaryTopic', 'TertiaryTopic', 'QuaternaryTopic', 'Paragraph', 'RichText',
        'TertiaryTitle', 'Placeholder', 'Alert', 'BulletList', 'NumberedList',
    ];

    /** Longest content shown in full; a longer one is cut and its block can only be kept. */
    public const MAX_CONTENT = 4000;

    /**
     * Where each type keeps its prose:
     *   key               a string prop
     *   key*              a BulletList/NumberedList list: items and sub-lists, recursively
     *   key~field         a tree of nodes (ConceptList, FileTree): that field of each node and its children
     *   key[].column      a grid: that column of each row (a string, or one string per line)
     *   …!prop            skipped when the block's prop is set
     */
    public const FIELDS = [
        'PrimaryTopic' => ['title'],
        'SecondaryTopic' => ['title'],
        'TertiaryTopic' => ['title'],
        'QuaternaryTopic' => ['title'],
        'TertiaryTitle' => ['text'],
        'Placeholder' => ['text'],
        'RichText' => ['html'],
        'Alert' => ['title'],
        'Signature' => ['label', 'designation'],
        'BulletList' => ['list*'],
        'NumberedList' => ['list*'],
        'ConceptList' => ['list~id!highlight', 'list~value'],
        'FileTree' => ['description', 'list~note'],
        'Image' => ['description'],
        'SqlTable' => ['description'],
        'CodeBlock' => ['label'],
        'ItemDescriptionTable' => ['itemName', 'descriptionName', 'data[].description', 'data[].item!mono'],
        'KeyValueTable' => ['table[].id', 'table[].value'],
        'ConversionDictionary' => ['data[].description'],
        'DataDictionary' => ['data[].description'],
        'TechStack' => ['data[].component'],
        // not functionality: neighbouring rows sharing it form one group, so it only changes by restructure
        'TestScenarios' => ['data[].details', 'data[].remarks'],
    ];

    private array $blocks = [];

    private array $segments = [];

    /**
     * @param  list<array>  $nodes  top-level nodes (id, type, props, version, children)
     */
    public function extract(array $nodes): ProseSnapshot
    {
        $this->blocks = $this->segments = [];
        $roots = array_map(fn (array $node) => $this->visit($node, null, 0), array_values($nodes));

        return new ProseSnapshot($this->blocks, $this->segments, $roots);
    }

    private function visit(array $node, ?string $parent, int $depth): string
    {
        $ref = 'B'.(count($this->blocks) + 1);
        $props = (array) ($node['props'] ?? []);
        $this->blocks[$ref] = [
            'ref' => $ref,
            'id' => $node['id'],
            'type' => $node['type'],
            'parent' => $parent,
            'depth' => $depth,
            'version' => $node['version'] ?? 1,
            'children' => [],
            'node' => ['type' => $node['type'], 'props' => $props, 'options' => (array) ($node['options'] ?? [])],
            'content' => null,
            'truncated' => false,
        ];

        $marked = $props;
        foreach ($this->texts($node['type'], $props) as $path => $text) {
            $segment = 'S'.(count($this->segments) + 1);
            $this->segments[$segment] = ['ref' => $segment, 'block' => $ref, 'field' => explode('.', $path)[0], 'path' => $path, 'text' => $text];
            $marked = PropPath::set($marked, $path, "«{$segment}»");
        }

        if (! in_array($node['type'], self::PLAIN, true) && $props !== []) {
            $content = json_encode($marked, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->blocks[$ref]['truncated'] = mb_strlen($content) > self::MAX_CONTENT;
            $this->blocks[$ref]['content'] = $this->blocks[$ref]['truncated'] ? mb_substr($content, 0, self::MAX_CONTENT).'…' : $content;
        }

        foreach ($node['children'] ?? [] as $child) {
            $this->blocks[$ref]['children'][] = $this->visit($child, $ref, $depth + 1);
        }

        return $ref;
    }

    /**
     * @return array<string, string> path => text
     */
    private function texts(string $type, array $props): array
    {
        $texts = [];

        foreach (self::FIELDS[$type] ?? [] as $rule) {
            [$rule, $unless] = array_pad(explode('!', $rule, 2), 2, null);
            if ($unless !== null && ! empty($props[$unless])) {
                continue;
            }

            $texts += match (true) {
                str_ends_with($rule, '*') => $this->listItems($props[rtrim($rule, '*')] ?? [], rtrim($rule, '*')),
                str_contains($rule, '~') => $this->treeRule($props, ...explode('~', $rule)),
                str_contains($rule, '[].') => $this->gridColumn($props, ...explode('[].', $rule)),
                default => $this->strings([$rule => $props[$rule] ?? null]),
            };
        }

        return array_filter($texts, fn (string $text) => preg_match('/\p{L}/u', strip_tags($text)) === 1);
    }

    private function treeRule(array $props, string $key, string $field): array
    {
        return $this->treeField($props[$key] ?? [], $key, $field);
    }

    private function listItems(mixed $items, string $prefix): array
    {
        $texts = [];
        foreach (is_array($items) ? $items : [] as $i => $item) {
            $texts += match (true) {
                is_string($item) => ["{$prefix}.{$i}" => $item],
                is_array($item) && array_is_list($item) => $this->listItems($item, "{$prefix}.{$i}"),
                is_array($item) && is_array($item['list'] ?? null) => $this->listItems($item['list'], "{$prefix}.{$i}.list"),
                default => [],
            };
        }

        return $texts;
    }

    private function treeField(mixed $nodes, string $prefix, string $field): array
    {
        $texts = [];
        foreach (is_array($nodes) ? $nodes : [] as $i => $node) {
            if (! is_array($node)) {
                continue;
            }
            $texts += $this->strings(["{$prefix}.{$i}.{$field}" => $node[$field] ?? null]);
            $texts += $this->treeField($node['children'] ?? [], "{$prefix}.{$i}.children", $field);
        }

        return $texts;
    }

    private function gridColumn(array $props, string $key, string $column): array
    {
        $texts = [];
        foreach (is_array($props[$key] ?? null) ? $props[$key] : [] as $i => $row) {
            $cell = is_array($row) ? ($row[$column] ?? null) : null;
            $texts += is_array($cell)
                ? $this->strings(array_combine(array_map(fn ($k) => "{$key}.{$i}.{$column}.{$k}", array_keys($cell)), $cell))
                : $this->strings(["{$key}.{$i}.{$column}" => $cell]);
        }

        return $texts;
    }

    /**
     * @return array<string, string> only the string values, trimmed ones dropped
     */
    private function strings(array $values): array
    {
        return array_filter($values, fn ($value) => is_string($value) && trim($value) !== '');
    }
}
