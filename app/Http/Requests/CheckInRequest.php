<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'secret_token' => ['required', 'string', 'max:255'],
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ];
    }
}
