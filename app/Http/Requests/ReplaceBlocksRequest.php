<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nesting, types and option keys of the nodes are checked by DocumentTree::assertValidNodes.
 */
class ReplaceBlocksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'blocks' => ['required', 'array', 'min:1', 'max:100'],
            'blocks.*.id' => ['required', 'uuid', 'distinct'],
            'blocks.*.version' => ['required', 'integer', 'min:1'],
            'nodes' => ['required', 'array', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, int> block id => version
     */
    public function versions(): array
    {
        return array_column($this->validated('blocks'), 'version', 'id');
    }
}
