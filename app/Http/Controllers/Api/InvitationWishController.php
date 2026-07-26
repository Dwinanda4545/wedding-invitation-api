<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvitationWishStoreRequest;
use App\Models\Guest;
use App\Models\InvitationWish;

class InvitationWishController extends Controller
{
    public function store(InvitationWishStoreRequest $request, string $secret_token)
    {
        $guest = Guest::query()
            ->where('secret_token', $secret_token)
            ->with('event')
            ->first();

        if (! $guest || ! $guest->event) {
            return response()->json(['message' => 'Invitation not found'], 404);
        }

        $wish = InvitationWish::create([
            'event_id' => $guest->event_id,
            'guest_id' => $guest->id,
            'guest_name' => $request->input('guest_name', $guest->name),
            'message' => $request->input('message'),
            'rsvp_status' => $request->input('rsvp_status', 'pending'),
        ]);

        return response()->json([
            'data' => [
                'id' => $wish->id,
                'guest_name' => $wish->guest_name,
                'message' => $wish->message,
                'rsvp_status' => $wish->rsvp_status,
                'created_at' => $wish->created_at,
            ],
        ], 201);
    }

    public function destroy(string $secret_token, InvitationWish $wish)
    {
        $guest = Guest::query()
            ->where('secret_token', $secret_token)
            ->first();

        if (! $guest || $wish->event_id !== $guest->event_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $wish->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
