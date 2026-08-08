<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
                // max is in kilobytes → 10240 KB = 10 MB
                'max:10240',
                // Do NOT use mimes/mimetypes here — Windows/browser MIME for MP3/M4A
                // often mismatches Laravel's guess and falsely rejects valid files.
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File musik wajib diunggah. Jika file sudah dipilih, kemungkinan batas upload PHP server (upload_max_filesize/post_max_size) lebih kecil dari ukuran file.',
            'file.file' => 'File musik tidak valid.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if (! $file) {
                return;
            }

            $allowedMimes = [
                'audio/mpeg',
                'audio/mp3',
                'audio/mpeg3',
                'audio/x-mpeg-3',
                'audio/x-mp3',
                'audio/wav',
                'audio/x-wav',
                'audio/wave',
                'audio/vnd.wave',
                'audio/ogg',
                'application/ogg',
                'audio/mp4',
                'audio/x-m4a',
                'audio/aac',
                'audio/m4a',
                'audio/x-aac',
                'video/mp4', // some browsers tag m4a as video/mp4
                'application/octet-stream',
            ];
            $allowedExt = ['mp3', 'wav', 'ogg', 'm4a', 'mp4', 'mpeg', 'mpg'];

            $mime = strtolower((string) $file->getMimeType());
            $clientMime = strtolower((string) ($file->getClientMimeType() ?? ''));
            $ext = strtolower((string) $file->getClientOriginalExtension());

            $mimeOk = in_array($mime, $allowedMimes, true)
                || in_array($clientMime, $allowedMimes, true);
            $extOk = in_array($ext, $allowedExt, true);

            // Accept if extension OR detected MIME looks like audio.
            if (! $mimeOk && ! $extOk) {
                $validator->errors()->add(
                    'file',
                    'Format file tidak didukung (MIME: '.$mime.', ekstensi: '.$ext.'). Gunakan MP3, WAV, OGG, atau M4A.'
                );
            }
        });
    }
}
