<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel per document: who's here, live block changes and block locks.
Broadcast::channel('document.{documentId}', function (User $user, int $documentId) {
    return ['id' => $user->id, 'name' => $user->name, 'color' => $user->color];
});

// AI prose review progress for a document: admins only.
Broadcast::channel('document.{documentId}.reviews', fn (User $user, int $documentId) => $user->isAdmin());
