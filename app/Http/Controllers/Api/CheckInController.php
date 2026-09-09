<?php

namespace App\Http\Controllers\Api;

use App\Events\GuestAttendanceUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Models\Event;
use App\Models\Guest;

class CheckInController extends Controller
{
    public function store(CheckInRequest $request)
    {
        $user = $request->user();
        $eventId = (int) $request->validated('event_id');
        $event = Event::query()->find($eventId);

        if (! $event || ! $user?->canAccessEvent($event)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke acara ini.',
            ], 403);
        }

        $guest = Guest::query()
            ->where('secret_token', $request->validated('secret_token'))
            ->where('event_id', $event->id)
            ->with('event')
            ->first();

        if (! $guest) {
            return response()->json([
                'success' => false,
                'message' => 'QR tidak valid untuk acara ini',
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

        event(new GuestAttendanceUpdated($guest));

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
