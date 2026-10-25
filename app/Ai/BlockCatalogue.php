<?php

namespace App\Ai;

use App\Support\BlockTypes;

/**
 * The block types a restructure or expansion may create, read from the JSON the editor's tests keep in step with
 * its forms. "Data" types (code, diagrams, dictionaries…) hold facts rather than prose: they can be created, or
 * rewritten in place as one block of the same type, but never dropped by a restructure of their neighbours.
 */
final class BlockCatalogue
{
    private static ?array $spec = null;

    public static function creatable(string $type): bool
    {
        return isset(self::spec()[$type]);
    }

    /**
     * Prose a restructure may rewrite into other shapes; anything else it replaces has to be kept (or, for a data
     * block, edited in place).
     */
    public static function rewritable(string $type): bool
    {
        return self::creatable($type) && ! self::isData($type);
    }

    public static function isData(string $type): bool
    {
        return (bool) (self::spec()[$type]['data'] ?? false);
    }

    /**
     * @return list<string>
     */
    public static function props(string $type): array
    {
        return self::spec()[$type]['props'] ?? [];
    }

    /**
     * Props holding plain source (code, diagrams) rather than inline HTML.
     *
     * @return list<string>
     */
    public static function rawProps(string $type): array
    {
        return self::spec()[$type]['raw'] ?? [];
    }

    /**
     * The catalogue as the model reads it: each type's props and what it may contain.
     */
    public static function describe(): string
    {
        $lines = ['Top level of a document: '.implode(', ', BlockTypes::allowedChildren(null)).'.'];
        foreach (self::spec() as $type => $entry) {
            $children = array_values(array_filter(BlockTypes::allowedChildren($type), self::creatable(...)));
            $lines[] = "- {$type}".(self::isData($type) ? ' (data block)' : '').": props {$entry['shape']}"
                .($children ? '; may contain '.implode(', ', $children) : '; no children').'.';
        }

        return implode("\n", $lines);
    }

    private static function spec(): array
    {
        return self::$spec ??= json_decode(
            file_get_contents(resource_path('js/builder/ai-blocks.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
