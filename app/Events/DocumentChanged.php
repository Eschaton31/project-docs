<?php

namespace App\Events;

use App\Models\Revision;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tells the other editors of a document that it changed. Deliberately small (Reverb caps message size):
 * they fetch the change itself from /api/documents/{slug}/events?since=…, which also fills any gaps.
 */
class DocumentChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly Revision $revision) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("document.{$this->revision->document_id}");
    }

    public function broadcastAs(): string
    {
        return 'document.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'seq' => $this->revision->seq,
            'op' => $this->revision->op,
            'blockId' => $this->revision->block_id,
            'userId' => $this->revision->user_id,
            'clientId' => $this->revision->client_id,
        ];
    }
}
