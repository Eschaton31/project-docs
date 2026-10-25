<?php

namespace App\Ai;

use App\Models\ProseSuggestion;

/**
 * Splits a snapshot into model-sized chunks and writes the prompt for each.
 *
 * Every prompt carries the whole outline (types, nesting and a short preview) so the model sees where the
 * chunk sits, but only the chunk's blocks carry their full text, and only those can be changed.
 */
final class ReviewPrompt
{
    private const PREVIEW = 80;

    /**
     * @return list<list<string>> each chunk's block refs, in document order
     */
    public static function chunks(ProseSnapshot $snapshot, int $maxChars): array
    {
        $chunks = [];
        $current = [];
        $size = 0;

        $add = function (array $refs, int $chars) use (&$chunks, &$current, &$size, $maxChars) {
            if ($current && $size + $chars > $maxChars) {
                $chunks[] = $current;
                [$current, $size] = [[], 0];
            }
            array_push($current, ...$refs);
            $size += $chars;
        };

        $pack = function (array $refs) use (&$pack, $add, $snapshot, $maxChars) {
            foreach ($refs as $ref) {
                $children = $snapshot->blocks[$ref]['children'];
                if ($children && $snapshot->size($ref) > $maxChars) {
                    $add([$ref], $snapshot->sizeOf([$ref]));
                    $pack($children);
                } else {
                    $add($snapshot->subtreeRefs($ref), $snapshot->size($ref));
                }
            }
        };

        $pack($snapshot->roots);
        if ($current) {
            $chunks[] = $current;
        }

        return array_values(array_filter($chunks, fn (array $chunk) => $snapshot->sizeOf($chunk) > 0));
    }

    /**
     * @param  list<string>  $chunk  block refs whose text is under review
     * @param  list<string>  $kinds  text, restructure, expand
     * @param  ?string  $brief  what the admin wants covered, quoted as given
     */
    public static function render(ProseSnapshot $snapshot, array $chunk, array $kinds, ?string $brief = null): string
    {
        $inChunk = array_flip($chunk);
        $lines = ['Suggestion kinds requested: '.implode(', ', $kinds).'.'];
        foreach (ProseSuggestion::KINDS as $kind) {
            if (! in_array($kind, $kinds, true)) {
                $lines[] = "Return an empty \"{$kind}\" list.";
            }
        }

        if (trim((string) $brief) !== '') {
            $lines[] = '';
            $lines[] = '## Brief';
            $lines[] = 'From the person who asked for this review, between « and ». Use it as a source when expanding.';
            $lines[] = '«'.trim($brief).'»';
        }

        $lines[] = '';
        $lines[] = '## Document outline';
        $lines[] = 'Every block in scope, for context. Blocks marked * are under review in this request.';
        foreach ($snapshot->blocks as $ref => $block) {
            $preview = self::preview($snapshot, $ref);
            $lines[] = str_repeat('  ', $block['depth']).(isset($inChunk[$ref]) ? '* ' : '').$ref.' '.$block['type'].($preview !== '' ? " — {$preview}" : '');
        }

        $lines[] = '';
        $lines[] = '## Blocks under review';
        $lines[] = 'Text is quoted exactly as stored, between « and ». A structured block also shows its "content": all of';
        $lines[] = 'its props as JSON, with «S12» standing for that segment\'s text. Content cut short (ending in …) is';
        $lines[] = 'incomplete, so that block can only be kept, never rewritten.';
        foreach ($chunk as $ref) {
            $block = $snapshot->blocks[$ref];
            $parent = $block['parent'] ? " (in {$block['parent']})" : ' (top level)';
            $lines[] = '';
            $lines[] = "{$ref} {$block['type']}{$parent}".self::flags($block['node']['props']);
            foreach ($snapshot->segmentsOf([$ref]) as $segment) {
                $lines[] = "  {$segment['ref']} [{$segment['path']}]: «{$segment['text']}»";
            }
            if ($block['content'] !== null) {
                $lines[] = "  content: {$block['content']}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The block's text or, for one without any (a diagram, unlabelled code), the start of its source.
     */
    private static function preview(ProseSnapshot $snapshot, string $ref): string
    {
        $text = implode(' ', array_column($snapshot->segmentsOf([$ref]), 'text'));
        $code = $snapshot->blocks[$ref]['node']['props']['code'] ?? null;
        if ($text === '' && is_string($code)) {
            $text = htmlspecialchars($code);
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text))));

        return mb_strlen($text) > self::PREVIEW ? mb_substr($text, 0, self::PREVIEW - 1).'…' : $text;
    }

    /**
     * Non-text props that matter when rewriting a block (an Alert's style, a list's bold), so a restructure keeps them.
     */
    private static function flags(array $props): string
    {
        $flags = array_filter(
            array_intersect_key($props, array_flip(['type', 'bold', 'mono', 'indent', 'emDash', 'highlight', 'lang'])),
            fn ($value) => is_scalar($value) && $value !== '' && $value !== false,
        );

        return $flags ? ' '.json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    }
}
