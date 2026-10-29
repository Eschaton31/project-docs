<?php

namespace App\Ai\Scaffold;

use App\Models\Document;
use App\Models\ProseSuggestion;

/**
 * Where an accepted scaffold section goes, so the sections end up in the template's order whichever order the
 * admin accepts them in. Each section was given an anchor (the existing block it follows, or none = first);
 * sections sharing an anchor are ordered among themselves by their place in the template.
 */
final class ScaffoldPlacement
{
    /**
     * @return array{0: ?string, 1: ?string} [afterId, beforeId] for DocumentEditor::insertTree; both null = at the end
     */
    public static function for(ProseSuggestion $suggestion, Document $document): array
    {
        $parentId = $suggestion->parent_id;
        $anchor = $suggestion->block_ids[0] ?? null;
        $here = fn (?string $id) => $id !== null && $document->blocks()->whereKey($id)->where('parent_id', $parentId)->exists();

        // sections of this scaffold already in the document, still where they were put
        $placed = $suggestion->review->suggestions()
            ->where('kind', ProseSuggestion::SCAFFOLD)
            ->where('status', ProseSuggestion::ACCEPTED)
            ->whereNotNull('inserted_id')
            ->whereKeyNot($suggestion->id)
            ->get()
            ->filter(fn (ProseSuggestion $other) => $other->parent_id === $parentId && $here($other->inserted_id))
            ->sortBy('order');
        $lower = fn ($sections) => $sections->filter(fn (ProseSuggestion $other) => $other->order < $suggestion->order)->last();
        $higher = fn ($sections) => $sections->first(fn (ProseSuggestion $other) => $other->order > $suggestion->order);

        if ($anchor === null || $here($anchor)) {
            $sameAnchor = $placed->filter(fn (ProseSuggestion $other) => ($other->block_ids[0] ?? null) === $anchor);
            if ($before = $lower($sameAnchor)) {
                return [$before->inserted_id, null];
            }
            if ($after = $higher($sameAnchor)) {
                return [null, $after->inserted_id];
            }
            if ($anchor !== null) {
                return [$anchor, null];
            }

            // first: before whatever is first now (with nothing there, the end is the start)
            return [null, $document->blocks()->where('parent_id', $parentId)->orderBy('position')->value('id')];
        }

        // the anchor was deleted or moved: place by this scaffold's other sections, or last
        if ($after = $higher($placed)) {
            return [null, $after->inserted_id];
        }
        if ($before = $lower($placed)) {
            return [$before->inserted_id, null];
        }

        return [null, null];
    }
}
