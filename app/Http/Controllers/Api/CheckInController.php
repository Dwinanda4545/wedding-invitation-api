<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Models\Guest;

class CheckInController extends Controller
{
    public function store(CheckInRequest $request)
    {
        $guest = Guest::query()
            ->where('secret_token', $request->validated('secret_token'))
            ->with('event')
            ->first();

        if (! $guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code',
            ], 404);
        }

        if ($guest->is_attended) {
            return response()->json([
                'success' => false,
                'message' => 'Already Checked In',
                'guest' => [
                    'name' => $guest->name,
                ],
                'event' => [
                    'name' => $guest->event?->name,
                ],
            ]);
        }

        $guest->forceFill([
            'is_attended' => true,
            'scanned_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Guest Verified',
            'guest' => [
                'name' => $guest->name,
            ],
            'event' => [
                'name' => $guest->event?->name,
            ],
        ]);
    }
}
