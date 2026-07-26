<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoveStoryStoreRequest;
use App\Http\Requests\LoveStoryUpdateRequest;
use App\Models\Event;
use App\Models\LoveStory;

class LoveStoryController extends Controller
{
    public function store(LoveStoryStoreRequest $request, Event $event)
    {
        $story = $event->loveStories()->create($request->validated());

        return response()->json(['data' => $story], 201);
    }

    public function update(LoveStoryUpdateRequest $request, Event $event, LoveStory $loveStory)
    {
        $this->assertBelongsToEvent($event, $loveStory);
        $loveStory->update($request->validated());

        return response()->json(['data' => $loveStory->fresh()]);
    }

    public function destroy(Event $event, LoveStory $loveStory)
    {
        $this->assertBelongsToEvent($event, $loveStory);
        $loveStory->delete();

        return response()->json(['message' => 'Deleted']);
    }

    protected function assertBelongsToEvent(Event $event, LoveStory $loveStory): void
    {
        abort_if($loveStory->event_id !== $event->id, 404);
    }
}
