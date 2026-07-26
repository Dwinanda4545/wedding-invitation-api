<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvitationThemeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'style' => ['sometimes', 'required', 'array'],
            'style.pageBackground' => ['required_with:style', 'string', 'max:255'],
            'style.pageTextColor' => ['required_with:style', 'string', 'max:50'],
            'style.tagColor' => ['nullable', 'string', 'max:50'],
            'style.qrCardBackground' => ['required_with:style', 'string', 'max:255'],
            'style.qrCardBorder' => ['required_with:style', 'string', 'max:50'],
            'style.qrHintColor' => ['required_with:style', 'string', 'max:50'],
            'style.fontFamily' => ['nullable', 'string', 'max:255'],
            'style.fontSize' => ['nullable', 'string', 'max:20'],
            'style.fontWeight' => ['nullable', 'string', 'max:10'],
            'style.textAlign' => ['nullable', 'string', 'in:left,center,right'],
            'style.letterSpacing' => ['nullable', 'string', 'max:20'],
            'default_content' => ['nullable', 'string'],
        ];
    }
}
