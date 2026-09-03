<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvelopeTransaction;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventEnvelopeController extends Controller
{
    public function index(Request $request, Event $event): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        $query = EnvelopeTransaction::query()
            ->forEvent($event->id)
            ->orderByDesc('created_at');

        $summaryQuery = clone $query;

        $paginator = $query->paginate($perPage);

        $paidQuery = (clone $summaryQuery)->paid();

        return response()->json([
            'summary' => [
                'total_paid_amount' => (int) $paidQuery->sum('amount'),
                'paid_count' => (int) (clone $summaryQuery)->paid()->count(),
                'pending_count' => (int) (clone $summaryQuery)->where('status', 'pending')->count(),
            ],
            'data' => collect($paginator->items())->map(fn (EnvelopeTransaction $tx) => [
                'id' => $tx->id,
                'sender_name' => $tx->sender_name,
                'sender_email' => $tx->sender_email,
                'sender_phone' => $tx->sender_phone,
                'amount' => $tx->amount,
                'message' => $tx->message,
                'payment_method' => $tx->payment_method,
                'status' => $tx->status,
                'order_id' => $tx->order_id,
                'paid_at' => $tx->paid_at,
                'created_at' => $tx->created_at,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
