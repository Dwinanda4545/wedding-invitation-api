<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoveStoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'date_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'story' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
