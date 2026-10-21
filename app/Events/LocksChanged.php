<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A lock was taken, renewed or released. Locks expire on their own (ttl) if a release never arrives.
 */
class LocksChanged implements ShouldBroadcastNow
{
    /**
     * @param  'locked'|'released'  $action
     * @param  array{target: string, userId: int, name: ?string, color: ?string, clientId: string, ttl: int}  $holder
     */
    public function __construct(public readonly int $documentId, public readonly string $action, public readonly array $holder) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("document.{$this->documentId}");
    }

    public function broadcastAs(): string
    {
        return 'locks.changed';
    }

    public function broadcastWith(): array
    {
        return ['action' => $this->action, ...$this->holder];
    }
}
