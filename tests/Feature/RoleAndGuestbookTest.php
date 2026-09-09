<?php

namespace Tests\Feature;

use App\Events\GuestAttendanceUpdated;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class RoleAndGuestbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_defaults_to_admin_role(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->canAccessEvent(Event::query()->create([
            'name' => 'A',
            'slug' => 'a-'.uniqid(),
        ])));
    }

    public function test_panitia_cannot_create_events(): void
    {
        $panitia = User::factory()->panitia()->create();

        $this->actingAs($panitia)
            ->postJson('/api/events', ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_panitia_can_access_assigned_guestbook_only(): void
    {
        $assigned = Event::query()->create(['name' => 'Assigned', 'slug' => 'asg-'.uniqid()]);
        $other = Event::query()->create(['name' => 'Other', 'slug' => 'oth-'.uniqid()]);
        $panitia = User::factory()->panitia()->create();
        $panitia->events()->attach($assigned->id);

        Guest::query()->create([
            'event_id' => $assigned->id,
            'name' => 'Budi',
            'guest_type' => 'Regular',
        ]);

        $this->actingAs($panitia)
            ->getJson('/api/events/'.$assigned->id.'/guestbook')
            ->assertOk()
            ->assertJsonPath('summary.total', 1);

        $this->actingAs($panitia)
            ->getJson('/api/events/'.$other->id.'/guestbook')
            ->assertForbidden();
    }

    public function test_check_in_requires_matching_event_and_broadcasts(): void
    {
        EventFacade::fake([GuestAttendanceUpdated::class]);

        $event = Event::query()->create(['name' => 'E', 'slug' => 'e-'.uniqid()]);
        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Siti',
            'guest_type' => 'VIP',
            'secret_token' => 'tokentest123456789012345678901234567890123456789012345678901234',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/check-in', [
                'secret_token' => $guest->secret_token,
                'event_id' => $event->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($guest->fresh()->is_attended);
        EventFacade::assertDispatched(GuestAttendanceUpdated::class);
    }

    public function test_admin_can_create_panitia_user_with_events(): void
    {
        $event = Event::query()->create(['name' => 'E', 'slug' => 'e-'.uniqid()]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Panitia Satu',
                'email' => 'panitia@example.com',
                'password' => 'password',
                'role' => 'panitia',
                'event_ids' => [$event->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'panitia')
            ->assertJsonPath('data.assigned_events.0.id', $event->id);
    }

    public function test_manual_check_in_and_cancel(): void
    {
        EventFacade::fake([GuestAttendanceUpdated::class]);

        $event = Event::query()->create(['name' => 'E', 'slug' => 'e-'.uniqid()]);
        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Ani',
            'guest_type' => 'Regular',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/check-in')
            ->assertOk()
            ->assertJsonPath('data.is_attended', true);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/check-in/cancel')
            ->assertOk()
            ->assertJsonPath('data.is_attended', false);
    }

    public function test_me_includes_role_and_assigned_events(): void
    {
        $event = Event::query()->create(['name' => 'E', 'slug' => 'e-'.uniqid()]);
        $panitia = User::factory()->panitia()->create();
        $panitia->events()->attach($event->id);

        $this->actingAs($panitia)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'panitia')
            ->assertJsonPath('user.assigned_events.0.id', $event->id);
    }
}
