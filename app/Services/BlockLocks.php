<?php

namespace App\Services;

use App\Events\LocksChanged;
use App\Exceptions\LockedException;
use App\Models\BlockLock;
use App\Models\Document;
use App\Models\User;
use App\Support\EditorClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Soft locks: whoever selects a block (or opens the document settings) holds it while they edit, and the
 * write path refuses other tabs' changes to it. Locks last TTL seconds and the editor renews them, so a
 * closed tab or dropped connection frees its locks on its own.
 */
class BlockLocks
{
    public const TTL = 60;

    public function acquire(Document $document, User $user, string $clientId, string $target): BlockLock
    {
        $lock = DB::transaction(function () use ($document, $user, $clientId, $target) {
            // serialises lock changes with each other and with edits to the document
            Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            $lock = BlockLock::query()->with('user')->where('document_id', $document->id)->where('target', $target)->first();
            if ($lock && $lock->expires_at->isFuture() && $lock->client_id !== $clientId) {
                throw new LockedException($lock);
            }

            $lock ??= new BlockLock(['document_id' => $document->id, 'target' => $target]);
            $lock->fill(['user_id' => $user->id, 'client_id' => $clientId, 'expires_at' => now()->addSeconds(self::TTL)])->save();

            return $lock->setRelation('user', $user);
        });

        $this->broadcast($document, 'locked', $lock);

        return $lock;
    }

    public function release(Document $document, string $clientId, string $target): void
    {
        $lock = BlockLock::query()->with('user')
            ->where('document_id', $document->id)
            ->where('target', $target)
            ->where('client_id', $clientId)
            ->first();

        if ($lock) {
            $lock->delete();
            $this->broadcast($document, 'released', $lock);
        }
    }

    /**
     * Everything a tab holds, e.g. when it closes.
     */
    public function releaseAll(Document $document, string $clientId): void
    {
        BlockLock::query()->with('user')
            ->where('document_id', $document->id)
            ->where('client_id', $clientId)
            ->get()
            ->each(function (BlockLock $lock) use ($document) {
                $lock->delete();
                $this->broadcast($document, 'released', $lock);
            });
    }

    /**
     * @return Collection<int, BlockLock>
     */
    public function active(Document $document): Collection
    {
        return BlockLock::query()->with('user')->where('document_id', $document->id)->active()->get();
    }

    /**
     * Throws if another tab holds a live lock on any of the targets. $current is sent back with the refusal
     * so the client can show what is actually saved.
     *
     * @param  list<string>  $targets
     */
    public function assertFree(Document $document, array $targets, ?array $current = null): void
    {
        $clientId = EditorClient::id();

        $lock = BlockLock::query()->with('user')
            ->where('document_id', $document->id)
            ->whereIn('target', $targets)
            ->active()
            ->when($clientId, fn ($query) => $query->where('client_id', '!=', $clientId))
            ->first();

        if ($lock) {
            throw new LockedException($lock, $current);
        }
    }

    private function broadcast(Document $document, string $action, BlockLock $lock): void
    {
        // live updates are a nicety: a broadcasting outage must not fail the request
        rescue(fn () => event(new LocksChanged($document->id, $action, $lock->toHolder())), report: true);
    }
}
