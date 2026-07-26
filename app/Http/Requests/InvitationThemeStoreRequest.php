<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvitationThemeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'style' => ['required', 'array'],
            'style.pageBackground' => ['required', 'string', 'max:255'],
            'style.pageTextColor' => ['required', 'string', 'max:50'],
            'style.tagColor' => ['nullable', 'string', 'max:50'],
            'style.qrCardBackground' => ['required', 'string', 'max:255'],
            'style.qrCardBorder' => ['required', 'string', 'max:50'],
            'style.qrHintColor' => ['required', 'string', 'max:50'],
            'style.fontFamily' => ['nullable', 'string', 'max:255'],
            'style.fontSize' => ['nullable', 'string', 'max:20'],
            'style.fontWeight' => ['nullable', 'string', 'max:10'],
            'style.textAlign' => ['nullable', 'string', 'in:left,center,right'],
            'style.letterSpacing' => ['nullable', 'string', 'max:20'],
            'default_content' => ['nullable', 'string'],
        ];
    }
}
