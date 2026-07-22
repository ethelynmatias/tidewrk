<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by the `auth:sanctum` middleware on the route,
     * so any authenticated user may submit an upload.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',      // CSV only
                'max:51200',          // 50 MB — large enough for 1,000+ row datasets
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A CSV file is required.',
            'file.file'     => 'The upload must be a valid file.',
            'file.mimes'    => 'The file must be a CSV file.',
            'file.max'      => 'The file may not be larger than 50 MB.',
        ];
    }
}
