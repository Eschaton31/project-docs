<?php

namespace App\Console\Commands;

use App\Ai\ProseExtractor;
use App\Ai\ReviewRun;
use App\Ai\Scaffold\ScaffoldRun;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Second half of a review or scaffold run in Claude Code: reads its answers (one JSON file per chunk or call),
 * validates them against what was exported, and saves the suggestions for admins to accept in the editor.
 */
#[Signature('prose-review:import {review : The review id prose-review:export printed}')]
#[Description('Import the answers of an AI prose review run in Claude Code')]
class ImportProseReview extends Command
{
    public function handle(): int
    {
        $review = ProseReview::query()->find($this->argument('review'));
        if (! $review || $review->status !== ProseReview::AWAITING || ! $review->snapshot) {
            return $this->fail("Review [{$this->argument('review')}] isn't waiting for answers.");
        }

        $scaffold = $review->isScaffold();
        $chunks = $scaffold ? $review->snapshot['calls'] : $review->snapshot['chunks'];
        $outputs = [];
        $problems = [];
        foreach (array_keys($chunks) as $i) {
            $path = $scaffold ? ExportProseScaffold::answerPath($review, $i + 1) : ExportProseReview::answerPath($review, $i + 1);
            [$outputs[$i], $problem] = $this->answer($path, $scaffold ? ['sections', 'expand', 'script_topics'] : ProseSuggestion::KINDS);
            if ($problem) {
                $problems[] = "{$path}: {$problem}";
            }
        }
        // all or nothing, so a fixed answer can simply be imported again
        if ($problems) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $dropped = [];
        if ($scaffold) {
            $dropped = ScaffoldRun::save($review, ScaffoldRun::from($review, $review->snapshot['nodes'], $chunks), $outputs);
        } else {
            $snapshot = (new ProseExtractor)->extract($review->snapshot['nodes']);
            foreach ($chunks as $i => $chunk) {
                $dropped = ReviewRun::addDropped($dropped, ReviewRun::save($review, $snapshot, $chunk, $outputs[$i]));
            }
        }
        ReviewRun::finish($review, ['input_tokens' => 0, 'output_tokens' => 0], count($chunks), $dropped);

        $this->info("Saved {$review->suggestions()->count()} suggestions. Open the document's AI review panel to go through them.");
        if ($dropped) {
            $this->warn('Discarded, because they could not be applied safely:');
            $this->table(['Reason', 'Count'], collect($dropped)->map(fn (int $count, string $reason) => [$reason, $count])->values());
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: array, 1: ?string} the parsed answer, or what's wrong with the file
     */
    private function answer(string $path, array $keys): array
    {
        if (! File::exists($path)) {
            return [[], 'missing'];
        }

        $output = json_decode(File::get($path), true);
        if (! is_array($output) || array_is_list($output)) {
            return [[], 'not a JSON object ('.(json_last_error() ? json_last_error_msg() : 'wrong shape').')'];
        }
        foreach ($keys as $key) {
            if (! is_array($output[$key] ?? []) || ! array_is_list($output[$key] ?? [])) {
                return [[], "\"{$key}\" must be a list"];
            }
        }

        return [$output, null];
    }
}
