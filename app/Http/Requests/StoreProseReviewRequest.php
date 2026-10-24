<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProseReviewRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');

        return [
            // a topic (or any block) to review with everything inside it; omitted = the whole document
            'blockId' => ['nullable', 'uuid', Rule::exists('blocks', 'id')->where('document_id', $document->id)->whereNull('deleted_at')],
            'kinds' => ['sometimes', 'array', 'min:1'],
            'kinds.*' => ['distinct', Rule::in(['text', 'restructure'])],
            'model' => ['sometimes', Rule::in(array_keys(config('ai-review.models')))],
        ];
    }

    public function kinds(): array
    {
        return array_values($this->validated('kinds', ['text', 'restructure']));
    }

    public function model(): string
    {
        return $this->validated('model', config('ai-review.default'));
    }
}
