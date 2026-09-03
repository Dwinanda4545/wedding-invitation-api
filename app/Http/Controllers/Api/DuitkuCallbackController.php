<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvelopeTransaction;
use App\Services\DuitkuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DuitkuCallbackController extends Controller
{
    public function __construct(private readonly DuitkuService $duitku) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->duitku->verifyCallbackSignature($payload)) {
            Log::warning('Duitku callback rejected: invalid signature', [
                'merchantOrderId' => $payload['merchantOrderId'] ?? null,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $orderId = (string) ($payload['merchantOrderId'] ?? '');
        $transaction = EnvelopeTransaction::query()
            ->where('order_id', $orderId)
            ->first();

        if (! $transaction) {
            Log::warning('Duitku callback: transaction not found', [
                'merchantOrderId' => $orderId,
            ]);

            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'paid') {
            return response()->json(['message' => 'OK']);
        }

        $resultCode = (string) ($payload['resultCode'] ?? '');
        $mappedStatus = $this->duitku->mapStatus($resultCode);

        $updates = [
            'payment_method' => $payload['paymentCode'] ?? $payload['paymentMethod'] ?? $transaction->payment_method,
            'duitku_reference' => $payload['reference'] ?? $transaction->duitku_reference,
        ];

        if ($mappedStatus === 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = now();
        } elseif ($mappedStatus === 'pending') {
            $updates['status'] = 'pending';
        } else {
            $updates['status'] = 'failed';
        }

        $transaction->update($updates);

        return response()->json(['message' => 'OK']);
    }
}
