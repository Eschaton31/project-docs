<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'target', 'user_id', 'client_id', 'expires_at'])]
class BlockLock extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public function toHolder(): array
    {
        return [
            'target' => $this->target,
            'userId' => $this->user_id,
            'name' => $this->user?->name,
            'color' => $this->user?->color,
            'clientId' => $this->client_id,
            // seconds left, rounded up so a fresh lock reads as the full TTL
            'ttl' => max(0, (int) ceil(now()->diffInSeconds($this->expires_at, false))),
        ];
    }
}
