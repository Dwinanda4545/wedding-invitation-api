<?php

namespace App\Http\Controllers\Api;

use App\Events\GuestAttendanceUpdated;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Http\Request;

class GuestbookController extends Controller
{
    public function index(Request $request, Event $event)
    {
        abort_unless($request->user()?->canAccessEvent($event), 403);

        $query = $event->guests()->orderBy('name');

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where('name', 'like', '%'.$q.'%');
        }

        $status = $request->query('status');
        if ($status === 'attended') {
            $query->where('is_attended', true);
        } elseif ($status === 'pending') {
            $query->where('is_attended', false);
        }

        $guests = $query->get(['id', 'name', 'guest_type', 'is_attended', 'scanned_at']);

        $total = $event->guests()->count();
        $attended = $event->guests()->where('is_attended', true)->count();

        return response()->json([
            'summary' => [
                'total' => $total,
                'attended' => $attended,
                'pending' => max(0, $total - $attended),
            ],
            'data' => $guests->map(fn (Guest $guest) => [
                'id' => $guest->id,
                'name' => $guest->name,
                'guest_type' => $guest->guest_type,
                'is_attended' => (bool) $guest->is_attended,
                'scanned_at' => $guest->scanned_at?->toIso8601String(),
            ]),
        ]);
    }

    public function checkIn(Request $request, Event $event, Guest $guest)
    {
        abort_unless($request->user()?->canAccessEvent($event), 403);
        abort_unless($guest->event_id === $event->id, 404);

        if (! $guest->is_attended) {
            $guest->forceFill([
                'is_attended' => true,
                'scanned_at' => now(),
            ])->save();
            event(new GuestAttendanceUpdated($guest));
        }

        return response()->json([
            'data' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'guest_type' => $guest->guest_type,
                'is_attended' => true,
                'scanned_at' => $guest->scanned_at?->toIso8601String(),
            ],
        ]);
    }

    public function cancelCheckIn(Request $request, Event $event, Guest $guest)
    {
        abort_unless($request->user()?->canAccessEvent($event), 403);
        abort_unless($guest->event_id === $event->id, 404);

        if ($guest->is_attended) {
            $guest->forceFill([
                'is_attended' => false,
                'scanned_at' => null,
            ])->save();
            event(new GuestAttendanceUpdated($guest));
        }

        return response()->json([
            'data' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'guest_type' => $guest->guest_type,
                'is_attended' => false,
                'scanned_at' => null,
            ],
        ]);
    }
}
