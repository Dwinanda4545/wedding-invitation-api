<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventInvitationUpdateRequest;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class EventInvitationController extends Controller
{
    public function show(Event $event)
    {
        $event->load([
            'schedules',
            'loveStories',
            'galleryImages',
            'wishes' => fn ($q) => $q->latest()->limit(50),
        ]);

        return response()->json(['data' => $this->payload($event)]);
    }

    public function update(EventInvitationUpdateRequest $request, Event $event)
    {
        $event->update($request->validated());

        return response()->json([
            'data' => $this->payload($event->fresh()->load([
                'schedules',
                'loveStories',
                'galleryImages',
            ])),
        ]);
    }

    protected function payload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'event_date' => $event->event_date,
            'location' => $event->location,
            'invitation_mode' => $event->invitation_mode ?? 'sections',
            'invitation_template' => $event->invitation_template,
            'invitation_style' => $event->invitation_style,
            'invitation_content' => $event->invitation_content,
            'couple_info' => $event->couple_info,
            'invitation_settings' => $event->invitation_settings,
            'hosts' => $event->hosts,
            'schedules' => $event->schedules->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'event_date' => $s->event_date?->format('Y-m-d'),
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'venue' => $s->venue,
                'address' => $s->address,
                'maps_url' => $s->maps_url,
                'sort_order' => $s->sort_order,
            ]),
            'love_stories' => $event->loveStories->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'date_label' => $s->date_label,
                'story' => $s->story,
                'sort_order' => $s->sort_order,
            ]),
            'gallery' => $event->galleryImages->map(fn ($g) => [
                'id' => $g->id,
                'caption' => $g->caption,
                'sort_order' => $g->sort_order,
                'image_url' => Storage::disk('public')->url($g->image_path),
            ]),
            'wishes' => $event->relationLoaded('wishes')
                ? $event->wishes->map(fn ($w) => [
                    'id' => $w->id,
                    'guest_name' => $w->guest_name,
                    'message' => $w->message,
                    'rsvp_status' => $w->rsvp_status,
                    'created_at' => $w->created_at,
                ])
                : [],
        ];
    }
}
