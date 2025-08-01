<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainRequest extends FormRequest
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
        $domainId = $this->route('domain')->id;

        return [
            'name'  => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('domains', 'name')->ignore($domainId),
            ],
            'url'   => [
                'sometimes',
                'required',
                'url',
                'max:255',
                Rule::unique('domains', 'url')->ignore($domainId),
            ],
            'detail' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'image'  => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048',
            ],
        ];
    }
}
