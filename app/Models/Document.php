<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['slug', 'title', 'meta', 'title_page', 'created_by', 'updated_by'])]
class Document extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'title_page' => 'array',
            'version' => 'integer',
            'seq' => 'integer',
        ];
    }

    /**
     * A slug based on $value that no document (including deleted ones) uses yet.
     */
    public static function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'document';
        $slug = $base;

        for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    /**
     * Document settings as sent to clients (the block tree is sent separately).
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'meta' => $this->meta ?: new \stdClass,
            'titlePage' => $this->title_page ?: new \stdClass,
            'version' => $this->version,
            'seq' => $this->seq,
            'updatedBy' => $this->updated_by,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
