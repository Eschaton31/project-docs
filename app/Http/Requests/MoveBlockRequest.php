<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveBlockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'parentId' => ['present', 'nullable', 'uuid'],
            'afterId' => ['nullable', 'uuid', 'prohibits:beforeId'],
            'beforeId' => ['nullable', 'uuid'],
        ];
    }
}
