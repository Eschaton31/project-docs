<?php

namespace App\Services;

use App\Ai\PropPath;
use App\Exceptions\LockedException;
use App\Exceptions\StaleVersionException;
use App\Exceptions\SuggestionStaleException;
use App\Models\Block;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepting and dismissing AI suggestions. Accepted changes go through DocumentEditor like any other edit:
 * version and lock checks, a revision, and live updates for the other editors.
 */
class ProseReviews
{
    public function __construct(private DocumentEditor $editor) {}

    public function accept(ProseSuggestion $suggestion, User $user): Revision
    {
        $this->assertPending($suggestion);

        try {
            $revision = $suggestion->kind === ProseSuggestion::TEXT
                ? $this->applyText($suggestion, $user)
                : $this->applyRestructure($suggestion, $user);
        } catch (StaleVersionException) {
            // someone saved one of the blocks between our check and the write
            throw $this->stale($suggestion);
        }

        $this->resolve($suggestion, ProseSuggestion::ACCEPTED, $user);
        if ($suggestion->kind === ProseSuggestion::RESTRUCTURE) {
            $this->staleOthersOn($suggestion);
        }

        return $revision;
    }

    public function dismiss(ProseSuggestion $suggestion, User $user): void
    {
        $this->assertPending($suggestion);
        $this->resolve($suggestion, ProseSuggestion::DISMISSED, $user);
    }

    /**
     * Accepts every pending text suggestion for one rule. Ones that went stale, or whose block another tab
     * holds, are skipped and counted.
     *
     * @return array{revisions: list<Revision>, accepted: int, stale: int, locked: int}
     */
    public function acceptRule(ProseReview $review, string $rule, User $user): array
    {
        $result = ['revisions' => [], 'accepted' => 0, 'stale' => 0, 'locked' => 0];

        $suggestions = $review->suggestions()
            ->where('kind', ProseSuggestion::TEXT)
            ->where('rule', $rule)
            ->where('status', ProseSuggestion::PENDING)
            ->orderBy('id')
            ->get();

        foreach ($suggestions as $suggestion) {
            try {
                $result['revisions'][] = $this->accept($suggestion, $user);
                $result['accepted']++;
            } catch (SuggestionStaleException) {
                $result['stale']++;
            } catch (LockedException) {
                $result['locked']++;
            }
        }

        return $result;
    }

    /**
     * The block may have been edited since the review; the suggestion still applies while its original
     * text is there, exactly once.
     */
    private function applyText(ProseSuggestion $suggestion, User $user): Revision
    {
        $revision = DB::transaction(function () use ($suggestion, $user) {
            $block = Block::query()->whereKey($suggestion->block_ids[0])->lockForUpdate()->first();
            $text = $block ? PropPath::get($block->props ?? [], $suggestion->path) : null;
            if (! is_string($text) || substr_count($text, $suggestion->original) !== 1) {
                return null;
            }

            $props = PropPath::set($block->props, $suggestion->path, str_replace($suggestion->original, $suggestion->suggested, $text));

            return $this->editor->updateBlock($block, $user, ['props' => $props], $block->version);
        });

        // marked outside the transaction, so the stale status isn't rolled back with it
        return $revision ?? throw $this->stale($suggestion);
    }

    /**
     * Replaces whole blocks, so nothing in them may have changed since the review.
     */
    private function applyRestructure(ProseSuggestion $suggestion, User $user): Revision
    {
        $versions = $suggestion->block_versions;
        $current = Block::query()->whereKey(array_keys($versions))->pluck('version', 'id');
        foreach ($versions as $id => $version) {
            if ((int) ($current[$id] ?? 0) !== (int) $version) {
                throw $this->stale($suggestion);
            }
        }

        return $this->editor->replaceBlocks($suggestion->review->document, $user, $suggestion->block_ids, $versions, $suggestion->replacement);
    }

    /**
     * Other pending suggestions on blocks a restructure just removed can't apply any more.
     */
    private function staleOthersOn(ProseSuggestion $accepted): void
    {
        $removed = array_keys($accepted->block_versions);

        $accepted->review->suggestions()
            ->where('status', ProseSuggestion::PENDING)
            ->get()
            ->filter(fn (ProseSuggestion $other) => array_intersect(array_keys($other->block_versions), $removed) !== [])
            ->each(fn (ProseSuggestion $other) => $other->update(['status' => ProseSuggestion::STALE]));
    }

    private function stale(ProseSuggestion $suggestion): SuggestionStaleException
    {
        $suggestion->update(['status' => ProseSuggestion::STALE]);

        return new SuggestionStaleException($suggestion);
    }

    private function resolve(ProseSuggestion $suggestion, string $status, User $user): void
    {
        $suggestion->update(['status' => $status, 'resolved_by' => $user->id, 'resolved_at' => now()]);
    }

    private function assertPending(ProseSuggestion $suggestion): void
    {
        if (! $suggestion->isPending()) {
            throw ValidationException::withMessages(['suggestion' => "This suggestion was already {$suggestion->status}."]);
        }
    }
}
