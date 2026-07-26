<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventMusicStoreRequest extends FormRequest
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
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a',
                'max:10240',
            ],
        ];
    }
}
