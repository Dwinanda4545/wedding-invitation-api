<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvelopeTransaction;
use App\Models\Event;
use App\Services\DokuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventEnvelopeController extends Controller
{
    public function __construct(private readonly DokuService $doku) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $synced = 0;
        if ($request->boolean('sync', true)) {
            $synced = $this->doku->syncPendingForEvent($event->id);
        }

        $query = EnvelopeTransaction::query()
            ->forEvent($event->id)
            ->with(['guest.relation'])
            ->orderByDesc('created_at');

        if ($request->filled('relation_id')) {
            $relationId = $request->input('relation_id');
            if ($relationId === 'none' || $relationId === 'null') {
                $query->where(function ($q) {
                    $q->whereNull('guest_id')
                        ->orWhereHas('guest', fn ($gq) => $gq->whereNull('guest_relation_id'));
                });
            } else {
                $query->whereHas('guest', fn ($gq) => $gq->where('guest_relation_id', (int) $relationId));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $summaryQuery = clone $query;
        $paidQuery = (clone $summaryQuery)->paid();

        $summary = [
            'total_paid_amount' => (int) $paidQuery->sum('amount'),
            'paid_count' => (int) (clone $summaryQuery)->paid()->count(),
            'pending_count' => (int) (clone $summaryQuery)->where('status', 'pending')->count(),
        ];

        $transform = fn (EnvelopeTransaction $tx) => [
            'id' => $tx->id,
            'guest_id' => $tx->guest_id,
            'guest_name' => $tx->guest?->name,
            'relation_id' => $tx->guest?->guest_relation_id,
            'relation' => $tx->guest?->relation ? [
                'id' => $tx->guest->relation->id,
                'label' => $tx->guest->relation->label,
            ] : null,
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
        ];

        $isAll = $request->boolean('all') || $request->input('per_page') === 'all';

        if ($isAll) {
            $items = $query->get();

            return response()->json([
                'summary' => $summary,
                'synced' => $synced,
                'data' => $items->map($transform),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $items->count(),
                    'total' => $items->count(),
                ],
            ]);
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'summary' => $summary,
            'synced' => $synced,
            'data' => collect($paginator->items())->map($transform),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
