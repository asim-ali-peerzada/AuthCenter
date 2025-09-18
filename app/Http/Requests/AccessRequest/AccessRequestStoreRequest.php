<?php

namespace App\Http\Requests\AccessRequest;

use Illuminate\Foundation\Http\FormRequest;

class AccessRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain_id' => ['required', 'integer'],
            'domain_name' => ['nullable', 'string', 'max:255'],
            'request_type' => ['nullable', 'string', 'in:access,activation'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
