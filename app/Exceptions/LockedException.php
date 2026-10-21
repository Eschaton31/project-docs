<?php

namespace App\Exceptions;

use App\Models\BlockLock;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Someone else (another person or another tab) is editing the block or settings being changed.
 */
class LockedException extends RuntimeException
{
    public function __construct(public readonly BlockLock $lock, public readonly ?array $current = null)
    {
        $name = $lock->user?->name ?? 'Someone else';
        $what = $lock->target === 'settings' ? 'the document settings' : 'this block';

        parent::__construct("{$name} is editing {$what}.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'holder' => $this->lock->toHolder(),
            'current' => $this->current,
        ], 423);
    }
}
