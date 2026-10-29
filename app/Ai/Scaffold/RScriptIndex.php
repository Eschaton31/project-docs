<?php

namespace App\Ai\Scaffold;

/**
 * A light read of R scripts as text: the packages they load, the scripts they source(), the functions they
 * define, the files and tables they read and write, and the messages they stop with. It doesn't judge whether
 * the code works; the facts point the model at the right lines and let the validator check what it writes.
 * Strings and comments are skipped when looking for calls, so "# library(foo)" isn't a package.
 */
final class RScriptIndex
{
    private const READS = [
        'read.csv', 'read.csv2', 'read.table', 'read.delim', 'readLines', 'readRDS', 'load', 'fread', 'read_csv',
        'read_csv2', 'read_tsv', 'read_delim', 'read_excel', 'read_xlsx', 'read_xls', 'read.xlsx', 'read_sav',
        'read_dta', 'read_sas', 'read_parquet', 'read_feather', 'fromJSON', 'read_json', 'dbGetQuery',
        'dbReadTable', 'dbSendQuery', 'sqlQuery', 'list.files',
    ];

    private const WRITES = [
        'write.csv', 'write.csv2', 'write.table', 'writeLines', 'saveRDS', 'save', 'fwrite', 'write_csv',
        'write_csv2', 'write_tsv', 'write_delim', 'write_xlsx', 'write.xlsx', 'saveWorkbook', 'write_parquet',
        'write_feather', 'write_json', 'ggsave', 'png', 'pdf', 'dbWriteTable', 'dbAppendTable', 'dbExecute',
        'sqlSave', 'render', 'sink',
    ];

    private const MESSAGES = ['stop', 'warning'];

    private const TARGET_LENGTH = 200;

    /**
     * @param  list<array{ref: string, name: string, text: string, entry?: bool}>  $files
     * @return array{files: list<array>, packages: list<string>, runOrder: list<string>}
     */
    public static function build(array $files): array
    {
        $indexed = array_map(fn (array $file) => self::file($file), $files);
        $indexed = self::resolveSources($indexed);

        $packages = array_values(array_unique(array_merge([], ...array_column($indexed, 'packages'))));
        sort($packages, SORT_STRING | SORT_FLAG_CASE);

        return ['files' => $indexed, 'packages' => $packages, 'runOrder' => self::runOrder($indexed)];
    }

    /**
     * @param  array{ref: string, name: string, text: string, entry?: bool}  $file
     */
    private static function file(array $file): array
    {
        $text = $file['text'];
        [$masked, $strings] = self::mask($text);
        $lineAt = self::lineLookup($text);

        $calls = [];
        // every call: name, where its "(" is and where its ")" is
        preg_match_all('/(?<![\w.$@])((?:[A-Za-z][\w.]*:::?)?[A-Za-z.][\w.]*)\s*\(/', $masked, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $i => [$name, $offset]) {
            $open = $matches[0][$i][1] + strlen($matches[0][$i][0]) - 1;
            $calls[] = ['name' => $name, 'offset' => $offset, 'open' => $open, 'close' => self::closing($masked, $open, '(', ')')];
        }

        $index = [
            'ref' => $file['ref'],
            'name' => $file['name'],
            'entry' => (bool) ($file['entry'] ?? false),
            'lines' => substr_count($text, "\n") + (str_ends_with($text, "\n") ? 0 : 1),
            'packages' => [],
            'sources' => [],
            'functions' => self::functions($text, $masked, $lineAt),
            'reads' => [],
            'writes' => [],
            'messages' => [],
        ];

        foreach ($calls as $call) {
            $name = preg_replace('/^[\w.]+:::?/', '', $call['name']);
            $literals = self::literalsIn($strings, $call['open'], $call['close']);
            $line = $lineAt($call['offset']);

            if (in_array($name, ['library', 'require', 'requireNamespace'], true)) {
                $index['packages'] = [...$index['packages'], ...self::loaded($masked, $call, $strings)];
            } elseif ($name === 'p_load') {
                $index['packages'] = [...$index['packages'], 'pacman', ...self::argumentNames($masked, $call, $strings)];
            } elseif (in_array($name, ['source', 'sys.source'], true) && $literals) {
                $index['sources'][] = ['line' => $line, 'path' => end($literals), 'file' => null];
            } elseif (in_array($name, self::READS, true)) {
                $index['reads'][] = ['line' => $line, 'call' => $name, 'target' => self::target($literals)];
            } elseif (in_array($name, self::WRITES, true)) {
                $index['writes'][] = ['line' => $line, 'call' => $name, 'target' => self::target($literals)];
            } elseif (in_array($name, self::MESSAGES, true) && $literals) {
                $index['messages'][] = ['line' => $line, 'kind' => $name, 'text' => implode('…', $literals)];
            }
        }

        // pkg::fn and pkg:::fn
        preg_match_all('/(?<![\w.])([A-Za-z][\w.]*[A-Za-z0-9]|[A-Za-z]):::?(?=[A-Za-z.`])/', $masked, $namespaced);
        $index['packages'] = array_values(array_unique([...$index['packages'], ...$namespaced[1]]));

        return $index;
    }

    /**
     * The text with comments and string contents blanked out (same length, newlines kept), and every string
     * literal by offset.
     *
     * @return array{0: string, 1: list<array{start: int, end: int, value: string}>}
     */
    private static function mask(string $text): array
    {
        $masked = $text;
        $strings = [];
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === '#') {
                while ($i < $length && $text[$i] !== "\n") {
                    $masked[$i++] = ' ';
                }
            } elseif ($char === '"' || $char === "'") {
                $start = $i;
                $value = '';
                for ($i++; $i < $length && $text[$i] !== $char; $i++) {
                    if ($text[$i] === '\\' && $i + 1 < $length) {
                        $value .= $text[$i].$text[$i + 1];
                        $masked[$i] = ' ';
                        if ($text[++$i] !== "\n") {
                            $masked[$i] = ' ';
                        }

                        continue;
                    }
                    $value .= $text[$i];
                    if ($text[$i] !== "\n") {
                        $masked[$i] = ' ';
                    }
                }
                $strings[] = ['start' => $start, 'end' => $i, 'value' => stripcslashes($value)];
            } elseif ($char === '`') {
                for ($i++; $i < $length && $text[$i] !== '`'; $i++);
            }
        }

        return [$masked, $strings];
    }

    /**
     * @return \Closure(int): int byte offset => 1-based line
     */
    private static function lineLookup(string $text): \Closure
    {
        preg_match_all('/\n/', $text, $newlines, PREG_OFFSET_CAPTURE);
        $starts = array_column($newlines[0], 1);

        return function (int $offset) use ($starts): int {
            [$low, $high] = [0, count($starts)];
            while ($low < $high) {
                $mid = intdiv($low + $high, 2);
                $starts[$mid] < $offset ? $low = $mid + 1 : $high = $mid;
            }

            return $low + 1;
        };
    }

    private static function closing(string $masked, int $open, string $left, string $right): int
    {
        $depth = 0;
        $length = strlen($masked);
        for ($i = $open; $i < $length; $i++) {
            if ($masked[$i] === $left) {
                $depth++;
            } elseif ($masked[$i] === $right && --$depth === 0) {
                return $i;
            }
        }

        return $length - 1;
    }

    /**
     * name <- function(args) and name = function(args), with the line range of the body and any #' comments above.
     */
    private static function functions(string $text, string $masked, \Closure $lineAt): array
    {
        $lines = explode("\n", $text);
        $functions = [];

        preg_match_all('/(?<![\w.])([A-Za-z.][\w.]*|`[^`]+`)\s*(?:<<-|<-|=)\s*function\s*\(/', $masked, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => [$whole, $offset]) {
            $open = $offset + strlen($whole) - 1;
            $close = self::closing($masked, $open, '(', ')');
            $args = trim(preg_replace('/\s+/', ' ', substr($text, $open + 1, $close - $open - 1)));

            $end = $close;
            $rest = substr($masked, $close + 1);
            if (preg_match('/^\s*\{/', $rest, $brace)) {
                $end = self::closing($masked, $close + strlen($brace[0]), '{', '}');
            } elseif (preg_match('/^[^\n]*/', $rest, $line)) {
                $end = $close + strlen($line[0]);
            }

            $from = $lineAt($offset);
            $doc = [];
            for ($l = $from - 2; $l >= 0 && preg_match("/^\s*#'\s?(.*)$/", $lines[$l], $comment); $l--) {
                array_unshift($doc, rtrim($comment[1]));
            }

            $functions[] = [
                'name' => trim($matches[1][$i][0], '`'),
                'args' => $args,
                'from' => $from,
                'to' => $lineAt($end),
                'doc' => trim(implode("\n", $doc)),
            ];
        }

        return $functions;
    }

    /**
     * library(dplyr), library("dplyr"), requireNamespace("x"); not library(pkg, character.only = TRUE).
     */
    private static function loaded(string $masked, array $call, array $strings): array
    {
        $inside = substr($masked, $call['open'] + 1, $call['close'] - $call['open'] - 1);
        if (preg_match('/character\.only\s*=\s*(TRUE|T)\b/', $inside)) {
            return [];
        }
        if (preg_match('/^\s*([A-Za-z][\w.]*)\s*(?:,|$)/', $inside, $bare)) {
            return [$bare[1]];
        }
        $literals = self::literalsIn($strings, $call['open'], $call['close']);

        return $literals && preg_match('/^[A-Za-z][\w.]*$/', $literals[0]) ? [$literals[0]] : [];
    }

    /**
     * Every unnamed argument of p_load(a, b, "c"), bare or quoted.
     */
    private static function argumentNames(string $masked, array $call, array $strings): array
    {
        $inside = substr($masked, $call['open'] + 1, $call['close'] - $call['open'] - 1);
        preg_match_all('/(?:^|,)\s*([A-Za-z][\w.]*)\s*(?=,|$)/', $inside, $bare);
        $quoted = array_filter(self::literalsIn($strings, $call['open'], $call['close']), fn ($value) => preg_match('/^[A-Za-z][\w.]*$/', $value));

        return array_values(array_unique([...$bare[1], ...$quoted]));
    }

    /**
     * @return list<string> string literals between two offsets
     */
    private static function literalsIn(array $strings, int $from, int $to): array
    {
        return array_values(array_map(
            fn (array $string) => $string['value'],
            array_filter($strings, fn (array $string) => $string['start'] > $from && $string['end'] < $to),
        ));
    }

    private static function target(array $literals): ?string
    {
        if ($literals === []) {
            return null;
        }
        $target = trim(preg_replace('/\s+/', ' ', $literals[0]));

        return mb_strlen($target) > self::TARGET_LENGTH ? mb_substr($target, 0, self::TARGET_LENGTH - 1).'…' : $target;
    }

    /**
     * Links each source("…") to the uploaded script it names: the same path, a path ending in it, or a unique
     * file name.
     */
    private static function resolveSources(array $files): array
    {
        foreach ($files as &$file) {
            foreach ($file['sources'] as &$source) {
                $path = ltrim(str_replace('\\', '/', $source['path']), './');
                $match = array_values(array_filter($files, fn (array $other) => $other['name'] === $path || str_ends_with($other['name'], "/{$path}") || str_ends_with($path, "/{$other['name']}")));
                if (! $match) {
                    $match = array_values(array_filter($files, fn (array $other) => basename($other['name']) === basename($path)));
                }
                $source['file'] = count($match) === 1 ? $match[0]['ref'] : null;
            }
        }

        return $files;
    }

    /**
     * The script opened first (or every script nothing else sources), each followed by what it sources in the
     * order it does; scripts that nothing reaches come last.
     */
    private static function runOrder(array $files): array
    {
        $byRef = array_column($files, null, 'ref');
        $sourced = array_filter(array_merge([], ...array_map(fn ($file) => array_column($file['sources'], 'file'), $files)));
        $starts = array_column(array_filter($files, fn ($file) => $file['entry']), 'ref')
            ?: array_column(array_filter($files, fn ($file) => ! in_array($file['ref'], $sourced, true)), 'ref');

        $order = [];
        $visit = function (string $ref) use (&$visit, &$order, $byRef) {
            if (in_array($ref, $order, true)) {
                return;
            }
            $order[] = $ref;
            foreach ($byRef[$ref]['sources'] as $source) {
                if ($source['file']) {
                    $visit($source['file']);
                }
            }
        };
        foreach ($starts as $ref) {
            $visit($ref);
        }

        return [...$order, ...array_values(array_diff(array_keys($byRef), $order))];
    }
}
