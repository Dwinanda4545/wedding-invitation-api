<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UniversalInvitationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'greeting' => ['sometimes', 'nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
