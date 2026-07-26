<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('events', 'slug')->ignore($event?->id),
            ],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invitation_template' => ['sometimes', 'nullable', 'string', 'max:50'],
            'invitation_style' => ['sometimes', 'nullable', 'array'],
            'invitation_content' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

