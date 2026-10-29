<?php

namespace App\Console\Commands;

use App\Models\ProseReview;
use App\Models\ProseReviewSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the R scripts uploaded for scaffolds once they are config('ai-review.scaffold.retention_days') old,
 * counted from the scaffold's last change (when it finished). The suggestions stay; their citations can no longer
 * be opened.
 */
#[Signature('prose-review:prune-sources')]
#[Description('Delete R scripts uploaded for scaffolds after the retention period')]
class PruneScaffoldSources extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(config('ai-review.scaffold.retention_days'));
        $reviews = ProseReview::query()
            ->where('mode', ProseReview::SCAFFOLD)
            ->where('updated_at', '<', $cutoff)
            ->whereHas('sources')
            ->with('sources')
            ->get();

        $files = 0;
        foreach ($reviews as $review) {
            $review->sources->each(function (ProseReviewSource $source) use (&$files) {
                Storage::disk($source->disk)->delete($source->path);
                $source->delete();
                $files++;
            });
        }

        $this->info("Deleted {$files} uploaded scripts from {$reviews->count()} scaffolds.");

        return self::SUCCESS;
    }
}
