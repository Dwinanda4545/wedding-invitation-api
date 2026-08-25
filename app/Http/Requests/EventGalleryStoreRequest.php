<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventGalleryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:20480'],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'Ukuran foto galeri maksimal 20MB.',
        ];
    }
}
