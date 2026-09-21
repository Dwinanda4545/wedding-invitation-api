<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UniversalInvitationStoreRequest;
use App\Http\Requests\UniversalInvitationUpdateRequest;
use App\Models\Event;
use App\Models\UniversalInvitation;

class UniversalInvitationController extends Controller
{
    public function index(Event $event)
    {
        return response()->json([
            'data' => $event->universalInvitations->map(
                fn (UniversalInvitation $item) => $this->payload($item),
            ),
        ]);
    }

    public function store(UniversalInvitationStoreRequest $request, Event $event)
    {
        $data = $request->validated();
        if (! array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $event->universalInvitations()->max('sort_order') + 1;
        }
        if (! array_key_exists('enabled', $data)) {
            $data['enabled'] = true;
        }

        $item = $event->universalInvitations()->create([
            ...$data,
            'token' => UniversalInvitation::generateToken(),
        ]);

        return response()->json(['data' => $this->payload($item)], 201);
    }

    public function update(
        UniversalInvitationUpdateRequest $request,
        Event $event,
        UniversalInvitation $universalInvitation,
    ) {
        $this->assertBelongs($event, $universalInvitation);
        $universalInvitation->update($request->validated());

        return response()->json(['data' => $this->payload($universalInvitation->fresh())]);
    }

    public function destroy(Event $event, UniversalInvitation $universalInvitation)
    {
        $this->assertBelongs($event, $universalInvitation);
        $universalInvitation->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function regenerate(Event $event, UniversalInvitation $universalInvitation)
    {
        $this->assertBelongs($event, $universalInvitation);
        $universalInvitation->forceFill([
            'token' => UniversalInvitation::generateToken(),
        ])->save();

        return response()->json(['data' => $this->payload($universalInvitation->fresh())]);
    }

    private function assertBelongs(Event $event, UniversalInvitation $item): void
    {
        if ((int) $item->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    private function payload(UniversalInvitation $item): array
    {
        return [
            'id' => $item->id,
            'event_id' => $item->event_id,
            'name' => $item->name,
            'greeting' => $item->greeting,
            'enabled' => (bool) $item->enabled,
            'sort_order' => $item->sort_order,
            'url' => $item->publicUrl(),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
