<?php

namespace App\Console\Commands;

use App\Ai\Agents\ProseReviewer;
use App\Ai\ProseExtractor;
use App\Ai\ReviewPrompt;
use App\Ai\ReviewRun;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\User;
use App\Services\DocumentTree;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * First half of a review run in Claude Code: saves what is being reviewed and writes the instructions and one
 * prompt per chunk for Claude Code to answer (see .claude/skills/prose-review). prose-review:import reads the
 * answers back.
 */
#[Signature('prose-review:export
    {document : Slug of the document to review}
    {--block= : Review only this block (usually a topic) and everything inside it}
    {--kinds=text,restructure : What to suggest, comma separated: text (wording), restructure (block structure), expand (add missing content)}
    {--brief-file= : A text file with the admin\'s brief: what to cover when expanding, and facts to use}
    {--user= : Email of the admin the review is for}')]
#[Description('Prepare an AI prose review for Claude Code to run')]
class ExportProseReview extends Command
{
    public function handle(DocumentTree $tree): int
    {
        $document = Document::query()->where('slug', $this->argument('document'))->first();
        if (! $document) {
            return $this->fail("No document with the slug [{$this->argument('document')}].");
        }

        $blockId = $this->option('block');
        if ($blockId && ! $document->blocks()->whereKey($blockId)->exists()) {
            return $this->fail("Block [{$blockId}] isn't in this document.");
        }

        $kinds = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('kinds'))))));
        if ($kinds === [] || array_diff($kinds, ProseSuggestion::KINDS)) {
            return $this->fail('--kinds takes any of '.implode(', ', ProseSuggestion::KINDS).' (comma separated).');
        }

        $briefFile = $this->option('brief-file');
        if ($briefFile && ! File::isFile($briefFile)) {
            return $this->fail("No brief file at [{$briefFile}].");
        }
        $brief = $briefFile ? trim(File::get($briefFile)) ?: null : null;
        if ($brief !== null && mb_strlen($brief) > ProseReview::MAX_BRIEF) {
            return $this->fail('The brief is longer than '.ProseReview::MAX_BRIEF.' characters.');
        }

        $user = $this->option('user') ? User::query()->where('email', $this->option('user'))->first() : null;
        if ($this->option('user') && ! $user?->isAdmin()) {
            return $this->fail("[{$this->option('user')}] isn't an admin.");
        }

        $review = $document->proseReviews()->make([
            'scope_block_id' => $blockId,
            'requested_by' => $user?->id,
            'model' => ProseReview::CLAUDE_CODE,
            'kinds' => $kinds,
            'brief' => $brief,
            'status' => ProseReview::AWAITING,
        ]);
        $nodes = ReviewRun::read($review, $tree);
        $snapshot = (new ProseExtractor)->extract($nodes);
        if ($snapshot->totalSize() === 0) {
            return $this->fail('There is no text to review here.');
        }
        $chunks = ReviewPrompt::chunks($snapshot, config('ai-review.max_chars_per_chunk'));

        $review->snapshot = ['nodes' => $nodes, 'chunks' => $chunks];
        $review->save();
        ReviewRun::broadcast($review);

        $dir = self::directory($review);
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/instructions.md", self::instructions());
        foreach ($chunks as $i => $chunk) {
            $n = $i + 1;
            $answer = self::answerPath($review, $n);
            File::put("{$dir}/chunk-{$n}.md", "# Chunk {$n} of ".count($chunks)."\n\nWrite your answer to: {$answer}\n\n".ReviewPrompt::render($snapshot, $chunk, $kinds, $brief)."\n");
        }

        $this->line("Review: {$review->id}");
        $this->line('Folder: '.str_replace('\\', '/', $dir));
        $this->line('Instructions: '.str_replace('\\', '/', "{$dir}/instructions.md"));
        foreach (array_keys($chunks) as $i) {
            $n = $i + 1;
            $this->line("Chunk {$n}: ".str_replace('\\', '/', "{$dir}/chunk-{$n}.md").' -> '.self::answerPath($review, $n));
        }
        $this->line("When every answer is written: php artisan prose-review:import {$review->id}");

        return self::SUCCESS;
    }

    public static function directory(ProseReview $review): string
    {
        return rtrim(config('ai-review.work_dir'), '/\\').DIRECTORY_SEPARATOR.$review->id;
    }

    public static function answerPath(ProseReview $review, int $chunk): string
    {
        return str_replace('\\', '/', self::directory($review)."/chunk-{$chunk}.json");
    }

    /**
     * The reviewer's instructions (the same the API path sends) and how to hand the answer back as a file.
     */
    private static function instructions(): string
    {
        $rules = implode(', ', ProseReviewer::RULES);

        return (new ProseReviewer)->instructions()."\n\n".<<<MD
        # How to answer

        Each chunk file says where to write its answer. Write one JSON object to that file and nothing else
        (no Markdown fence, no comments):

        {
          "text": [
            {"segment": "S2", "original": "In order to install it", "suggested": "To install it",
             "rule": "hedging", "rationale": "The style guide cuts \"in order to\".", "severity": "minor"}
          ],
          "restructure": [
            {"blocks": ["B4"],
             "replacement_json": "[{\"type\": \"BulletList\", \"props\": {\"list\": [\"PHP\", \"A database\"]}}]",
             "rule": "list", "rationale": "Parallel items read better as a list.", "severity": "major"},
            {"blocks": ["B9"],
             "replacement_json": "[{\"type\": \"Mermaid\", \"props\": {\"code\": \"flowchart LR\\\\n  A[Upload] --> B[Validate] --> C[Publish]\"}}]",
             "rule": "diagram", "rationale": "The diagram skips the validation step the text describes.", "severity": "major"}
          ],
          "expand": [
            {"anchor": "B1", "position": "start",
             "blocks_json": "[{\"type\": \"Paragraph\", \"props\": {}, \"children\": [{\"type\": \"RichText\", \"props\": {\"html\": \"Before you start, make sure you have the following installed.\"}}]}]",
             "sources": ["document"], "rule": "introduction",
             "rationale": "The section opens with a list and no introduction.", "severity": "minor"}
          ]
        }

        - All three lists are required; use [] when there is nothing to suggest.
        - "replacement_json" and "blocks_json" are strings that contain JSON (escape their quotes).
        - "rule" is one of: {$rules}. "severity" is "minor" or "major".
        - Copy "original" from the segment exactly, HTML included; a suggestion whose original isn't found
          exactly once in its segment is discarded when the answers are imported.
        MD;
    }
}
