<?php

namespace Tests\Feature;

use App\Models\EnvelopeTransaction;
use App\Models\Event;
use App\Models\InvitationWish;
use App\Models\User;
use App\Services\DokuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_invitation_returns_greeting_without_qr(): void
    {
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
            'universal_invitation_token' => 'opentokenabc',
            'universal_invitation_enabled' => true,
            'universal_greeting' => 'Yth. Bapak/Ibu/Saudara/i',
        ]);

        $this->getJson('/api/invitation/open/opentokenabc')
            ->assertOk()
            ->assertJsonPath('is_universal', true)
            ->assertJsonPath('greeting', 'Yth. Bapak/Ibu/Saudara/i')
            ->assertJsonPath('guest.name', 'Yth. Bapak/Ibu/Saudara/i')
            ->assertJsonPath('guest.qr_code_url', null)
            ->assertJsonPath('event.id', $event->id);
    }

    public function test_disabled_open_token_is_404(): void
    {
        Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
            'universal_invitation_token' => 'opentokenoff',
            'universal_invitation_enabled' => false,
        ]);

        $this->getJson('/api/invitation/open/opentokenoff')->assertNotFound();
    }

    public function test_open_link_can_store_many_wishes_without_guest(): void
    {
        Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
            'universal_invitation_token' => 'opentokenwish',
            'universal_invitation_enabled' => true,
        ]);

        $this->postJson('/api/invitation/open/opentokenwish/wishes', [
            'guest_name' => 'Andi',
            'message' => 'Selamat',
            'rsvp_status' => 'attending',
        ])->assertCreated();

        $this->postJson('/api/invitation/open/opentokenwish/wishes', [
            'guest_name' => 'Sari',
            'message' => 'Bahagia selalu',
        ])->assertCreated();

        $this->assertSame(2, InvitationWish::query()->whereNull('guest_id')->count());
    }

    public function test_open_link_can_create_envelope_without_guest(): void
    {
        Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
            'universal_invitation_token' => 'opentokenabc',
            'universal_invitation_enabled' => true,
            'invitation_settings' => [
                'sections' => ['digital_envelope' => true],
            ],
        ]);

        $this->mock(DokuService::class, function ($mock) {
            $mock->shouldReceive('createTransaction')
                ->once()
                ->withArgs(fn ($tx, $path) => $path === '/invitation/open/opentokenabc')
                ->andReturn('https://pay.example/checkout');
        });

        $this->postJson('/api/invitation/open/opentokenabc/digital-envelopes', [
            'sender_name' => 'Andi',
            'amount' => 100000,
        ])->assertCreated()
            ->assertJsonPath('data.payment_url', 'https://pay.example/checkout');

        $this->assertTrue(
            EnvelopeTransaction::query()->whereNull('guest_id')->where('sender_name', 'Andi')->exists()
        );
    }

    public function test_admin_can_enable_and_regenerate_universal_link(): void
    {
        config(['app.frontend_url' => 'http://localhost:5173']);

        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
        ]);

        $enabled = $this->actingAs($admin)
            ->putJson('/api/events/'.$event->id.'/invitation', [
                'universal_invitation_enabled' => true,
                'universal_greeting' => 'Yth. Keluarga',
            ])
            ->assertOk()
            ->assertJsonPath('data.universal_invitation_enabled', true)
            ->assertJsonPath('data.universal_greeting', 'Yth. Keluarga');

        $url = $enabled->json('data.universal_invitation_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('/invitation/open/', $url);

        $token = basename((string) parse_url($url, PHP_URL_PATH));
        $this->getJson('/api/invitation/open/'.$token)->assertOk();

        $regenerated = $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/universal-invitation/regenerate')
            ->assertOk();

        $newUrl = $regenerated->json('data.universal_invitation_url');
        $this->assertNotSame($url, $newUrl);
        $this->getJson('/api/invitation/open/'.$token)->assertNotFound();
    }
}
