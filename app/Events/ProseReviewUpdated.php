<?php

namespace App\Events;

use App\Models\ProseReview;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A prose review started, finished or failed. Sent on an admin-only channel, separate from the document's
 * presence channel, so other editors never receive anything about reviews.
 */
class ProseReviewUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly ProseReview $review) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("document.{$this->review->document_id}.reviews");
    }

    public function broadcastAs(): string
    {
        return 'review.updated';
    }

    public function broadcastWith(): array
    {
        return ['review' => $this->review->toSummary()];
    }
}
