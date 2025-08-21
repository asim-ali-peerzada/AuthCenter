<?php

namespace App\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;

class SiteDetailsRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'siteNames' => 'required|array|min:1|max:100',
            'siteNames.*' => 'required|string|max:255',
            'site_type' => 'required|string|in:hub,smallcell,selected_All',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'siteNames.required' => 'Site names are required.',
            'siteNames.array' => 'Site names must be an array.',
            'siteNames.min' => 'At least one site name is required.',
            'siteNames.max' => 'Maximum 100 site names allowed per request.',
            'siteNames.*.required' => 'Each site name is required.',
            'siteNames.*.string' => 'Each site name must be a string.',
            'siteNames.*.max' => 'Each site name must not exceed 255 characters.',
            'site_type.required' => 'Site type is required.',
            'site_type.in' => 'Site type must be either hub or smallcell.',
        ];
    }
}