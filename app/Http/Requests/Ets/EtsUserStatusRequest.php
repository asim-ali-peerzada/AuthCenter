<?php

namespace App\Http\Requests\Ets;

use Illuminate\Foundation\Http\FormRequest;

class EtsUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['uuid', 'distinct'],
        ];
    }
}