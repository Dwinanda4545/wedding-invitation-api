<?php

namespace Tests\Feature;

use App\Models\EnvelopeTransaction;
use App\Models\Event;
use App\Models\InvitationWish;
use App\Models\UniversalInvitation;
use App\Models\User;
use App\Services\DokuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function openInvite(Event $event, array $attrs = []): UniversalInvitation
    {
        return UniversalInvitation::query()->create(array_merge([
            'event_id' => $event->id,
            'name' => 'Grup Keluarga A',
            'greeting' => 'Yth. Bapak/Ibu/Saudara/i',
            'token' => 'opentokenabc',
            'enabled' => true,
            'sort_order' => 0,
        ], $attrs));
    }

    public function test_open_invitation_returns_greeting_without_qr(): void
    {
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
        ]);
        $this->openInvite($event);

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
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
        ]);
        $this->openInvite($event, [
            'token' => 'opentokenoff',
            'enabled' => false,
        ]);

        $this->getJson('/api/invitation/open/opentokenoff')->assertNotFound();
    }

    public function test_open_link_can_store_many_wishes_without_guest(): void
    {
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
        ]);
        $this->openInvite($event, ['token' => 'opentokenwish']);

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
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
            'invitation_settings' => [
                'sections' => ['digital_envelope' => true],
            ],
        ]);
        $this->openInvite($event);

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

    public function test_admin_can_manage_multiple_universal_invitations(): void
    {
        config(['app.frontend_url' => 'http://localhost:5173']);

        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
        ]);

        $created = $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/universal-invitations', [
                'name' => 'Grup Keluarga A',
                'greeting' => 'Yth. Keluarga A',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Grup Keluarga A')
            ->assertJsonPath('data.greeting', 'Yth. Keluarga A');

        $url = $created->json('data.url');
        $this->assertStringContainsString('/invitation/open/', $url);
        $id = $created->json('data.id');
        $token = basename((string) parse_url($url, PHP_URL_PATH));

        $this->getJson('/api/invitation/open/'.$token)
            ->assertOk()
            ->assertJsonPath('greeting', 'Yth. Keluarga A');

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/universal-invitations', [
                'name' => 'Grup Keluarga B',
                'greeting' => 'Yth. Keluarga B',
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->getJson('/api/events/'.$event->id.'/universal-invitations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $regenerated = $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/universal-invitations/'.$id.'/regenerate')
            ->assertOk();

        $newUrl = $regenerated->json('data.url');
        $this->assertNotSame($url, $newUrl);
        $this->getJson('/api/invitation/open/'.$token)->assertNotFound();

        $this->actingAs($admin)
            ->patchJson('/api/events/'.$event->id.'/universal-invitations/'.$id, [
                'enabled' => false,
            ])
            ->assertOk();

        $newToken = basename((string) parse_url($newUrl, PHP_URL_PATH));
        $this->getJson('/api/invitation/open/'.$newToken)->assertNotFound();
    }
}
