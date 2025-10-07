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
            'first_name'=> 'required|string|max:100|regex:/^[a-zA-Z0-9\s\-_.]+$/i',
            'last_name' => 'required|string|max:100|regex:/^[a-zA-Z0-9\s\-_.]+$/i',
            'key'       => 'required|string|max:20|alpha_dash',
            'email'     => 'required|email:rfc,dns|unique:users,email',
            'password'  => 'required|string|min:8|max:64',
        ];
    }
    
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'first_name' => strip_tags($this->first_name),
            'last_name'  => strip_tags($this->last_name),
            'key'        => strip_tags($this->key),
            'email'      => filter_var($this->email, FILTER_SANITIZE_EMAIL),
        ]);
    }
}
