<?php

namespace App\Http\Requests;

/**
 * Validation for the per-block options shared by create and update.
 */
trait BlockOptionRules
{
    protected function optionRules(): array
    {
        return [
            'options' => ['sometimes', 'array:pageBreakBefore,anchorId,draft,hidden,assignee,status'],
            'options.pageBreakBefore' => ['boolean'],
            'options.anchorId' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z][\w-]*$/'],
            'options.draft' => ['boolean'],
            'options.hidden' => ['boolean'],
            'options.assignee' => ['nullable', 'integer', 'exists:users,id'],
            'options.status' => ['nullable', 'in:todo,in_progress,review,done'],
        ];
    }
}
