<?php

namespace Tests\Feature;

use App\Models\EnvelopeTransaction;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Services\DokuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigitalEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doku.client_id' => 'MCH-TEST-CLIENT',
            'doku.secret_key' => 'test-secret-key',
            'doku.frontend_url' => 'http://localhost:5173',
            'doku.base_url' => 'https://api-sandbox.doku.com',
            'doku.notification_path' => '/api/doku/notification',
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

        $this->mock(DokuService::class, function ($mock) {
            $mock->shouldReceive('createTransaction')
                ->once()
                ->andReturn('https://sandbox.doku.com/checkout/example');
        });

        $this->postJson("/api/invitation/{$guest->secret_token}/digital-envelopes", [
            'sender_name' => 'Budi',
            'amount' => 100000,
            'message' => 'Selamat menempuh hidup baru',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_url', 'https://sandbox.doku.com/checkout/example')
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('envelope_transactions', [
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'status' => 'pending',
        ]);
    }

    public function test_doku_payload_strips_invalid_characters(): void
    {
        ['guest' => $guest] = $this->enabledGuest();

        Http::fake([
            '*/checkout/v1/payment' => Http::response([
                'response' => [
                    'payment' => [
                        'url' => 'https://sandbox.doku.com/checkout/sanitized',
                    ],
                ],
            ], 200),
        ]);

        $this->postJson("/api/invitation/{$guest->secret_token}/digital-envelopes", [
            'sender_name' => 'Budi & Keluarga #1',
            'amount' => 100000,
        ])->assertCreated();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if (! str_contains($request->url(), '/checkout/v1/payment')) {
                return false;
            }

            $payload = $request->data();
            $itemName = data_get($payload, 'order.line_items.0.name');
            $customerName = data_get($payload, 'customer.name');

            $this->assertIsString($itemName);
            $this->assertIsString($customerName);
            $this->assertDoesNotMatchRegularExpression('/[^a-zA-Z0-9 .\\-\\/+ ,=_:\'@%()]/', $itemName);
            $this->assertDoesNotMatchRegularExpression('/[^a-zA-Z0-9 .\\-\\/+ ,=_:\'@%()]/', $customerName);
            $this->assertStringNotContainsString('&', $itemName);
            $this->assertStringNotContainsString('#', $customerName);
            $this->assertStringContainsString('Amplop Digital', $itemName);
            $this->assertStringContainsString('Raka', $itemName);
            $this->assertStringContainsString('Sinta', $itemName);

            return true;
        });
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

    public function test_notification_updates_status_to_paid(): void
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

        $payload = [
            'order' => [
                'invoice_number' => $transaction->order_id,
                'amount' => 100000,
            ],
            'transaction' => [
                'status' => 'SUCCESS',
                'original_request_id' => 'req-123',
            ],
            'channel' => [
                'id' => 'VIRTUAL_ACCOUNT',
            ],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = $this->dokuNotificationHeaders($rawBody);

        $this->call(
            'POST',
            '/api/doku/notification',
            [],
            [],
            [],
            $this->transformHeadersToServerVars(array_merge([
                'CONTENT_TYPE' => 'application/json',
                'Accept' => 'application/json',
            ], $headers)),
            $rawBody,
        )->assertOk();

        $transaction->refresh();
        $this->assertSame('paid', $transaction->status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame('VIRTUAL_ACCOUNT', $transaction->payment_method);
        $this->assertSame('req-123', $transaction->payment_reference);
    }

    public function test_notification_rejects_invalid_signature(): void
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

        $payload = [
            'order' => ['invoice_number' => 'ENV-1-BADSIG'],
            'transaction' => ['status' => 'SUCCESS'],
        ];
        $rawBody = json_encode($payload);

        $this->call(
            'POST',
            '/api/doku/notification',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'CONTENT_TYPE' => 'application/json',
                'Accept' => 'application/json',
                'Client-Id' => 'MCH-TEST-CLIENT',
                'Request-Id' => 'abc',
                'Request-Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'Signature' => 'HMACSHA256=invalid',
            ]),
            $rawBody,
        )->assertForbidden();
    }

    public function test_notification_is_idempotent(): void
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

        $payload = [
            'order' => ['invoice_number' => $transaction->order_id],
            'transaction' => ['status' => 'SUCCESS'],
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = $this->dokuNotificationHeaders($rawBody);

        $this->call(
            'POST',
            '/api/doku/notification',
            [],
            [],
            [],
            $this->transformHeadersToServerVars(array_merge([
                'CONTENT_TYPE' => 'application/json',
                'Accept' => 'application/json',
            ], $headers)),
            $rawBody,
        )->assertOk();

        $this->assertSame('paid', $transaction->fresh()->status);
    }

    public function test_admin_list_syncs_paid_status_from_doku(): void
    {
        ['event' => $event, 'guest' => $guest] = $this->enabledGuest();
        $user = User::factory()->create();

        $transaction = EnvelopeTransaction::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Budi',
            'amount' => 100000,
            'order_id' => 'ENV-1-SYNC-PAID',
            'status' => 'pending',
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($transaction) {
            if (! str_contains($request->url(), '/orders/v1/status/')) {
                return Http::response(['message' => 'unexpected '.$request->url()], 500);
            }

            return Http::response([
                'order' => ['invoice_number' => $transaction->order_id],
                'transaction' => [
                    'status' => 'SUCCESS',
                    'original_request_id' => 'DOKU-SYNC-REF',
                ],
                'channel' => ['id' => 'QRIS'],
            ], 200);
        });

        $this->actingAs($user)
            ->getJson("/api/events/{$event->id}/envelope-transactions")
            ->assertOk()
            ->assertJsonPath('synced', 1)
            ->assertJsonPath('summary.paid_count', 1)
            ->assertJsonPath('summary.pending_count', 0)
            ->assertJsonPath('data.0.status', 'paid');

        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('DOKU-SYNC-REF', $transaction->fresh()->payment_reference);
        $this->assertNotNull($transaction->fresh()->paid_at);
    }

    public function test_admin_can_list_transactions(): void
    {
        Http::fake([
            '*/orders/v1/status/*' => Http::response([
                'transaction' => ['status' => 'PENDING'],
            ], 200),
        ]);

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

    /**
     * @return array<string, string>
     */
    private function dokuNotificationHeaders(string $rawBody): array
    {
        $clientId = 'MCH-TEST-CLIENT';
        $requestId = 'notif-req-1';
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $digest = base64_encode(hash('sha256', $rawBody, true));
        $component = implode("\n", [
            'Client-Id:'.$clientId,
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:/api/doku/notification',
            'Digest:'.$digest,
        ]);
        $signature = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $component, 'test-secret-key', true));

        return [
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
        ];
    }
}
