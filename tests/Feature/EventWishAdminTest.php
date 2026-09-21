<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\InvitationWish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventWishAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_event_wishes(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);
        $other = Event::query()->create([
            'name' => 'Other',
            'slug' => 'o-'.uniqid(),
        ]);

        InvitationWish::query()->create([
            'event_id' => $event->id,
            'guest_id' => null,
            'guest_name' => 'Ana',
            'message' => 'Semoga bahagia',
            'rsvp_status' => 'attending',
        ]);
        InvitationWish::query()->create([
            'event_id' => $event->id,
            'guest_id' => null,
            'guest_name' => 'Budi',
            'message' => 'Barakallah',
            'rsvp_status' => 'pending',
        ]);
        InvitationWish::query()->create([
            'event_id' => $other->id,
            'guest_id' => null,
            'guest_name' => 'Cici',
            'message' => 'Wrong event',
            'rsvp_status' => 'attending',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/events/'.$event->id.'/wishes')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.guest_name', 'Budi')
            ->assertJsonPath('data.1.guest_name', 'Ana');
    }

    public function test_guest_cannot_list_event_wishes(): void
    {
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);

        $this->getJson('/api/events/'.$event->id.'/wishes')
            ->assertUnauthorized();
    }
}
