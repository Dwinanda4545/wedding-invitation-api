<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRelationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $eventId = $this->route('event')?->id;

        return [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('guest_relations', 'label')->where(
                    fn ($q) => $q->where('event_id', $eventId),
                ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
