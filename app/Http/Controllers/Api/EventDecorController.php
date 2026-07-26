<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventDecorStoreRequest;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class EventDecorController extends Controller
{
    public function store(EventDecorStoreRequest $request, Event $event)
    {
        $file = $request->file('file');
        $path = $file->store("decor/events/{$event->id}", 'public');

        return response()->json([
            'data' => [
                'path' => $path,
                'url'  => Storage::disk('public')->url($path),
            ],
        ], 201);
    }

    public function destroy(Event $event, string $filename)
    {
        $path = "decor/events/{$event->id}/{$filename}";

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Deleted']);
    }
}
