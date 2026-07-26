<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventScheduleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'maps_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
