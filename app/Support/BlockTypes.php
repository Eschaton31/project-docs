<?php

namespace App\Support;

/**
 * Block types and nesting rules, read from the same JSON file the Vue registry imports.
 */
final class BlockTypes
{
    private static ?array $spec = null;

    public static function exists(string $type): bool
    {
        return isset(self::spec()['types'][$type]);
    }

    /**
     * Types allowed directly under $parentType; null means the document root.
     *
     * @return list<string>
     */
    public static function allowedChildren(?string $parentType): array
    {
        $spec = self::spec();
        $entries = $parentType === null ? $spec['root'] : ($spec['types'][$parentType]['children'] ?? []);

        $allowed = [];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, '@')) {
                array_push($allowed, ...$spec['groups'][substr($entry, 1)]);
            } else {
                $allowed[] = $entry;
            }
        }

        return array_values(array_unique($allowed));
    }

    public static function canContain(?string $parentType, string $childType): bool
    {
        return in_array($childType, self::allowedChildren($parentType), true);
    }

    private static function spec(): array
    {
        return self::$spec ??= json_decode(
            file_get_contents(resource_path('js/builder/block-types.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
