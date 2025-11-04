<?php

namespace App\Http\Requests\Ets;

use Illuminate\Foundation\Http\FormRequest;

class EtsUserUnlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
        ];
    }
}