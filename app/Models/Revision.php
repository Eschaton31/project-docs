<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'seq', 'block_id', 'user_id', 'client_id', 'op', 'before', 'after'])]
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toEvent(): array
    {
        return [
            'seq' => $this->seq,
            'op' => $this->op,
            'blockId' => $this->block_id,
            'userId' => $this->user_id,
            'clientId' => $this->client_id,
            'before' => $this->before,
            'after' => $this->after,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
