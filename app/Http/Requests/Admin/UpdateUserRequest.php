<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
         $userUuid = $this->route('user');

        return [
            'first_name' => 'sometimes|required|string|max:100',
            'last_name'  => 'sometimes|required|string|max:100',
            'email'  => [
                'sometimes',
                'required',
                'email:rfc,dns',
                 Rule::unique('users', 'email')->ignore($userUuid, 'uuid'),
            ],
            'role'   => 'sometimes|required|in:user,admin',
            'status' => 'sometimes|required|in:active,inactive',
        ];
    }
}
