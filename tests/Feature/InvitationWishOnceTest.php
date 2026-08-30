<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\InvitationWish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationWishOnceTest extends TestCase
{
    use RefreshDatabase;

    private function guest(): Guest
    {
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
        ]);

        return Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Budi Santoso',
            'guest_type' => 'Regular',
        ]);
    }

    public function test_guest_can_send_one_wish(): void
    {
        $guest = $this->guest();

        $this->postJson("/api/invitation/{$guest->secret_token}/wishes", [
            'guest_name' => 'Budi',
            'message' => 'Selamat ya',
            'rsvp_status' => 'attending',
        ])->assertCreated()
            ->assertJsonPath('data.message', 'Selamat ya')
            ->assertJsonPath('data.rsvp_status', 'attending');

        $this->assertDatabaseCount('invitation_wishes', 1);
    }

    public function test_guest_cannot_send_a_second_wish(): void
    {
        $guest = $this->guest();

        $this->postJson("/api/invitation/{$guest->secret_token}/wishes", [
            'message' => 'Pertama',
            'rsvp_status' => 'attending',
        ])->assertCreated();

        $this->postJson("/api/invitation/{$guest->secret_token}/wishes", [
            'message' => 'Kedua',
            'rsvp_status' => 'not_attending',
        ])->assertStatus(409)
            ->assertJsonPath('data.message', 'Pertama');

        $this->assertDatabaseCount('invitation_wishes', 1);
        $this->assertSame('Pertama', InvitationWish::query()->first()->message);
    }

    public function test_guest_can_update_rsvp_after_sending_wish(): void
    {
        $guest = $this->guest();

        $this->postJson("/api/invitation/{$guest->secret_token}/wishes", [
            'message' => 'Doa restu',
            'rsvp_status' => 'attending',
        ])->assertCreated();

        $this->patchJson("/api/invitation/{$guest->secret_token}/wishes", [
            'rsvp_status' => 'not_attending',
        ])->assertOk()
            ->assertJsonPath('data.message', 'Doa restu')
            ->assertJsonPath('data.rsvp_status', 'not_attending');

        $this->assertDatabaseCount('invitation_wishes', 1);
        $this->assertSame('not_attending', InvitationWish::query()->first()->rsvp_status);
        $this->assertSame('Doa restu', InvitationWish::query()->first()->message);
    }

    public function test_invitation_payload_includes_the_guest_own_wish(): void
    {
        $guest = $this->guest();

        $this->postJson("/api/invitation/{$guest->secret_token}/wishes", [
            'message' => 'Selamat',
            'rsvp_status' => 'attending',
        ])->assertCreated();

        $this->getJson("/api/invitation/{$guest->secret_token}")
            ->assertOk()
            ->assertJsonPath('guest.wish.message', 'Selamat')
            ->assertJsonPath('guest.wish.rsvp_status', 'attending');
    }
}
