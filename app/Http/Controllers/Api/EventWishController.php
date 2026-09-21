<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\InvitationWish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventWishController extends Controller
{
    public function index(Request $request, Event $event): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 50)));

        $paginator = InvitationWish::query()
            ->where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'data' => collect($paginator->items())->map(fn (InvitationWish $wish) => [
                'id' => $wish->id,
                'guest_name' => $wish->guest_name,
                'message' => $wish->message,
                'rsvp_status' => $wish->rsvp_status,
                'created_at' => $wish->created_at,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
