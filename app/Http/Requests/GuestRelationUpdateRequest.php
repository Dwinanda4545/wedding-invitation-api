<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRelationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $eventId = $this->route('event')?->id;
        $relationId = $this->route('guestRelation')?->id;

        return [
            'label' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('guest_relations', 'label')
                    ->where(fn ($q) => $q->where('event_id', $eventId))
                    ->ignore($relationId),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
