<?php

namespace Tests\Feature;

use App\Models\EnvelopeTransaction;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Services\DuitkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'duitku.merchant_code' => 'D1234',
            'duitku.api_key' => 'test-api-key',
            'duitku.callback_url' => 'http://localhost/api/duitku/callback',
            'duitku.frontend_url' => 'http://localhost:5173',
        ]);
    }

    /**
     * @return array{event: Event, guest: Guest}
     */
    private function enabledGuest(): array
    {
        $event = Event::query()->create([
            'name' => 'Raka & Sinta',
            'slug' => 'raka-sinta-'.uniqid(),
            'invitation_settings' => [
                'sections' => [
                    'digital_envelope' => true,
                ],
                'digital_envelope' => [
                    'min_amount' => 10000,
                    'max_amount' => 5000000,
                ],
            ],
        ]);

        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Budi Santoso',
            'guest_type' => 'Regular',
        ]);

        return ['event' => $event, 'guest' => $guest];
    }

    public function test_guest_can_create_envelope_transaction(): void
    {
        ['guest' => $guest] = $this->enabledGuest();

        $this->mock(DuitkuService::class, function ($mock) {
            $mock->shouldReceive('createTransaction')
                ->once()
                ->andReturn('https://sandbox.duitku.com/pay/example');
        });

        $this->postJson("/api/invitation/{$guest->secret_token}/digital-envelopes", [
            'sender_name' => 'Budi',
            'amount' => 100000,
            'message' => 'Selamat menempuh hidup baru',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_url', 'https://sandbox.duitku.com/pay/example')
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('envelope_transactions', [
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'status' => 'pending',
        ]);
    }

    public function test_invalid_amount_rejected(): void
    {
        ['guest' => $guest] = $this->enabledGuest();

        $this->postJson("/api/invitation/{$guest->secret_token}/digital-envelopes", [
            'sender_name' => 'Budi',
            'amount' => 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_disabled_section_returns_403(): void
    {
        $event = Event::query()->create([
            'name' => 'Tanpa Amplop',
            'slug' => 'tanpa-amplop-'.uniqid(),
            'invitation_settings' => [
                'sections' => [
                    'digital_envelope' => false,
                ],
            ],
        ]);

        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Ani',
            'guest_type' => 'Regular',
        ]);

        $this->postJson("/api/invitation/{$guest->secret_token}/digital-envelopes", [
            'sender_name' => 'Ani',
            'amount' => 50000,
        ])->assertForbidden();
    }

    public function test_callback_updates_status_to_paid(): void
    {
        ['event' => $event, 'guest' => $guest] = $this->enabledGuest();

        $transaction = EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'order_id' => 'ENV-1-TESTORDER',
            'status' => 'pending',
        ]);

        $merchantCode = 'D1234';
        $amount = '100000';
        $orderId = $transaction->order_id;
        $signature = md5($merchantCode.$amount.$orderId.'test-api-key');

        $this->post('/api/duitku/callback', [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'resultCode' => '00',
            'reference' => 'REF123',
            'paymentCode' => 'QRIS',
            'signature' => $signature,
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('paid', $transaction->status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame('QRIS', $transaction->payment_method);
    }

    public function test_callback_rejects_invalid_signature(): void
    {
        ['event' => $event, 'guest' => $guest] = $this->enabledGuest();

        EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'order_id' => 'ENV-1-BADSIG',
            'status' => 'pending',
        ]);

        $this->post('/api/duitku/callback', [
            'merchantCode' => 'D1234',
            'amount' => '100000',
            'merchantOrderId' => 'ENV-1-BADSIG',
            'resultCode' => '00',
            'signature' => 'invalid',
        ])->assertForbidden();
    }

    public function test_callback_is_idempotent(): void
    {
        ['event' => $event, 'guest' => $guest] = $this->enabledGuest();

        $transaction = EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'order_id' => 'ENV-1-PAIDONCE',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $merchantCode = 'D1234';
        $amount = '100000';
        $orderId = $transaction->order_id;
        $signature = md5($merchantCode.$amount.$orderId.'test-api-key');

        $this->post('/api/duitku/callback', [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'resultCode' => '00',
            'signature' => $signature,
        ])->assertOk();

        $this->assertSame('paid', $transaction->fresh()->status);
    }

    public function test_admin_can_list_transactions(): void
    {
        ['event' => $event, 'guest' => $guest] = $this->enabledGuest();
        $user = User::factory()->create();

        EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'order_id' => 'ENV-1-ADMIN1',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Citra',
            'amount' => 50000,
            'order_id' => 'ENV-1-ADMIN2',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->getJson("/api/events/{$event->id}/envelope-transactions")
            ->assertOk()
            ->assertJsonPath('summary.total_paid_amount', 100000)
            ->assertJsonPath('summary.paid_count', 1)
            ->assertJsonPath('summary.pending_count', 1)
            ->assertJsonCount(2, 'data');
    }

    public function test_public_invitation_excludes_envelope_transactions(): void
    {
        ['event' => $event, 'guest' => $guest] = $this->enabledGuest();

        EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'order_id' => 'ENV-1-PUBLIC',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->getJson("/api/invitation/{$guest->secret_token}")
            ->assertOk();

        $payload = $response->json();
        $this->assertArrayNotHasKey('envelope_transactions', $payload['event'] ?? []);
        $this->assertArrayNotHasKey('envelope_transactions', $payload['guest'] ?? []);
        $this->assertStringNotContainsString('ENV-1-PUBLIC', json_encode($payload));
    }
}
