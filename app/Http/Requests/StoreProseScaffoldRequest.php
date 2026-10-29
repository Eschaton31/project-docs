<?php

namespace App\Http\Requests;

use App\Models\Block;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProseScaffoldRequest extends FormRequest
{
    // topics a scaffold can go into: their sections are one level down, and each script one more
    public const SCOPES = ['PrimaryTopic', 'SecondaryTopic'];

    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');
        $config = config('ai-review.scaffold');

        return [
            // the extension and content are checked by SourceIntake: browsers send .R as text/plain or octet-stream
            'files' => ['required', 'array', 'min:1', 'max:'.$config['max_files']],
            'files.*' => ['file', 'max:'.$config['max_file_kb']],
            // each file's path inside a dropped folder (webkitRelativePath), in the same order
            'paths' => ['sometimes', 'array'],
            'paths.*' => ['nullable', 'string', 'max:255'],
            // the script the admin opens first
            'entry' => ['nullable', 'string', 'max:255'],
            'runNotes' => ['nullable', 'string', 'max:'.$config['max_run_notes']],
            // a topic to draft into; omitted = the document's top level
            'blockId' => ['nullable', 'uuid', Rule::exists('blocks', 'id')->where('document_id', $document->id)->whereNull('deleted_at')],
            'model' => ['sometimes', Rule::in(array_keys(config('ai-review.models')))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $blockId = $this->input('blockId');
                if ($blockId && ! $validator->errors()->has('blockId') && ! in_array(Block::query()->whereKey($blockId)->value('type'), self::SCOPES, true)) {
                    $validator->errors()->add('blockId', 'Documentation can be drafted into the whole document, a primary topic or a secondary topic.');
                }
            },
        ];
    }

    public function runNotes(): ?string
    {
        return trim((string) $this->validated('runNotes')) ?: null;
    }

    public function model(): string
    {
        return $this->validated('model', config('ai-review.default'));
    }
}
