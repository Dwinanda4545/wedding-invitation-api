<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitationWishStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'rsvp_status' => ['nullable', Rule::in(['pending', 'attending', 'not_attending'])],
        ];
    }
}
