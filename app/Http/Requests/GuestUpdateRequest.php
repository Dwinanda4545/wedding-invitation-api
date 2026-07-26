<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guest_type' => ['sometimes', 'required', Rule::in(['VIP', 'Regular'])],
        ];
    }
}

