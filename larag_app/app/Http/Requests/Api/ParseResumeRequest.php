<?php

namespace App\Http\Requests\Api;

use App\Models\AiSetting;
use Illuminate\Foundation\Http\FormRequest;

class ParseResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) AiSetting::current()->parsing_max_file_size_kb;

        return [
            'file' => [
                'required',
                'file',
                'max:'.max(1, $maxKb),
                'mimes:pdf,doc,docx,txt,jpg,jpeg,png,webp,gif',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A resume file is required (field: "file").',
            'file.file' => 'The upload must be a valid file.',
            'file.max' => 'The file is larger than the configured limit.',
            'file.mimes' => 'Allowed file types: PDF, DOC, DOCX, TXT, JPG, PNG, WebP, GIF.',
        ];
    }
}
