<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use RuntimeException;

class GuestImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'content' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('file')) {
                return;
            }

            if ($this->filled('content') && $this->filled('filename')) {
                return;
            }

            $validator->errors()->add(
                'file',
                'Kirim file upload (multipart) atau content+filename (JSON base64).'
            );
        });
    }

    public function uploadedImportFile(): UploadedFile
    {
        if ($this->hasFile('file')) {
            return $this->file('file');
        }

        $filename = basename((string) $this->input('filename'));
        $raw = base64_decode((string) $this->input('content'), true);
        if ($raw === false || $filename === '' || $raw === '') {
            throw new RuntimeException('Invalid import content');
        }

        // Keep size roughly in line with 10MB upload limit
        if (strlen($raw) > 10 * 1024 * 1024) {
            throw new RuntimeException('Import file too large (max 10MB)');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'guestimp_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file');
        }

        file_put_contents($tmp, $raw);

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'csv');
        $mime = match ($extension) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'txt' => 'text/plain',
            default => 'text/csv',
        };

        return new UploadedFile($tmp, $filename, $mime, UPLOAD_ERR_OK, true);
    }
}
