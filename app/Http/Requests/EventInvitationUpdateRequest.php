<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventInvitationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invitation_mode' => ['sometimes', 'nullable', 'string', 'in:sections,html'],
            'invitation_template' => ['sometimes', 'nullable', 'string', 'max:50'],
            'invitation_style' => ['sometimes', 'nullable', 'array'],
            'invitation_content' => ['sometimes', 'nullable', 'string'],
            'couple_info' => ['sometimes', 'nullable', 'array'],
            'couple_info.groom' => ['sometimes', 'nullable', 'array'],
            'couple_info.bride' => ['sometimes', 'nullable', 'array'],
            'couple_info.opening_quote' => ['sometimes', 'nullable', 'string'],
            'couple_info.couple_initial' => ['sometimes', 'nullable', 'string', 'max:20'],
            'invitation_settings' => ['sometimes', 'nullable', 'array'],
            'hosts' => ['sometimes', 'nullable', 'array'],
            'hosts.groom_side' => ['sometimes', 'nullable', 'array'],
            'hosts.bride_side' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
