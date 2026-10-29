<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['review_id', 'kind', 'severity', 'rule', 'rationale', 'block_ids', 'block_versions', 'field', 'path', 'original', 'suggested', 'replacement', 'parent_id', 'position', 'sources', 'citations', 'order', 'inserted_id', 'status', 'resolved_by', 'resolved_at'])]
class ProseSuggestion extends Model
{
    public const TEXT = 'text';

    public const RESTRUCTURE = 'restructure';

    // adds new blocks next to or inside an existing one; nothing already there changes
    public const EXPAND = 'expand';

    public const KINDS = [self::TEXT, self::RESTRUCTURE, self::EXPAND];

    // a new section drafted from source files, placed in the template's order (scaffold reviews only)
    public const SCAFFOLD = 'scaffold';

    // where an expansion goes, relative to its block: a sibling before/after it, or its first/last child
    public const POSITIONS = ['before', 'after', 'start', 'end'];

    // where an expansion's content came from; "general" (the model's own knowledge) needs a fact check
    public const SOURCES = ['document', 'brief', 'general'];

    // scaffolds also draw on the uploaded scripts and the admin's notes on how they are run
    public const SCAFFOLD_SOURCES = ['document', 'code', 'run_notes', 'general'];

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
            'sources' => 'array',
            'citations' => 'array',
            'order' => 'integer',
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
            'position' => $this->position,
            'sources' => $this->sources,
            'citations' => $this->citations,
            'order' => $this->order,
            'insertedId' => $this->inserted_id,
            'status' => $this->status,
            'resolvedBy' => $this->resolved_by,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
