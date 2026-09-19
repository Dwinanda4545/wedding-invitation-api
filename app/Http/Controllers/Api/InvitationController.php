<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use App\Models\InvitationWish;
use Illuminate\Support\Facades\Storage;

class InvitationController extends Controller
{
    public function show(string $secret_token)
    {
        $guest = Guest::query()
            ->where('secret_token', $secret_token)
            ->with([
                'event.schedules',
                'event.loveStories',
                'event.galleryImages',
                'event.wishes' => fn ($q) => $q->latest()->limit(30),
            ])
            ->first();

        if (! $guest || ! $guest->event) {
            return response()->json(['message' => 'Invitation not found'], 404);
        }

        $event = $guest->event;

        return response()->json([
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'phone_number' => $guest->phone_number,
                'guest_type' => $guest->guest_type,
                'secret_token' => $guest->secret_token,
                'qr_code_url' => (! $guest->is_attended && $guest->qr_code_path)
                    ? Storage::disk('public')->url($guest->qr_code_path)
                    : null,
                'is_attended' => (bool) $guest->is_attended,
                'scanned_at' => $guest->scanned_at,
                'wish' => $this->guestWishPayload($guest),
            ],
            'event' => $this->eventPayload($event),
        ]);
    }

    public function showOpen(string $token)
    {
        $event = Event::findEnabledUniversal($token);

        if (! $event) {
            return response()->json(['message' => 'Invitation not found'], 404);
        }

        $event->load([
            'schedules',
            'loveStories',
            'galleryImages',
            'wishes' => fn ($q) => $q->latest()->limit(30),
        ]);

        $greeting = $event->universal_greeting ?: Event::DEFAULT_UNIVERSAL_GREETING;

        return response()->json([
            'is_universal' => true,
            'greeting' => $greeting,
            'guest' => [
                'id' => null,
                'name' => $greeting,
                'phone_number' => null,
                'guest_type' => null,
                'secret_token' => $token,
                'qr_code_url' => null,
                'is_attended' => false,
                'scanned_at' => null,
                'wish' => null,
            ],
            'event' => $this->eventPayload($event),
        ]);
    }

    private function guestWishPayload(Guest $guest): ?array
    {
        $wish = InvitationWish::query()
            ->where('event_id', $guest->event_id)
            ->where('guest_id', $guest->id)
            ->latest()
            ->first();

        if (! $wish) {
            return null;
        }

        return [
            'id' => $wish->id,
            'guest_name' => $wish->guest_name,
            'message' => $wish->message,
            'rsvp_status' => $wish->rsvp_status,
            'created_at' => $wish->created_at,
        ];
    }

    private function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
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
            'wishes' => $event->wishes->map(fn ($w) => [
                'id' => $w->id,
                'guest_name' => $w->guest_name,
                'message' => $w->message,
                'rsvp_status' => $w->rsvp_status,
                'created_at' => $w->created_at,
            ]),
        ];
    }
}
