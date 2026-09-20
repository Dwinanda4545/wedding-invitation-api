<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkSendInvitationRequest;
use App\Http\Requests\SendInvitationRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\InvitationSend;
use App\Services\InvitationWhatsappService;
use Illuminate\Http\Request;

class InvitationSendController extends Controller
{
    public function sendOne(
        SendInvitationRequest $request,
        Event $event,
        Guest $guest,
        InvitationWhatsappService $service,
    ) {
        $this->assertGuestBelongsToEvent($event, $guest);

        $result = $service->sendToGuest(
            $event,
            $guest,
            $request->validated('message'),
            $request->user(),
            $request->validated('device_id'),
        );

        $statusCode = $result['status'] === InvitationSend::STATUS_SENT ? 200 : 422;

        return response()->json([
            'data' => $this->sendPayload($result['send']),
        ], $statusCode);
    }

    public function sendBulk(
        BulkSendInvitationRequest $request,
        Event $event,
        InvitationWhatsappService $service,
    ) {
        $validated = $request->validated();

        $outcome = $service->sendToGuests(
            $event,
            array_map('intval', $validated['guest_ids']),
            $validated['message'],
            $request->user(),
            isset($validated['device_id']) ? (int) $validated['device_id'] : null,
        );

        $results = array_map(function (array $row) {
            return [
                'guest_id' => $row['guest_id'],
                'status' => $row['status'],
                'error_message' => $row['error_message'],
                'send' => $row['send'] instanceof InvitationSend
                    ? $this->sendPayload($row['send'])
                    : null,
            ];
        }, $outcome['results']);

        return response()->json([
            'data' => [
                'sent_count' => $outcome['sent_count'],
                'failed_count' => $outcome['failed_count'],
                'results' => $results,
            ],
        ]);
    }

    public function summary(Event $event)
    {
        $guestsTotal = $event->guests()->count();

        $latestIds = InvitationSend::query()
            ->selectRaw('MAX(id) as id')
            ->where('event_id', $event->id)
            ->groupBy('guest_id')
            ->pluck('id');

        $latestSent = 0;
        $latestFailed = 0;
        $latestPending = 0;

        if ($latestIds->isNotEmpty()) {
            $latestCounts = InvitationSend::query()
                ->whereIn('id', $latestIds)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $latestSent = (int) ($latestCounts[InvitationSend::STATUS_SENT] ?? 0);
            $latestFailed = (int) ($latestCounts[InvitationSend::STATUS_FAILED] ?? 0);
            $latestPending = (int) ($latestCounts[InvitationSend::STATUS_PENDING] ?? 0);
        }

        $attemptCounts = InvitationSend::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return response()->json([
            'data' => [
                'guests_total' => $guestsTotal,
                'never_sent' => max(0, $guestsTotal - $latestIds->count()),
                'latest_sent' => $latestSent,
                'latest_failed' => $latestFailed,
                'latest_pending' => $latestPending,
                'attempts_sent' => (int) ($attemptCounts[InvitationSend::STATUS_SENT] ?? 0),
                'attempts_failed' => (int) ($attemptCounts[InvitationSend::STATUS_FAILED] ?? 0),
                'attempts_pending' => (int) ($attemptCounts[InvitationSend::STATUS_PENDING] ?? 0),
            ],
        ]);
    }

    public function indexForEvent(Request $request, Event $event)
    {
        $query = $event->invitationSends()->with([
            'guest:id,name,phone_number',
            'whatsappDevice:id,name,provider_device_id,phone_label,is_active',
        ]);

        if ($request->filled('guest_id')) {
            $query->where('guest_id', (int) $request->query('guest_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $sends = $query->orderByDesc('id')->paginate(
            min(100, max(1, (int) $request->query('per_page', 25)))
        );

        return response()->json([
            'data' => $sends->getCollection()->map(fn (InvitationSend $send) => $this->sendPayload($send))->values(),
            'meta' => [
                'current_page' => $sends->currentPage(),
                'last_page' => $sends->lastPage(),
                'per_page' => $sends->perPage(),
                'total' => $sends->total(),
            ],
        ]);
    }

    public function indexForGuest(Event $event, Guest $guest)
    {
        $this->assertGuestBelongsToEvent($event, $guest);

        $sends = $guest->invitationSends()
            ->with('whatsappDevice:id,name,provider_device_id,phone_label,is_active')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InvitationSend $send) => $this->sendPayload($send));

        return response()->json(['data' => $sends]);
    }

    protected function assertGuestBelongsToEvent(Event $event, Guest $guest): void
    {
        if ((int) $guest->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    protected function sendPayload(InvitationSend $send): array
    {
        if (! $send->relationLoaded('whatsappDevice')) {
            $send->load('whatsappDevice:id,name,provider_device_id,phone_label,is_active');
        }

        return [
            'id' => $send->id,
            'event_id' => $send->event_id,
            'guest_id' => $send->guest_id,
            'phone_number' => $send->phone_number,
            'message_body' => $send->message_body,
            'status' => $send->status,
            'provider' => $send->provider,
            'provider_message_id' => $send->provider_message_id,
            'error_message' => $send->error_message,
            'sent_by' => $send->sent_by,
            'whatsapp_device_id' => $send->whatsapp_device_id,
            'sent_at' => $send->sent_at,
            'created_at' => $send->created_at,
            'updated_at' => $send->updated_at,
            'guest' => $send->relationLoaded('guest') && $send->guest
                ? [
                    'id' => $send->guest->id,
                    'name' => $send->guest->name,
                    'phone_number' => $send->guest->phone_number,
                ]
                : null,
            'device' => $send->whatsappDevice
                ? [
                    'id' => $send->whatsappDevice->id,
                    'name' => $send->whatsappDevice->name,
                    'provider_device_id' => $send->whatsappDevice->provider_device_id,
                    'phone_label' => $send->whatsappDevice->phone_label,
                    'is_active' => $send->whatsappDevice->is_active,
                ]
                : null,
        ];
    }
}
