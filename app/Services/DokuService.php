<?php

namespace App\Services;

use App\Models\EnvelopeTransaction;
use App\Models\Guest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DokuService
{
    public function createTransaction(EnvelopeTransaction $transaction, Guest $guest): string
    {
        $this->assertConfigured();

        $transaction->loadMissing('event');

        $amount = (int) $transaction->amount;
        $invoiceNumber = $transaction->order_id;
        $productName = $this->sanitizeDokuText(
            'Amplop Digital - '.($transaction->event->name ?? 'Undangan'),
            255,
        );

        $frontendUrl = rtrim((string) config('doku.frontend_url'), '/');
        $returnUrl = $frontendUrl.'/invitation/'.$guest->secret_token
            .'?order_id='.urlencode($invoiceNumber);

        $customer = [
            'name' => $this->sanitizeDokuText((string) $transaction->sender_name, 100),
            'email' => $this->customerEmail($transaction),
        ];

        $phone = $this->customerPhone($transaction);
        if ($phone !== null) {
            $customer['phone'] = $phone;
        }

        $payload = [
            'order' => [
                'amount' => $amount,
                'invoice_number' => $invoiceNumber,
                'currency' => 'IDR',
                'callback_url' => $returnUrl,
                'callback_url_result' => $returnUrl,
                'auto_redirect' => true,
                'language' => 'ID',
                'line_items' => [
                    [
                        'name' => $productName,
                        'price' => $amount,
                        'quantity' => 1,
                    ],
                ],
            ],
            'payment' => [
                'payment_due_date' => 60,
            ],
            'customer' => $customer,
        ];

        $requestId = (string) Str::uuid();
        $response = $this->signedRequest(
            'POST',
            '/checkout/v1/payment',
            $payload,
            $requestId,
        );

        if (! $response->successful()) {
            throw new RuntimeException('DOKU request failed: '.$response->body());
        }

        $body = $response->json() ?? [];
        $paymentUrl = data_get($body, 'response.payment.url')
            ?? data_get($body, 'payment.url');

        if (! is_string($paymentUrl) || $paymentUrl === '') {
            throw new RuntimeException('DOKU response missing payment.url: '.$response->body());
        }

        $transaction->forceFill([
            'payment_reference' => $requestId,
        ])->save();

        return $paymentUrl;
    }

    /**
     * @return array{status: string, payment_method: string|null, reference: string|null}
     */
    public function checkTransactionStatus(string $invoiceNumber): array
    {
        $this->assertConfigured();

        $path = '/orders/v1/status/'.rawurlencode($invoiceNumber);
        $response = $this->signedRequest('GET', $path, null, (string) Str::uuid());

        if (! $response->successful()) {
            throw new RuntimeException('DOKU status check failed: '.$response->body());
        }

        $body = $response->json() ?? [];

        return [
            'status' => (string) data_get($body, 'transaction.status', ''),
            'payment_method' => $this->extractPaymentMethod($body),
            'reference' => $this->extractReference($body),
        ];
    }

    public function syncTransaction(EnvelopeTransaction $transaction): bool
    {
        if ($transaction->status === 'paid') {
            return false;
        }

        try {
            $remote = $this->checkTransactionStatus($transaction->order_id);
        } catch (RuntimeException) {
            return false;
        }

        $mapped = $this->mapRemoteStatus($remote['status']);
        if ($mapped === 'pending' && $transaction->status === 'pending') {
            if ($remote['reference'] && $transaction->payment_reference !== $remote['reference']) {
                $transaction->update(['payment_reference' => $remote['reference']]);

                return true;
            }

            return false;
        }

        $updates = [];

        if ($remote['reference']) {
            $updates['payment_reference'] = $remote['reference'];
        }
        if ($remote['payment_method']) {
            $updates['payment_method'] = $remote['payment_method'];
        }

        if ($mapped === 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = $transaction->paid_at ?? now();
        } elseif ($mapped === 'failed' && $transaction->status !== 'failed') {
            $updates['status'] = 'failed';
        } elseif ($mapped === 'expired' && $transaction->status !== 'expired') {
            $updates['status'] = 'expired';
        }

        if ($updates === []) {
            return false;
        }

        $transaction->update($updates);

        return true;
    }

    public function syncPendingForEvent(int $eventId, int $limit = 15): int
    {
        $transactions = EnvelopeTransaction::query()
            ->forEvent($eventId)
            ->whereIn('status', ['pending', 'failed'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $updated = 0;
        foreach ($transactions as $transaction) {
            if ($this->syncTransaction($transaction)) {
                $updated++;
            }
        }

        return $updated;
    }

    public function verifyNotificationSignature(
        string $clientId,
        string $requestId,
        string $requestTimestamp,
        string $signatureHeader,
        string $rawBody,
    ): bool {
        $secret = (string) config('doku.secret_key');
        $expectedClientId = (string) config('doku.client_id');

        if ($secret === '' || $expectedClientId === '' || $signatureHeader === '') {
            return false;
        }

        if (! hash_equals($expectedClientId, $clientId)) {
            return false;
        }

        $path = (string) config('doku.notification_path', '/api/doku/notification');
        $expected = $this->buildSignature(
            $clientId,
            $requestId,
            $requestTimestamp,
            $path,
            $this->digest($rawBody),
        );

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Checkout may send FAILED while guest retries another method — ignore those.
     */
    public function mapNotificationStatus(string $status): ?string
    {
        return match (strtoupper($status)) {
            'SUCCESS' => 'paid',
            'EXPIRED' => 'expired',
            'FAILED' => null,
            default => null,
        };
    }

    public function mapRemoteStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS' => 'paid',
            'PENDING', 'REDIRECT' => 'pending',
            'EXPIRED' => 'expired',
            default => 'failed',
        };
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function signedRequest(string $method, string $path, ?array $payload, string $requestId): Response
    {
        $clientId = (string) config('doku.client_id');
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new RuntimeException('Failed to encode DOKU payload.');
        }

        $digest = $method === 'GET' || $method === 'DELETE' ? null : $this->digest($body);
        $signature = $this->buildSignature($clientId, $requestId, $timestamp, $path, $digest);

        $headers = [
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
        ];

        $url = rtrim((string) config('doku.base_url'), '/').$path;

        $pending = Http::acceptJson()
            ->timeout(30)
            ->withHeaders($headers);

        if ($method === 'GET') {
            return $pending->get($url);
        }

        return $pending
            ->withBody($body, 'application/json')
            ->post($url);
    }

    private function buildSignature(
        string $clientId,
        string $requestId,
        string $requestTimestamp,
        string $requestTarget,
        ?string $digest,
    ): string {
        $lines = [
            'Client-Id:'.$clientId,
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$requestTimestamp,
            'Request-Target:'.$requestTarget,
        ];

        if ($digest !== null && $digest !== '') {
            $lines[] = 'Digest:'.$digest;
        }

        $component = implode("\n", $lines);
        $secret = (string) config('doku.secret_key');
        $hash = base64_encode(hash_hmac('sha256', $component, $secret, true));

        return 'HMACSHA256='.$hash;
    }

    private function digest(string $rawBody): string
    {
        return base64_encode(hash('sha256', $rawBody, true));
    }

    private function assertConfigured(): void
    {
        if ((string) config('doku.client_id') === '' || (string) config('doku.secret_key') === '') {
            throw new RuntimeException('DOKU is not configured.');
        }
    }

    /**
     * DOKU Checkout rejects text outside: a-z A-Z 0-9 . - / + , = _ : ' @ % ( ) and space.
     */
    private function sanitizeDokuText(string $value, int $maxLength): string
    {
        $value = str_replace(['&', '#'], [' dan ', ' '], $value);
        $value = preg_replace("/[^a-zA-Z0-9 .\\-\/+,=_:'@%()]/", ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value);

        if ($value === '') {
            return 'Guest';
        }

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function customerEmail(EnvelopeTransaction $transaction): string
    {
        $email = trim((string) ($transaction->sender_email ?? ''));
        if ($email === '') {
            return 'noreply@local.dev';
        }

        return strlen($email) > 50 ? substr($email, 0, 50) : $email;
    }

    /**
     * DOKU requires phone as 1-20 digits when present; omit if empty/invalid.
     */
    private function customerPhone(EnvelopeTransaction $transaction): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($transaction->sender_phone ?? '')) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 20) {
            $digits = substr($digits, 0, 20);
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractPaymentMethod(array $body): ?string
    {
        $candidates = [
            data_get($body, 'channel.id'),
            data_get($body, 'acquirer.id'),
            data_get($body, 'service.id'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractReference(array $body): ?string
    {
        $candidates = [
            data_get($body, 'transaction.original_request_id'),
            data_get($body, 'transaction.id'),
            data_get($body, 'order.invoice_number'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
