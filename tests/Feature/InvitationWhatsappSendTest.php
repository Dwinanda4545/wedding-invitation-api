<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\InvitationSend;
use App\Models\User;
use App\Models\WhatsappDevice;
use App\Services\FlowkirimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvitationWhatsappSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'http://localhost:5173',
            'flowkirim.api_token' => 'test-token',
            'flowkirim.base_url' => 'https://scan.flowkirim.com',
            'flowkirim.send_path' => '/api/whatsapp/messages/text',
            'flowkirim.timeout' => 20,
            'flowkirim.device_field' => 'session_id',
            'flowkirim.append_jid_suffix' => true,
        ]);
    }

    public function test_flowkirim_send_text_includes_device_field(): void
    {
        Http::fake([
            'scan.flowkirim.com/*' => Http::response(['id' => '1'], 200),
        ]);

        app(FlowkirimService::class)->sendText('628111111111', 'hi', 'dev-abc');

        Http::assertSent(fn ($req) => $req['session_id'] === 'dev-abc'
            && $req['to'] === '628111111111@s.whatsapp.net');
    }

    public function test_admin_can_send_invitation_to_one_guest(): void
    {
        Http::fake([
            'scan.flowkirim.com/api/whatsapp/messages/text' => Http::response([
                'id' => 'msg-123',
                'status' => 'sent',
            ], 200),
        ]);

        [$admin, $event, $guest, $device] = $this->seedAdminEventGuest('081234567890');

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Halo {nama}, undangan: {link}',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.phone_number', '6281234567890')
            ->assertJsonPath('data.provider_message_id', 'msg-123')
            ->assertJsonPath('data.whatsapp_device_id', $device->id);

        $this->assertDatabaseHas('invitation_sends', [
            'guest_id' => $guest->id,
            'status' => InvitationSend::STATUS_SENT,
            'phone_number' => '6281234567890',
            'whatsapp_device_id' => $device->id,
        ]);

        $send = InvitationSend::query()->first();
        $this->assertStringContainsString('Halo '.$guest->name, $send->message_body);
        $this->assertStringContainsString('/invitation/'.$guest->secret_token, $send->message_body);

        Http::assertSent(function ($request) use ($device) {
            return $request->url() === 'https://scan.flowkirim.com/api/whatsapp/messages/text'
                && $request['to'] === '6281234567890@s.whatsapp.net'
                && $request['session_id'] === $device->provider_device_id
                && str_contains($request['message'], 'Halo');
        });
    }

    public function test_send_uses_override_device_instead_of_event_default(): void
    {
        Http::fake([
            'scan.flowkirim.com/api/whatsapp/messages/text' => Http::response(['id' => 'm2'], 200),
        ]);

        [$admin, $event, $guest] = $this->seedAdminEventGuest('081234567890');
        $override = WhatsappDevice::query()->create([
            'name' => 'Override',
            'provider_device_id' => 'provider-override',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Hi {nama}',
                'device_id' => $override->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_device_id', $override->id);

        Http::assertSent(fn ($req) => $req['session_id'] === 'provider-override');
    }

    public function test_send_fails_without_device(): void
    {
        Http::fake();

        $admin = User::factory()->admin()->create();
        $event = Event::query()->create(['name' => 'Wedding', 'slug' => 'w-'.uniqid()]);
        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Budi',
            'phone_number' => '081234567890',
            'guest_type' => 'VIP',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Hi',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
    }

    public function test_send_fails_when_device_inactive(): void
    {
        Http::fake();

        [$admin, $event, $guest, $device] = $this->seedAdminEventGuest('081234567890');
        $device->update(['is_active' => false]);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Hi',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
    }

    public function test_send_fails_when_guest_has_no_phone(): void
    {
        Http::fake();

        [$admin, $event, $guest] = $this->seedAdminEventGuest(null);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Halo {nama}',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('invitation_sends', [
            'guest_id' => $guest->id,
            'status' => InvitationSend::STATUS_FAILED,
        ]);

        Http::assertNothingSent();
    }

    public function test_bulk_send_continues_after_partial_failure(): void
    {
        Http::fake([
            'scan.flowkirim.com/api/whatsapp/messages/text' => Http::sequence()
                ->push(['id' => 'ok-1'], 200)
                ->push(['error' => 'rate limited'], 429),
        ]);

        $admin = User::factory()->admin()->create();
        $device = WhatsappDevice::query()->create([
            'name' => 'Main',
            'provider_device_id' => 'provider-main',
            'is_active' => true,
        ]);
        $event = Event::query()->create([
            'name' => 'E',
            'slug' => 'e-'.uniqid(),
            'whatsapp_device_id' => $device->id,
        ]);
        $ok = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Ok Guest',
            'phone_number' => '081111111111',
            'guest_type' => 'Regular',
        ]);
        $fail = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Fail Guest',
            'phone_number' => '082222222222',
            'guest_type' => 'Regular',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/send-invitations', [
                'guest_ids' => [$ok->id, $fail->id],
                'message' => 'Undangan {link}',
            ])
            ->assertOk()
            ->assertJsonPath('data.sent_count', 1)
            ->assertJsonPath('data.failed_count', 1);

        $this->assertSame(2, InvitationSend::query()->count());
        $this->assertDatabaseHas('invitation_sends', [
            'guest_id' => $ok->id,
            'status' => InvitationSend::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('invitation_sends', [
            'guest_id' => $fail->id,
            'status' => InvitationSend::STATUS_FAILED,
        ]);
    }

    public function test_admin_can_list_invitation_sends(): void
    {
        Http::fake([
            'scan.flowkirim.com/api/whatsapp/messages/text' => Http::response(['id' => 'm1'], 200),
        ]);

        [$admin, $event, $guest] = $this->seedAdminEventGuest('081234567890');

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Hi {nama}',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/events/'.$event->id.'/invitation-sends')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.guest_id', $guest->id)
            ->assertJsonPath('data.0.device.name', 'Main WA');

        $this->actingAs($admin)
            ->getJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/invitation-sends')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_panitia_cannot_send_invitation(): void
    {
        $panitia = User::factory()->panitia()->create();
        $event = Event::query()->create(['name' => 'E', 'slug' => 'e-'.uniqid()]);
        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'X',
            'phone_number' => '081234567890',
            'guest_type' => 'Regular',
        ]);

        $this->actingAs($panitia)
            ->postJson('/api/events/'.$event->id.'/guests/'.$guest->id.'/send-invitation', [
                'message' => 'Hi',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Event, 2: Guest, 3: WhatsappDevice}
     */
    private function seedAdminEventGuest(?string $phone): array
    {
        $admin = User::factory()->admin()->create();
        $device = WhatsappDevice::query()->create([
            'name' => 'Main WA',
            'provider_device_id' => 'provider-main',
            'phone_label' => '0812',
            'is_active' => true,
        ]);
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
            'whatsapp_device_id' => $device->id,
        ]);
        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Budi Santoso',
            'phone_number' => $phone,
            'guest_type' => 'VIP',
        ]);

        return [$admin, $event, $guest, $device];
    }
}
