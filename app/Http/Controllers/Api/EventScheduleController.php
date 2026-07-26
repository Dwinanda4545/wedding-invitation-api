<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventScheduleStoreRequest;
use App\Http\Requests\EventScheduleUpdateRequest;
use App\Models\Event;
use App\Models\EventSchedule;

class EventScheduleController extends Controller
{
    public function store(EventScheduleStoreRequest $request, Event $event)
    {
        $schedule = $event->schedules()->create($request->validated());

        return response()->json(['data' => $schedule], 201);
    }

    public function update(EventScheduleUpdateRequest $request, Event $event, EventSchedule $schedule)
    {
        $this->assertBelongsToEvent($event, $schedule);
        $schedule->update($request->validated());

        return response()->json(['data' => $schedule->fresh()]);
    }

    public function destroy(Event $event, EventSchedule $schedule)
    {
        $this->assertBelongsToEvent($event, $schedule);
        $schedule->delete();

        return response()->json(['message' => 'Deleted']);
    }

    protected function assertBelongsToEvent(Event $event, EventSchedule $schedule): void
    {
        abort_if($schedule->event_id !== $event->id, 404);
    }
}
