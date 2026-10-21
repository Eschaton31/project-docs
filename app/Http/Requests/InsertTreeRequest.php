<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nesting, types and option keys of the nodes are checked by DocumentTree::assertValidNodes.
 */
class InsertTreeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parentId' => ['nullable', 'uuid'],
            'afterId' => ['nullable', 'uuid', 'prohibits:beforeId'],
            'beforeId' => ['nullable', 'uuid'],
            'blocks' => ['required', 'array', 'min:1', 'max:500'],
        ];
    }
}
