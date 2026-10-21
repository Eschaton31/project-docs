<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:255'],
            'meta' => ['sometimes', 'array:fileName,runningTitle,toc'],
            'meta.fileName' => ['nullable', 'string', 'max:255'],
            'meta.runningTitle' => ['nullable', 'array:bold,light'],
            'meta.runningTitle.*' => ['nullable', 'string', 'max:255'],
            'meta.toc' => ['boolean'],
            'titlePage' => ['sometimes', 'array:title,subtitle,client,project,version,dateSubmitted,submittedBy,submittedTo'],
            'titlePage.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
