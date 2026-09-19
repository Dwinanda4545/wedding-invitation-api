<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestRelationStoreRequest;
use App\Http\Requests\GuestRelationUpdateRequest;
use App\Models\Event;
use App\Models\GuestRelation;

class GuestRelationController extends Controller
{
    public function index(Event $event)
    {
        $rows = $event->guestRelations()->get();

        return response()->json([
            'data' => $rows->map(fn (GuestRelation $r) => $this->payload($r)),
        ]);
    }

    public function store(GuestRelationStoreRequest $request, Event $event)
    {
        $data = $request->validated();
        if (! array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $event->guestRelations()->max('sort_order') + 1;
        }

        $relation = $event->guestRelations()->create($data);

        return response()->json(['data' => $this->payload($relation)], 201);
    }

    public function update(GuestRelationUpdateRequest $request, Event $event, GuestRelation $guestRelation)
    {
        $this->assertBelongs($event, $guestRelation);
        $guestRelation->update($request->validated());

        return response()->json(['data' => $this->payload($guestRelation->fresh())]);
    }

    public function destroy(Event $event, GuestRelation $guestRelation)
    {
        $this->assertBelongs($event, $guestRelation);
        $guestRelation->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function assertBelongs(Event $event, GuestRelation $relation): void
    {
        if ((int) $relation->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    private function payload(GuestRelation $relation): array
    {
        return [
            'id' => $relation->id,
            'event_id' => $relation->event_id,
            'label' => $relation->label,
            'sort_order' => $relation->sort_order,
            'created_at' => $relation->created_at,
            'updated_at' => $relation->updated_at,
        ];
    }
}
