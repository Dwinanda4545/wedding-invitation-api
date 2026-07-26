<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query()->orderByDesc('event_date')->orderByDesc('id');

        if ($request->boolean('with_guest_counts')) {
            $query->withCount('guests');
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(EventStoreRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['slug'] = $this->ensureUniqueSlug($data['slug']);

        $event = Event::create($data);

        return response()->json(['data' => $event], 201);
    }

    public function show(Event $event)
    {
        return response()->json(['data' => $event->loadCount('guests')]);
    }

    public function update(EventUpdateRequest $request, Event $event)
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (isset($data['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $event->id);
        }

        $event->update($data);

        return response()->json(['data' => $event->fresh()]);
    }

    public function destroy(Event $event)
    {
        $event->delete();

        return response()->json(['message' => 'Deleted']);
    }

    protected function ensureUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $i = 2;

        while (Event::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
