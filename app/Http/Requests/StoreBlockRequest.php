<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlockRequest extends FormRequest
{
    use BlockOptionRules;

    public function rules(): array
    {
        return [
            // clients may generate the id so they can show the block before the server replies
            'id' => ['nullable', 'uuid', 'unique:blocks,id'],
            'type' => ['required', 'string', 'max:64'],
            'parentId' => ['nullable', 'uuid'],
            'afterId' => ['nullable', 'uuid', 'prohibits:beforeId'],
            'beforeId' => ['nullable', 'uuid'],
            'props' => ['sometimes', 'array'],
            ...$this->optionRules(),
        ];
    }
}
