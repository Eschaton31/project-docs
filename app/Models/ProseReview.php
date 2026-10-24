<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['document_id', 'scope_block_id', 'requested_by', 'model', 'kinds', 'status', 'error', 'document_seq', 'snapshot', 'input_tokens', 'output_tokens'])]
#[Hidden(['snapshot'])]
class ProseReview extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    // exported for Claude Code; waiting for its results to be imported
    public const AWAITING = 'awaiting';

    public const DONE = 'done';

    public const FAILED = 'failed';

    // the model column of reviews run in Claude Code (with the user's own Claude plan)
    public const CLAUDE_CODE = 'claude-code';

    protected function casts(): array
    {
        return [
            'kinds' => 'array',
            'snapshot' => 'array',
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
