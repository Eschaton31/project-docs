<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['document_id', 'scope_block_id', 'requested_by', 'model', 'kinds', 'status', 'error', 'document_seq', 'input_tokens', 'output_tokens'])]
class ProseReview extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'kinds' => 'array',
            'document_seq' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ProseSuggestion::class, 'review_id');
    }

    public function wants(string $kind): bool
    {
        return in_array($kind, $this->kinds ?? [], true);
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'scopeBlockId' => $this->scope_block_id,
            'model' => $this->model,
            'kinds' => $this->kinds,
            'error' => $this->error,
            'documentSeq' => $this->document_seq,
            'usage' => ['inputTokens' => $this->input_tokens, 'outputTokens' => $this->output_tokens],
            'requestedBy' => $this->requested_by,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
