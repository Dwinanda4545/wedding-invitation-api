<?php

namespace App\Services;

use App\Models\EnvelopeTransaction;
use App\Models\Guest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DuitkuService
{
    public function createTransaction(EnvelopeTransaction $transaction, Guest $guest): string
    {
        $merchantCode = (string) config('duitku.merchant_code');
        $apiKey = (string) config('duitku.api_key');

        if ($merchantCode === '' || $apiKey === '') {
            throw new RuntimeException('Duitku is not configured.');
        }

        $transaction->loadMissing('event');

        $amount = (int) $transaction->amount;
        $orderId = $transaction->order_id;
        $signature = md5($merchantCode.$orderId.$amount.$apiKey);

        $frontendUrl = rtrim((string) config('duitku.frontend_url'), '/');
        $returnUrl = $frontendUrl.'/invitation/'.$guest->secret_token
            .'?order_id='.urlencode($orderId);

        $payload = [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'merchantOrderId' => $orderId,
            'productDetails' => 'Amplop Digital - '.($transaction->event->name ?? 'Undangan'),
            'customerVaName' => $transaction->sender_name,
            'email' => $transaction->sender_email ?? '',
            'phoneNumber' => $transaction->sender_phone ?? '',
            'callbackUrl' => (string) config('duitku.callback_url'),
            'returnUrl' => $returnUrl,
            'signature' => $signature,
            'expiryPeriod' => 60,
        ];

        $url = rtrim((string) config('duitku.base_url'), '/')
            .'/webapi/api/merchant/v2/inquiry';

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Duitku request failed: '.$response->body());
        }

        $body = $response->json();
        $statusCode = (string) ($body['statusCode'] ?? '');
        $paymentUrl = $body['paymentUrl'] ?? null;

        if ($statusCode !== '00' || ! is_string($paymentUrl) || $paymentUrl === '') {
            $message = is_string($body['statusMessage'] ?? null)
                ? $body['statusMessage']
                : 'Unknown Duitku error';

            throw new RuntimeException($message);
        }

        return $paymentUrl;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallbackSignature(array $payload): bool
    {
        $merchantCode = (string) config('duitku.merchant_code');
        $apiKey = (string) config('duitku.api_key');
        $signature = (string) ($payload['signature'] ?? '');
        $amount = (string) ($payload['amount'] ?? '');
        $merchantOrderId = (string) ($payload['merchantOrderId'] ?? '');

        if ($merchantCode === '' || $apiKey === '' || $signature === '') {
            return false;
        }

        $expected = md5($merchantCode.$amount.$merchantOrderId.$apiKey);

        return hash_equals($expected, $signature);
    }

    public function mapStatus(string $resultCode): string
    {
        return match ($resultCode) {
            '00' => 'paid',
            '01' => 'pending',
            default => 'failed',
        };
    }
}
