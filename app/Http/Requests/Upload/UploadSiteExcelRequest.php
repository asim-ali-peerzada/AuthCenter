<?php

namespace App\Http\Requests\Upload;


use Illuminate\Foundation\Http\FormRequest;

class UploadSiteExcelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:51200', // 50MB in KB
            ],
            'file_type' => [
                'required',
                'string',
                'in:hub,small_cell',
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
            'file.required' => 'Please select an Excel file to upload.',
            'file.mimes' => 'The file must be an Excel file (.xlsx or .xls).',
            'file.max' => 'The file size must not exceed 50MB.',
            'file_type.required' => 'File type is required.',
            'file_type.in' => 'File type must be either hub or small_cell.',
        ];
    }
}
