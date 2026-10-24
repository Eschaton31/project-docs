<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['review_id', 'kind', 'severity', 'rule', 'rationale', 'block_ids', 'block_versions', 'field', 'path', 'original', 'suggested', 'replacement', 'parent_id', 'status', 'resolved_by', 'resolved_at'])]
class ProseSuggestion extends Model
{
    public const TEXT = 'text';

    public const RESTRUCTURE = 'restructure';

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DISMISSED = 'dismissed';

    // the blocks changed since the review, so it can no longer be applied as suggested
    public const STALE = 'stale';

    protected function casts(): array
    {
        return [
            'block_ids' => 'array',
            'block_versions' => 'array',
            'replacement' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProseReview::class, 'review_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'reviewId' => $this->review_id,
            'kind' => $this->kind,
            'severity' => $this->severity,
            'rule' => $this->rule,
            'rationale' => $this->rationale,
            'blockIds' => $this->block_ids,
            'field' => $this->field,
            'path' => $this->path,
            // not $this->original: inside the model that is Eloquent's own $original property (all attributes)
            'original' => $this->getAttribute('original'),
            'suggested' => $this->suggested,
            'replacement' => $this->replacement,
            'parentId' => $this->parent_id,
            'status' => $this->status,
            'resolvedBy' => $this->resolved_by,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
