<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded R script of a scaffold, stored privately with its secrets already redacted.
 */
#[Fillable(['review_id', 'ref', 'name', 'entry', 'disk', 'path', 'size', 'sha256', 'chars', 'lines', 'redactions'])]
class ProseReviewSource extends Model
{
    protected function casts(): array
    {
        return [
            'entry' => 'boolean',
            'size' => 'integer',
            'chars' => 'integer',
            'lines' => 'integer',
            'redactions' => 'array',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProseReview::class, 'review_id');
    }

    /**
     * The script as stored: UTF-8, LF line endings, secrets redacted.
     */
    public function text(): string
    {
        return (string) Storage::disk($this->disk)->get($this->path);
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'ref' => $this->ref,
            'name' => $this->name,
            'entry' => $this->entry,
            'size' => $this->size,
            'lines' => $this->lines,
            'redactions' => $this->redactions,
        ];
    }
}
