<?php

namespace App\Ai\Scaffold;

/**
 * The documentation template a scaffold follows (config ai-review.scaffold.template): its text for the model, and
 * its sections in order, read from the "## key: TITLE" headings.
 */
final class ScaffoldTemplate
{
    // drafted per script and wrapped by the app, not suggested by the overview call
    public const SCRIPT_REFERENCE = 'script-reference';

    // the topic ladder: what a section is under each parent (null = the document root)
    private const TOPIC_UNDER = [
        '' => 'PrimaryTopic',
        'PrimaryTopic' => 'SecondaryTopic',
        'SecondaryTopic' => 'TertiaryTopic',
        'TertiaryTopic' => 'QuaternaryTopic',
    ];

    /**
     * The topic type a new section is under a parent of this type; null when sections can't go there.
     */
    public static function topicUnder(?string $parentType): ?string
    {
        return self::TOPIC_UNDER[$parentType ?? ''] ?? null;
    }

    public static function text(): string
    {
        return file_get_contents(config('ai-review.scaffold.template'));
    }

    /**
     * @return array<string, string> key => title, in the template's order
     */
    public static function sections(): array
    {
        preg_match_all('/^## ([a-z0-9-]+): (.+)$/m', self::text(), $matches);

        return array_combine($matches[1], array_map('trim', $matches[2]));
    }

    public static function order(string $key): ?int
    {
        $index = array_search($key, array_keys(self::sections()), true);

        return $index === false ? null : $index;
    }
}
