<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The browser tab making the current request, from the X-Client-Id header the editor sends.
 * Locks belong to a tab (the same person may have two open), and revisions record it so a tab can
 * recognise its own changes.
 */
final class EditorClient
{
    public static function id(): ?string
    {
        $id = request()?->header('X-Client-Id');

        return is_string($id) && Str::isUuid($id) ? strtolower($id) : null;
    }
}
