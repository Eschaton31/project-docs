<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * PHP twin of resources/js/builder/fileName.js: 'oh-pipeline-tech-manual_{yyyyMMdd}' → '…_20261021'.
 * Covers the date-fns tokens a file name needs; anything else inside the braces is kept literally.
 */
final class PrintFileName
{
    /** date-fns token → PHP date() character, longest first so 'yyyy' wins over 'yy'. */
    private const TOKENS = [
        'yyyy' => 'Y', 'yy' => 'y',
        'MMMM' => 'F', 'MMM' => 'M', 'MM' => 'm', 'M' => 'n',
        'dd' => 'd', 'd' => 'j',
        'HH' => 'H', 'H' => 'G',
        'mm' => 'i', 'ss' => 's',
    ];

    public static function make(string $pattern, ?Carbon $date = null): string
    {
        $date ??= now();

        $name = preg_replace_callback('/\{([^}]+)}/', fn (array $match) => $date->format(self::toPhpFormat($match[1])), $pattern);

        // keep it a safe file name on every OS
        return trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $name)) ?: 'document';
    }

    private static function toPhpFormat(string $pattern): string
    {
        $format = '';
        for ($i = 0; $i < strlen($pattern);) {
            foreach (self::TOKENS as $token => $php) {
                if (substr($pattern, $i, strlen($token)) === $token) {
                    $format .= $php;
                    $i += strlen($token);

                    continue 2;
                }
            }
            // literal character (escaped so date() doesn't read it as a format code)
            $format .= '\\'.$pattern[$i++];
        }

        return $format;
    }
}
