<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_ids' => ['required', 'array', 'min:1', 'max:50'],
            'guest_ids.*' => ['integer', 'distinct'],
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'device_id' => ['sometimes', 'nullable', 'integer', 'exists:whatsapp_devices,id'],
        ];
    }
}
