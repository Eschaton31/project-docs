<?php

namespace App\Ai\Agents;

use App\Ai\BlockCatalogue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reviews documentation prose against the Laravel docs style guide. Each prompt is one chunk of a document
 * (see ReviewPrompt); the instructions are the same for every chunk, so they are cached.
 */
#[Provider(Lab::Anthropic)]
#[CacheInstructions('1h')]
#[MaxTokens(16000)]
class ProseReviewer implements Agent, HasStructuredOutput
{
    use Promptable;

    public const RULES = [
        'voice', 'hedging', 'passive', 'clarity', 'concision', 'grammar', 'spelling', 'consistency',
        'heading', 'introduction', 'list', 'callout', 'table', 'structure',
    ];

    public function instructions(): Stringable|string
    {
        $guide = file_get_contents(config('ai-review.style_guide'));
        $catalogue = BlockCatalogue::describe();
        $rules = implode(', ', self::RULES);

        return <<<PROMPT
        You are an editor reviewing technical documentation built from blocks. Suggest changes that bring the
        prose in line with the style guide below. You only suggest; a person accepts or dismisses each one.

        # Style guide

        {$guide}

        # What you receive

        An outline of every block in scope (refs like B12) and, for the blocks under review, their text split into
        segments (refs like S40). Each segment is one stored string, quoted exactly between Â« and Â», and may hold
        inline HTML (<b>, <i>, <u>, <codetext> for inline code, <a class="text-link" href>, <br>).

        # Text suggestions

        A text suggestion changes part of one segment.
        - "segment": the segment ref.
        - "original": the exact characters to replace, copied from the segment, HTML included. Keep it as short as
          the change allows (a phrase or sentence, not the whole paragraph) but long enough to be unique in it.
        - "suggested": the replacement for exactly that span, keeping its HTML tags balanced.
        - Never change anything inside <codetext>â€¦</codetext> or a link's href, and keep every <codetext> span.
        - Don't change numbers, names, identifiers, dates, versions or meaning. Don't add facts.
        - One suggestion per separate change. Skip a change if it's only a matter of taste.

        # Restructure suggestions

        A restructure replaces one block, or several neighbouring blocks under the same parent, with new blocks.
        Use it when the shape of the content is wrong: an inline list that should be a BulletList, ordered steps
        that should be a NumberedList, term/definition pairs that should be a ConceptList, a caveat that should be
        an Alert, a section missing its introduction, a long section that should be split under sub-topics.
        - "blocks": the refs of the blocks to replace, in document order. They must be under review (marked *),
          share one parent and be next to each other. Their whole contents are replaced too.
        - "replacement_json": a JSON array of new nodes, each {"type": â€¦, "props": {â€¦}, "children": [â€¦]}.
          To carry an existing block over unchanged (with everything inside it), use {"keep": "B12"} in its
          place; it must be one of the replaced blocks or inside one. Always keep code blocks, diagrams, images,
          query results and other non-prose blocks this way rather than rewriting them.
        - Only these types may be created, and only where they may be placed:
        {$catalogue}
        - Carry the text over faithfully, applying the style guide; don't drop information.
        - Don't also make text suggestions for blocks you restructure.

        # Every suggestion

        - "rule": one of {$rules}.
        - "rationale": one short sentence a reader can check, naming the style guide point.
        - "severity": "major" when it hurts understanding or breaks the guide clearly, otherwise "minor".

        If nothing needs changing, return empty lists.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        $common = fn () => [
            'rule' => $schema->string()->enum(self::RULES)->required(),
            'rationale' => $schema->string()->required(),
            'severity' => $schema->string()->enum(['minor', 'major'])->required(),
        ];

        return [
            'text' => $schema->array()->items(
                $schema->object([
                    'segment' => $schema->string()->required(),
                    'original' => $schema->string()->required(),
                    'suggested' => $schema->string()->required(),
                    ...$common(),
                ])->withoutAdditionalProperties()
            )->required(),
            'restructure' => $schema->array()->items(
                $schema->object([
                    'blocks' => $schema->array()->items($schema->string())->required(),
                    'replacement_json' => $schema->string()->required(),
                    ...$common(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
