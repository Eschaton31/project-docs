<?php

namespace App\Ai;

use App\Support\BlockTypes;

/**
 * The block types a restructure may create, read from the JSON the editor's tests keep in step with its forms.
 */
final class BlockCatalogue
{
    private static ?array $spec = null;

    public static function creatable(string $type): bool
    {
        return isset(self::spec()[$type]);
    }

    /**
     * @return list<string>
     */
    public static function props(string $type): array
    {
        return self::spec()[$type]['props'] ?? [];
    }

    /**
     * The catalogue as the model reads it: each type's props and what it may contain.
     */
    public static function describe(): string
    {
        $lines = ['Top level of a document: '.implode(', ', BlockTypes::allowedChildren(null)).'.'];
        foreach (self::spec() as $type => $entry) {
            $children = array_values(array_filter(BlockTypes::allowedChildren($type), self::creatable(...)));
            $lines[] = "- {$type}: props {$entry['shape']}"
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
