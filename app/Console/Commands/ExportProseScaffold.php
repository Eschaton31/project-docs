<?php

namespace App\Console\Commands;

use App\Ai\Agents\DocScaffolder;
use App\Ai\ReviewRun;
use App\Ai\Scaffold\ScaffoldRun;
use App\Ai\Scaffold\SourceIntake;
use App\Http\Requests\StoreProseScaffoldRequest;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\User;
use App\Services\DocumentTree;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * First half of a scaffold run in Claude Code: takes R scripts uploaded in the editor (--review) or read from
 * disk (--source), and writes the instructions and one prompt per call for Claude Code to answer (see
 * .claude/skills/doc-scaffold). prose-review:import reads the answers back. The scripts are never run.
 */
#[Signature('prose-review:scaffold-export
    {document? : Slug of the document to draft into (not needed with --review)}
    {--review= : A scaffold uploaded in the editor that is waiting for Claude Code}
    {--source=* : An R script, or a folder of them (repeatable)}
    {--entry= : The script that is opened first, as named in its folder (main.R)}
    {--block= : Draft into this topic instead of the document\'s top level}
    {--run-notes-file= : A text file saying how the scripts are run}
    {--user= : Email of the admin the scaffold is for}')]
#[Description('Prepare documentation drafted from R scripts for Claude Code to write')]
class ExportProseScaffold extends Command
{
    public function handle(DocumentTree $tree, SourceIntake $intake): int
    {
        try {
            $review = $this->option('review') ? $this->uploaded() : $this->fromDisk($intake);
        } catch (ValidationException $e) {
            return $this->fail(implode(' ', $e->validator->errors()->all()));
        }

        $prepared = ScaffoldRun::prepare($review, $tree);
        $review->update(['snapshot' => ['nodes' => $prepared['nodes'], 'calls' => $prepared['calls']]]);
        ReviewRun::broadcast($review);

        $dir = ExportProseReview::directory($review);
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/instructions.md", self::instructions());
        $count = count($prepared['calls']);
        foreach ($prepared['calls'] as $i => $call) {
            $n = $i + 1;
            File::put("{$dir}/call-{$n}.md", "# Call {$n} of {$count}\n\nWrite your answer to: ".self::answerPath($review, $n)."\n\n".ScaffoldRun::prompt($prepared, $call, $review->run_notes)."\n");
        }

        $this->line("Review: {$review->id}");
        $this->line('Folder: '.str_replace('\\', '/', $dir));
        $this->line('Instructions: '.str_replace('\\', '/', "{$dir}/instructions.md"));
        foreach (range(1, $count) as $n) {
            $this->line("Call {$n}: ".str_replace('\\', '/', "{$dir}/call-{$n}.md").' -> '.self::answerPath($review, $n));
        }
        foreach ($review->sources as $source) {
            foreach ($source->redactions as $redaction) {
                $this->warn("Redacted a secret in {$source->name}, line {$redaction['line']}.");
            }
        }
        $this->line("When every answer is written: php artisan prose-review:import {$review->id}");

        return self::SUCCESS;
    }

    public static function answerPath(ProseReview $review, int $call): string
    {
        return str_replace('\\', '/', ExportProseReview::directory($review)."/call-{$call}.json");
    }

    private function uploaded(): ProseReview
    {
        $review = ProseReview::query()->find($this->option('review'));
        if (! $review?->isScaffold() || $review->status !== ProseReview::AWAITING) {
            return $this->fail("Scaffold [{$this->option('review')}] isn't waiting for Claude Code.");
        }

        return $review;
    }

    private function fromDisk(SourceIntake $intake): ProseReview
    {
        $document = Document::query()->where('slug', (string) $this->argument('document'))->first();
        if (! $document) {
            return $this->fail("No document with the slug [{$this->argument('document')}].");
        }
        if (! $this->option('source')) {
            return $this->fail('Give the R scripts with --source=<file or folder>, or an editor upload with --review=<id>.');
        }

        $blockId = $this->option('block');
        if ($blockId && ! in_array($document->blocks()->whereKey($blockId)->value('type'), StoreProseScaffoldRequest::SCOPES, true)) {
            return $this->fail("Block [{$blockId}] isn't a primary or secondary topic in this document.");
        }

        $notesFile = $this->option('run-notes-file');
        if ($notesFile && ! File::isFile($notesFile)) {
            return $this->fail("No run notes file at [{$notesFile}].");
        }
        $notes = $notesFile ? trim(File::get($notesFile)) ?: null : null;
        if ($notes !== null && mb_strlen($notes) > config('ai-review.scaffold.max_run_notes')) {
            return $this->fail('The run notes are longer than '.config('ai-review.scaffold.max_run_notes').' characters.');
        }

        $user = $this->option('user') ? User::query()->where('email', $this->option('user'))->first() : null;
        if ($this->option('user') && ! $user?->isAdmin()) {
            return $this->fail("[{$this->option('user')}] isn't an admin.");
        }

        $prepared = $intake->prepare(SourceIntake::fromPaths($this->option('source')));

        return DB::transaction(function () use ($document, $blockId, $user, $notes, $intake, $prepared) {
            $review = $document->proseReviews()->create([
                'mode' => ProseReview::SCAFFOLD,
                'scope_block_id' => $blockId,
                'requested_by' => $user?->id,
                'model' => ProseReview::CLAUDE_CODE,
                'kinds' => [],
                'run_notes' => $notes,
                'status' => ProseReview::AWAITING,
            ]);
            $intake->store($review, $prepared, $this->option('entry'));

            return $review;
        });
    }

    /**
     * The scaffolder's instructions (the same the API path sends) and how to hand the answer back as a file.
     */
    private static function instructions(): string
    {
        return (new DocScaffolder)->instructions()."\n\n".<<<'MD'
        # How to answer

        Each call file says where to write its answer. Write one JSON object to that file and nothing else
        (no Markdown fence, no comments):

        {
          "sections": [
            {"section": "dependencies", "after": "B1",
             "blocks_json": "[{\"type\": \"PrimaryTopic\", \"props\": {\"title\": \"DEPENDENCIES\"}, \"children\": [{\"type\": \"ItemDescriptionTable\", \"props\": {\"itemName\": \"Package\", \"descriptionName\": \"Used for\", \"mono\": true, \"data\": [{\"item\": \"dplyr\", \"description\": \"Filtering and summarising the export\"}]}}]}]",
             "sources": ["code"], "citations": [{"file": "F1", "from": 1, "to": 3}],
             "rationale": "The document doesn't list the packages the scripts load."}
          ],
          "expand": [
            {"section": "overview", "anchor": "B1", "position": "end",
             "blocks_json": "[{\"type\": \"Placeholder\", \"props\": {\"text\": \"Who uses the report, and for what?\"}}]",
             "sources": ["document"], "citations": [],
             "rationale": "The overview doesn't say who the report is for, and the scripts can't tell."}
          ],
          "script_topics": [
            {"file": "F2", "into": "",
             "blocks_json": "[{\"type\": \"SecondaryTopic\", \"props\": {\"title\": \"clean.R\"}, \"children\": []}]",
             "sources": ["code"], "citations": [{"file": "F2", "from": 1, "to": 40}],
             "rationale": "Documents what clean.R does."}
          ]
        }

        - All three lists are required; use [] when there is nothing to suggest.
        - "blocks_json" is a string that contains JSON (escape its quotes).
        - R code in a CodeBlock must be copied exactly from a script it cites, or the suggestion is discarded
          when the answers are imported.
        MD;
    }
}
