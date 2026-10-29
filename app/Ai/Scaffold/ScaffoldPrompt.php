<?php

namespace App\Ai\Scaffold;

use App\Ai\ProseSnapshot;

/**
 * Splits a scaffold into model calls and writes the prompt for each.
 *
 * The first call drafts every template section except the script reference, from the run notes, the index and
 * as much of the source as fits; then one call per script (or several small ones, or part of a large one) writes
 * its script-reference topic. Every call carries the run notes, the index and the document, so suggestions fit
 * what is already there.
 */
final class ScaffoldPrompt
{
    public const OVERVIEW = 'overview';

    public const SCRIPTS = 'scripts';

    // lines of each other script the overview call sees when not everything fits
    private const HEAD_LINES = 60;

    private const PREVIEW = 80;

    /**
     * @param  list<array{ref: string, name: string, text: string}>  $sources
     * @param  array{runOrder: list<string>}  $index
     * @return list<array{task: string, files: list<array{ref: string, from: int, to: int}>}> files in each call, with the lines it sees
     */
    public static function calls(array $sources, array $index, int $maxChars): array
    {
        $byRef = array_column($sources, null, 'ref');
        $order = array_values(array_filter($index['runOrder'], fn (string $ref) => isset($byRef[$ref])));
        $lines = array_map(fn (array $source) => explode("\n", rtrim($source['text'], "\n")), $byRef);
        $whole = fn (string $ref) => ['ref' => $ref, 'from' => 1, 'to' => count($lines[$ref])];
        $total = array_sum(array_map(fn (array $source) => mb_strlen($source['text']), $sources));

        // overview: everything if it fits, else the first script in full and the start of the others
        if ($total <= $maxChars) {
            $overview = array_map($whole, $order);
        } else {
            $first = array_shift($order);
            $overview = [self::firstPart($lines[$first], $first, $maxChars, $index)];
            foreach ($order as $ref) {
                $overview[] = ['ref' => $ref, 'from' => 1, 'to' => min(self::HEAD_LINES, count($lines[$ref]))];
            }
            array_unshift($order, $first);
        }
        $calls = [['task' => self::OVERVIEW, 'files' => $overview]];

        // script reference: small scripts together, large ones split at their functions
        $group = [];
        $size = 0;
        foreach ($order as $ref) {
            $chars = mb_strlen($byRef[$ref]['text']);
            if ($chars > $maxChars) {
                foreach (self::parts($lines[$ref], $ref, $maxChars, $index) as $part) {
                    $calls[] = ['task' => self::SCRIPTS, 'files' => [$part]];
                }

                continue;
            }
            if ($group && $size + $chars > $maxChars) {
                $calls[] = ['task' => self::SCRIPTS, 'files' => $group];
                [$group, $size] = [[], 0];
            }
            $group[] = $whole($ref);
            $size += $chars;
        }
        if ($group) {
            $calls[] = ['task' => self::SCRIPTS, 'files' => $group];
        }

        return $calls;
    }

    /**
     * @param  ProseSnapshot  $document  the document, or the scope topic, as it is now
     * @param  ?string  $scopeRef  the topic new sections go into; null = the document's top level
     * @param  list<array{ref: string, name: string, text: string, entry?: bool}>  $sources
     * @param  array{task: string, files: list<array{ref: string, from: int, to: int}>}  $call
     */
    public static function render(ProseSnapshot $document, ?string $scopeRef, array $sources, array $index, ?string $runNotes, array $call, int $maxChars): string
    {
        $byRef = array_column($sources, null, 'ref');
        $lines = [...self::task($document, $scopeRef, $call, $byRef), ''];

        $lines[] = '## How the scripts are run';
        $lines[] = trim((string) $runNotes) !== '' ? '«'.trim($runNotes).'»' : 'No run notes were given: describe only what the code shows, and add a Placeholder for the steps.';

        $lines[] = '';
        $lines[] = '## Scripts';
        foreach ($index['files'] as $file) {
            $lines[] = "{$file['ref']} {$file['name']} ({$file['lines']} lines)".($file['entry'] ? ', opened first' : '');
        }
        $lines[] = 'Run order: '.implode(' → ', $index['runOrder']);

        $lines[] = '';
        $lines[] = '## Index';
        $lines[] = json_encode(array_map(fn (array $file) => array_diff_key($file, ['lines' => 0, 'entry' => 0]), $index['files']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $lines[] = '';
        array_push($lines, ...self::document($document, $index, $maxChars));

        $lines[] = '';
        $lines[] = '## Source';
        foreach ($call['files'] as $part) {
            $source = explode("\n", rtrim($byRef[$part['ref']]['text'], "\n"));
            $whole = $part['from'] === 1 && $part['to'] === count($source);
            $lines[] = '';
            $lines[] = "### {$part['ref']} {$byRef[$part['ref']]['name']}".($whole ? '' : " (lines {$part['from']}–{$part['to']} of ".count($source).')');
            $width = strlen((string) $part['to']);
            for ($n = $part['from']; $n <= $part['to']; $n++) {
                $lines[] = str_pad((string) $n, $width, ' ', STR_PAD_LEFT).'| '.$source[$n - 1];
            }
        }

        return implode("\n", $lines);
    }

    private static function task(ProseSnapshot $document, ?string $scopeRef, array $call, array $byRef): array
    {
        $parentType = $scopeRef ? $document->blocks[$scopeRef]['type'] : null;
        $sectionType = ScaffoldTemplate::topicUnder($parentType);
        $scriptType = ScaffoldTemplate::topicUnder($sectionType);
        $children = $scopeRef ? $document->blocks[$scopeRef]['children'] : $document->roots;
        $where = $scopeRef ? "inside {$scopeRef} ({$parentType})" : 'at the top level of the document';
        $existing = $children
            ? 'Its blocks there now, in order: '.implode(', ', array_map(fn (string $ref) => $ref.' '.self::preview($document, $ref, 40), $children)).'.'
            : 'There is nothing there yet.';

        $lines = ['# Task', ''];
        if ($call['task'] === self::OVERVIEW) {
            $lines[] = 'Draft the template\'s sections other than script-reference that the document is missing, and additions to the ones it has.';
            $lines[] = 'Return an empty "script_topics" list.';
        } else {
            $files = implode(', ', array_map(fn (array $part) => "{$part['ref']} {$byRef[$part['ref']]['name']}", $call['files']));
            $partial = array_filter($call['files'], fn (array $part) => $part['from'] > 1 || $part['to'] < count(explode("\n", rtrim($byRef[$part['ref']]['text'], "\n"))));
            $lines[] = "Write the script-reference topic for: {$files}. Return empty \"sections\" and leave \"expand\" for additions to an existing topic about these scripts.";
            if ($partial) {
                $lines[] = 'This call sees only part of the script. Document the functions and code in these lines; another call covers the rest. When the part starts after line 1, add your functions to the script\'s topic with "expand" if the document already has it, otherwise return its topic with only what these lines show.';
            }
            $lines[] = "A script's topic is a {$scriptType}.";
        }
        $lines[] = "New sections go {$where}, as {$sectionType} nodes. {$existing}";

        return $lines;
    }

    /**
     * The document's outline and text. When it's too large to send in full alongside the code, only the topics
     * that name a script, function or file the index found carry their text.
     */
    private static function document(ProseSnapshot $document, array $index, int $maxChars): array
    {
        if ($document->blocks === []) {
            return ['## Document', 'The document is empty.'];
        }

        $full = $document->totalSize() <= $maxChars;
        $wanted = $full ? array_keys($document->blocks) : self::relevant($document, $index);
        $wantedSet = array_flip($wanted);

        $lines = ['## Document', $full ? 'Every block, with its text.' : 'Every block; only the topics that mention these scripts carry their text.'];
        foreach ($document->blocks as $ref => $block) {
            $preview = self::preview($document, $ref, self::PREVIEW);
            $lines[] = str_repeat('  ', $block['depth'])."{$ref} {$block['type']}".($preview !== '' ? " — {$preview}" : '');
            if (! isset($wantedSet[$ref])) {
                continue;
            }
            foreach ($document->segmentsOf([$ref]) as $segment) {
                $lines[] = str_repeat('  ', $block['depth'] + 1)."[{$segment['path']}]: «{$segment['text']}»";
            }
            if ($block['content'] !== null) {
                $lines[] = str_repeat('  ', $block['depth'] + 1)."content: {$block['content']}";
            }
        }

        return $lines;
    }

    /**
     * @return list<string> refs of every block in a top-level topic whose text names a script, function or file
     */
    private static function relevant(ProseSnapshot $document, array $index): array
    {
        $names = [];
        foreach ($index['files'] as $file) {
            $names[] = basename($file['name']);
            array_push($names, ...array_column($file['functions'], 'name'));
            array_push($names, ...array_filter(array_column([...$file['reads'], ...$file['writes']], 'target')));
        }
        $names = array_filter(array_unique($names), fn ($name) => mb_strlen((string) $name) >= 3);

        $refs = [];
        foreach ($document->roots as $root) {
            $subtree = $document->subtreeRefs($root);
            $text = implode(' ', array_column($document->segmentsOf($subtree), 'text'));
            foreach ($names as $name) {
                if (str_contains($text, $name)) {
                    array_push($refs, ...$subtree);
                    break;
                }
            }
        }

        return $refs;
    }

    private static function preview(ProseSnapshot $document, string $ref, int $length): string
    {
        $text = implode(' ', array_column($document->segmentsOf([$ref]), 'text'));
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text))));

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }

    /**
     * The start of a script, up to the limit, cut at a function where it can be.
     */
    private static function firstPart(array $lines, string $ref, int $maxChars, array $index): array
    {
        return self::parts($lines, $ref, $maxChars, $index)[0];
    }

    /**
     * A large script in line ranges of at most $maxChars, each cut just before a function starts where possible.
     *
     * @return list<array{ref: string, from: int, to: int}>
     */
    private static function parts(array $lines, string $ref, int $maxChars, array $index): array
    {
        $file = array_values(array_filter($index['files'], fn (array $file) => $file['ref'] === $ref))[0] ?? ['functions' => []];
        $starts = array_flip(array_column($file['functions'], 'from'));

        $parts = [];
        $from = 1;
        $size = 0;
        $lastStart = null;
        foreach ($lines as $i => $line) {
            $n = $i + 1;
            if (isset($starts[$n]) && $n > $from) {
                $lastStart = $n;
            }
            $size += mb_strlen($line) + 1;
            if ($size > $maxChars && $n > $from) {
                // cut before the last function that started in this part, or here
                $cut = $lastStart && $lastStart > $from ? $lastStart : $n;
                $parts[] = ['ref' => $ref, 'from' => $from, 'to' => $cut - 1];
                $from = $cut;
                $size = array_sum(array_map(fn ($each) => mb_strlen($each) + 1, array_slice($lines, $from - 1, $n - $from + 1)));
                $lastStart = null;
            }
        }
        $parts[] = ['ref' => $ref, 'from' => $from, 'to' => count($lines)];

        return $parts;
    }
}
