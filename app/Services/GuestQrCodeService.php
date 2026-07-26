<?php

namespace App\Services;

use App\Models\Guest;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GuestQrCodeService
{
    public function generateAndStore(Guest $guest, string $contents): string
    {
        // SVG avoids Imagick/GD PNG requirements on some Windows PHP builds (Laragon).
        $relativePath = "qrcodes/events/{$guest->event_id}/guests/{$guest->id}.svg";

        $svg = QrCode::format('svg')
            ->size(512)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($contents);

        Storage::disk('public')->put($relativePath, $svg);

        return $relativePath;
    }
}

