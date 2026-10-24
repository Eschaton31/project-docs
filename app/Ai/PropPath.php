<?php

namespace App\Ai;

use InvalidArgumentException;

/**
 * Dot paths into a block's props (title, list.1.0, data.3.description.1), the address of one reviewable string.
 */
final class PropPath
{
    public static function get(array $props, string $path): mixed
    {
        $value = $props;
        foreach (explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Replaces the string at $path. Only existing strings can be replaced, so a suggestion can't change a
     * prop's shape (turn a sub-list into an item, add a row).
     */
    public static function set(array $props, string $path, string $value): array
    {
        if (! is_string(self::get($props, $path))) {
            throw new InvalidArgumentException("No text at [{$path}].");
        }

        $target = &$props;
        foreach (explode('.', $path) as $key) {
            $target = &$target[$key];
        }
        $target = $value;

        return $props;
    }
}
