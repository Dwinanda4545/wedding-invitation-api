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
        $eventId = $this->route('event')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guest_type' => ['sometimes', 'required', Rule::in(['VIP', 'Regular'])],
            'guest_relation_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('guest_relations', 'id')->where(
                    fn ($q) => $q->where('event_id', $eventId),
                ),
            ],
        ];
    }
}

