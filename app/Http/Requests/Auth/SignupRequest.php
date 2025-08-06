<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
            'first_name'=> 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'key'     => 'required|string|max:20',
            'email'    => 'required|email:rfc,dns|unique:users,email',
            'password' => 'required|string|min:8|max:64',
        ];
    }
}
