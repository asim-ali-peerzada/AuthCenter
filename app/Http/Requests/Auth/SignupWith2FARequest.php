<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupWith2FARequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|string|uuid',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'code.size' => 'The 2FA code must be exactly 6 digits.',
            'code.regex' => 'The 2FA code must contain only numbers.',
            'session_id.uuid' => 'Invalid session identifier.',
        ];
    }
}