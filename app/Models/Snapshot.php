<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A published version: the document frozen as exported JSON, plus its PDF once rendered.
 */
#[Fillable(['document_id', 'label', 'seq', 'tree', 'pdf_path', 'created_by'])]
class Snapshot extends Model
{
    protected function casts(): array
    {
        return ['tree' => 'array', 'seq' => 'integer'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function latestExport(): HasOne
    {
        return $this->hasOne(Export::class)->latestOfMany();
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'seq' => $this->seq,
            'createdBy' => $this->creator?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
            'pdf' => $this->latestExport?->toSummary(),
        ];
    }

    /**
     * The frozen document in the shape the print view renders ({ document, blocks }).
     */
    public function toPrintData(): array
    {
        $tree = $this->tree;

        return [
            'document' => [
                'slug' => $tree['slug'] ?? $this->document->slug,
                'title' => $tree['title'],
                'meta' => $tree['meta'] ?: new \stdClass,
                'titlePage' => $tree['titlePage'] ?: new \stdClass,
            ],
            'blocks' => $tree['blocks'],
        ];
    }
}
