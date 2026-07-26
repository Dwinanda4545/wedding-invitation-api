<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventGalleryStoreRequest;
use App\Models\Event;
use App\Models\EventGalleryImage;
use Illuminate\Support\Facades\Storage;

class EventGalleryController extends Controller
{
    public function store(EventGalleryStoreRequest $request, Event $event)
    {
        $file = $request->file('image');
        $path = $file->store("gallery/events/{$event->id}", 'public');

        $image = $event->galleryImages()->create([
            'image_path' => $path,
            'caption' => $request->input('caption'),
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return response()->json([
            'data' => [
                'id' => $image->id,
                'caption' => $image->caption,
                'sort_order' => $image->sort_order,
                'image_url' => Storage::disk('public')->url($image->image_path),
            ],
        ], 201);
    }

    public function destroy(Event $event, EventGalleryImage $galleryImage)
    {
        abort_if($galleryImage->event_id !== $event->id, 404);

        Storage::disk('public')->delete($galleryImage->image_path);
        $galleryImage->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
