<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['id', 'document_id', 'parent_id', 'position', 'type', 'props', 'options', 'updated_by'])]
class Block extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'props' => 'array',
            'options' => 'array',
            'version' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * The block as sent to clients and stored in revisions (without children).
     */
    public function toNode(): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parent_id,
            'position' => $this->position,
            'type' => $this->type,
            // keep empty objects as {} in JSON rather than []
            'props' => $this->props ?: new \stdClass,
            'options' => $this->options ?: new \stdClass,
            'version' => $this->version,
            'updatedBy' => $this->updated_by,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
