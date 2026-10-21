<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'snapshot_id', 'mode', 'status', 'file_name', 'path', 'error', 'requested_by'])]
class Export extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'mode' => $this->mode,
            'fileName' => $this->file_name,
            'snapshotId' => $this->snapshot_id,
            'error' => $this->error,
            'downloadUrl' => $this->status === self::DONE ? "/api/exports/{$this->id}/download" : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
