<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventMusicStoreRequest;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class EventMusicController extends Controller
{
    public function store(EventMusicStoreRequest $request, Event $event)
    {
        $file = $request->file('file');
        $path = $file->store("music/events/{$event->id}", 'public');

        $this->deletePreviousMusicFile($event);

        return response()->json([
            'data' => [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }

    protected function deletePreviousMusicFile(Event $event): void
    {
        $settings = $event->invitation_settings ?? [];
        $previousUrl = $settings['music_url'] ?? null;

        if (! is_string($previousUrl)) {
            return;
        }

        $prefix = Storage::disk('public')->url('music/events/'.$event->id.'/');

        if (str_starts_with($previousUrl, $prefix)) {
            $relativePath = 'music/events/'.$event->id.'/'.basename($previousUrl);
            Storage::disk('public')->delete($relativePath);
        }
    }
}
