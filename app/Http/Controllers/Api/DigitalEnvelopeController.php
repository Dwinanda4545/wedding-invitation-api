<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DigitalEnvelopeStoreRequest;
use App\Models\EnvelopeTransaction;
use App\Models\Guest;
use App\Services\DokuService;
use App\Support\EnvelopeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;

class DigitalEnvelopeController extends Controller
{
    public function __construct(private readonly DokuService $doku) {}

    public function store(DigitalEnvelopeStoreRequest $request, string $secret_token): JsonResponse
    {
        $guest = Guest::query()
            ->where('secret_token', $secret_token)
            ->with('event')
            ->first();

        if (! $guest || ! $guest->event) {
            return response()->json(['message' => 'Invitation not found'], 404);
        }

        $envelopeSettings = EnvelopeSettings::fromEvent($guest->event);

        if (! $envelopeSettings['enabled']) {
            return response()->json(['message' => 'Amplop digital tidak tersedia'], 403);
        }

        $orderId = sprintf('ENV-%d-%s', $guest->event_id, Str::lower(Str::ulid()));

        $transaction = EnvelopeTransaction::create([
            'event_id' => $guest->event_id,
            'guest_id' => $guest->id,
            'sender_name' => $request->input('sender_name'),
            'sender_email' => $request->input('sender_email'),
            'sender_phone' => $request->input('sender_phone'),
            'amount' => (int) $request->input('amount'),
            'message' => $request->input('message'),
            'order_id' => $orderId,
            'status' => 'pending',
        ]);

        try {
            $paymentUrl = $this->doku->createTransaction($transaction, $guest);
        } catch (RuntimeException $e) {
            $transaction->update(['status' => 'failed']);

            report($e);

            return response()->json([
                'message' => 'Payment service unavailable',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        $transaction->refresh();
        $transaction->update(['payment_url' => $paymentUrl]);

        return response()->json([
            'data' => [
                'order_id' => $transaction->order_id,
                'payment_url' => $paymentUrl,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
            ],
        ], 201);
    }
}
