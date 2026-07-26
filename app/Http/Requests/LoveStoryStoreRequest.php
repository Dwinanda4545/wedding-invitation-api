<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoveStoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'date_label' => ['nullable', 'string', 'max:255'],
            'story' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
