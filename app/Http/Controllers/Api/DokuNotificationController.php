<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvelopeTransaction;
use App\Services\DokuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DokuNotificationController extends Controller
{
    public function __construct(private readonly DokuService $doku) {}

    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $clientId = (string) $request->header('Client-Id', '');
        $requestId = (string) $request->header('Request-Id', '');
        $requestTimestamp = (string) $request->header('Request-Timestamp', '');
        $signature = (string) $request->header('Signature', '');

        if (! $this->doku->verifyNotificationSignature(
            $clientId,
            $requestId,
            $requestTimestamp,
            $signature,
            $rawBody,
        )) {
            Log::warning('DOKU notification rejected: invalid signature', [
                'request_id' => $requestId,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $invoiceNumber = (string) data_get($payload, 'order.invoice_number', '');
        $remoteStatus = (string) data_get($payload, 'transaction.status', '');

        $transaction = EnvelopeTransaction::query()
            ->where('order_id', $invoiceNumber)
            ->first();

        if (! $transaction) {
            Log::warning('DOKU notification: transaction not found', [
                'invoice_number' => $invoiceNumber,
            ]);

            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'paid') {
            return response()->json(['message' => 'OK']);
        }

        $mapped = $this->doku->mapNotificationStatus($remoteStatus);
        if ($mapped === null) {
            // Checkout: ignore FAILED so guest can retry another method.
            return response()->json(['message' => 'Ignored']);
        }

        $updates = [
            'payment_method' => data_get($payload, 'channel.id')
                ?? data_get($payload, 'acquirer.id')
                ?? $transaction->payment_method,
            'payment_reference' => data_get($payload, 'transaction.original_request_id')
                ?? data_get($payload, 'transaction.id')
                ?? $transaction->payment_reference,
        ];

        if ($mapped === 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = now();
        } elseif ($mapped === 'expired') {
            $updates['status'] = 'expired';
        }

        $transaction->update($updates);

        return response()->json(['message' => 'OK']);
    }
}
