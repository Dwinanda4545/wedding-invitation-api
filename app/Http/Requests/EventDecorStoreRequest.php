<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventDecorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml,image/gif',
                'max:5120',
            ],
        ];
    }
}
