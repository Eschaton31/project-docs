<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlockRequest extends FormRequest
{
    use BlockOptionRules;

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'props' => ['sometimes', 'array'],
            ...$this->optionRules(),
        ];
    }
}
