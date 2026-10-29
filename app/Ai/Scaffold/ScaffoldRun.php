<?php

namespace App\Ai\Scaffold;

use App\Ai\ProseExtractor;
use App\Ai\ProseSnapshot;
use App\Models\ProseReview;
use App\Models\ProseReviewSource;
use App\Models\ProseSuggestion;
use App\Services\DocumentTree;
use Illuminate\Validation\ValidationException;

/**
 * The steps every scaffold shares, whether the model is called from the queue or the scaffold runs in Claude
 * Code (exported, then imported): read the document and the scripts, split the work into calls, validate and
 * save the answers.
 */
final class ScaffoldRun
{
    /**
     * The whole document as it is now (recording the seq it was read at), the scripts, their index and the calls.
     *
     * @return array{nodes: list<array>, document: ProseSnapshot, scopeRef: ?string, sources: list<array>, index: array, calls: list<array>}
     */
    public static function prepare(ProseReview $review, DocumentTree $tree): array
    {
        $review->document_seq = $review->document->seq;
        $review->save();

        return self::from($review, $tree->build($review->document));
    }

    /**
     * The same, from a block tree saved at export (calls too, when given), so answers are read against exactly
     * what was exported.
     *
     * @param  list<array>  $nodes
     */
    public static function from(ProseReview $review, array $nodes, ?array $calls = null): array
    {
        $document = (new ProseExtractor)->extract($nodes);
        $sources = $review->sources->map(fn (ProseReviewSource $source) => [
            'ref' => $source->ref,
            'name' => $source->name,
            'text' => $source->text(),
            'entry' => $source->entry,
        ])->all();
        $index = RScriptIndex::build($sources);

        return [
            'nodes' => $nodes,
            'document' => $document,
            'scopeRef' => $review->scope_block_id ? $document->refFor($review->scope_block_id) : null,
            'sources' => $sources,
            'index' => $index,
            'calls' => $calls ?? ScaffoldPrompt::calls($sources, $index, config('ai-review.max_chars_per_chunk')),
        ];
    }

    /**
     * Refuses scripts that would take more model calls than one scaffold job may make before it times out.
     *
     * @param  list<array{name: string, text: string}>  $prepared  as SourceIntake::prepare() returns them
     *
     * @throws ValidationException
     */
    public static function assertFits(array $prepared): void
    {
        $sources = array_map(fn (array $file, int $i) => ['ref' => 'F'.($i + 1), 'name' => $file['name'], 'text' => $file['text']], $prepared, array_keys($prepared));
        $calls = count(ScaffoldPrompt::calls($sources, RScriptIndex::build($sources), config('ai-review.max_chars_per_chunk')));
        $max = config('ai-review.scaffold.max_calls');

        if ($calls > $max) {
            throw ValidationException::withMessages(['files' => "These scripts would take {$calls} model calls to document; the most is {$max}. Upload the main scripts, or draft them in two goes."]);
        }
    }

    public static function prompt(array $prepared, array $call, ?string $runNotes): string
    {
        return ScaffoldPrompt::render($prepared['document'], $prepared['scopeRef'], $prepared['sources'], $prepared['index'], $runNotes, $call, config('ai-review.max_chars_per_chunk'));
    }

    /**
     * Validates every call's answer (in call order) and saves the suggestions that pass, in the template's order.
     *
     * @param  list<array>  $outputs
     * @return array<string, int> why the others were dropped
     */
    public static function save(ProseReview $review, array $prepared, array $outputs): array
    {
        $validator = new ScaffoldValidator($prepared['document'], $prepared['scopeRef'], $prepared['sources'], $prepared['index']);
        $rows = [];
        foreach ($outputs as $output) {
            array_push($rows, ...$validator->validate($output));
        }
        if ($reference = $validator->scriptReference()) {
            $rows[] = $reference;
        }

        // stable: suggestions for the same section keep the order they came in
        usort($rows, fn (array $a, array $b) => ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX));
        foreach ($rows as $row) {
            $review->suggestions()->create([...$row, 'status' => ProseSuggestion::PENDING]);
        }

        return $validator->dropped();
    }
}
