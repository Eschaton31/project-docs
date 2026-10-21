<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Base-62 fractional indexing (port of the midpoint algorithm from the `fractional-indexing` npm package).
 *
 * Keys sort byte-wise, so a block can be inserted or moved between two siblings by writing a single
 * row, and two people reordering different parts of a list never renumber each other's blocks.
 */
final class FractionalIndex
{
    private const DIGITS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * A key strictly between $a and $b; null means "no bound" on that side.
     */
    public static function between(?string $a, ?string $b): string
    {
        if ($a !== null && $b !== null && strcmp($a, $b) >= 0) {
            throw new InvalidArgumentException("Position [{$a}] must sort before [{$b}].");
        }

        return self::midpoint($a ?? '', $b);
    }

    /**
     * $n ascending keys between $a and $b, spread evenly so they stay short.
     *
     * @return list<string>
     */
    public static function nBetween(?string $a, ?string $b, int $n): array
    {
        if ($n <= 0) {
            return [];
        }

        if ($n === 1) {
            return [self::between($a, $b)];
        }

        $mid = intdiv($n, 2);
        $c = self::between($a, $b);

        return [...self::nBetween($a, $c, $mid), $c, ...self::nBetween($c, $b, $n - $mid - 1)];
    }

    private static function midpoint(string $a, ?string $b): string
    {
        $zero = self::DIGITS[0];

        if ($b !== null) {
            // skip the shared prefix, treating $a as padded with zeros
            $n = 0;
            while (($a[$n] ?? $zero) === ($b[$n] ?? null)) {
                $n++;
            }

            if ($n > 0) {
                return substr($b, 0, $n).self::midpoint(substr($a, $n), substr($b, $n));
            }
        }

        $digitA = $a !== '' ? strpos(self::DIGITS, $a[0]) : 0;
        $digitB = $b !== null ? strpos(self::DIGITS, $b[0]) : strlen(self::DIGITS);

        if ($digitB - $digitA > 1) {
            return self::DIGITS[(int) round(($digitA + $digitB) / 2)];
        }

        if ($b !== null && strlen($b) > 1) {
            return substr($b, 0, 1);
        }

        return self::DIGITS[$digitA].self::midpoint(substr($a, 1), null);
    }
}
