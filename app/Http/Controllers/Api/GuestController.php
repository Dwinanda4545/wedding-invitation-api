<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestImportRequest;
use App\Http\Requests\GuestStoreRequest;
use App\Http\Requests\GuestUpdateRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestImportService;
use App\Services\GuestQrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $guests = $event->guests()
            ->with('relation')
            ->orderBy('name')
            ->get()
            ->map(fn (Guest $guest) => $this->guestPayload($guest));

        return response()->json(['data' => $guests]);
    }

    public function store(GuestStoreRequest $request, Event $event, GuestQrCodeService $qr)
    {
        $guest = $event->guests()->create($request->validated());

        $path = $qr->generateAndStore($guest, $guest->secret_token);
        $guest->forceFill(['qr_code_path' => $path])->save();

        return response()->json(['data' => $this->guestPayload($guest->fresh())], 201);
    }

    public function show(Event $event, Guest $guest)
    {
        $this->assertGuestBelongsToEvent($event, $guest);

        return response()->json(['data' => $this->guestPayload($guest)]);
    }

    public function update(GuestUpdateRequest $request, Event $event, Guest $guest)
    {
        $this->assertGuestBelongsToEvent($event, $guest);

        $guest->update($request->validated());

        return response()->json(['data' => $this->guestPayload($guest->fresh())]);
    }

    public function destroy(Event $event, Guest $guest)
    {
        $this->assertGuestBelongsToEvent($event, $guest);

        if ($guest->qr_code_path) {
            Storage::disk('public')->delete($guest->qr_code_path);
        }

        $guest->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function importTemplate(Request $request, GuestImportService $importer): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        if (! in_array($format, ['csv', 'xlsx'], true)) {
            return response()->json(['message' => 'format must be csv or xlsx'], 422);
        }

        try {
            return $importer->downloadTemplate($format);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function import(GuestImportRequest $request, Event $event, GuestImportService $importer, GuestQrCodeService $qr)
    {
        try {
            $result = $importer->import($event, $request->uploadedImportFile(), $qr);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Import finished',
            'created' => $result['created'],
            'skipped_empty_rows' => $result['skipped_empty_rows'],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    public function regenerateQr(Event $event, Guest $guest, GuestQrCodeService $qr)
    {
        $this->assertGuestBelongsToEvent($event, $guest);

        if ($guest->qr_code_path) {
            Storage::disk('public')->delete($guest->qr_code_path);
        }

        $path = $qr->generateAndStore($guest, $guest->secret_token);
        $guest->forceFill(['qr_code_path' => $path])->save();

        return response()->json(['data' => $this->guestPayload($guest->fresh())]);
    }

    protected function assertGuestBelongsToEvent(Event $event, Guest $guest): void
    {
        if ((int) $guest->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    protected function guestPayload(Guest $guest): array
    {
        $guest->loadMissing('relation');
        $frontend = rtrim(config('app.frontend_url', ''), '/');

        return [
            'id' => $guest->id,
            'event_id' => $guest->event_id,
            'name' => $guest->name,
            'phone_number' => $guest->phone_number,
            'guest_type' => $guest->guest_type,
            'guest_relation_id' => $guest->guest_relation_id,
            'relation' => $guest->relation
                ? [
                    'id' => $guest->relation->id,
                    'label' => $guest->relation->label,
                ]
                : null,
            'secret_token' => $guest->secret_token,
            'qr_code_path' => $guest->qr_code_path,
            'qr_code_url' => $guest->qr_code_path ? Storage::disk('public')->url($guest->qr_code_path) : null,
            'invitation_url' => $frontend ? "{$frontend}/invitation/{$guest->secret_token}" : null,
            'is_attended' => $guest->is_attended,
            'scanned_at' => $guest->scanned_at,
            'created_at' => $guest->created_at,
            'updated_at' => $guest->updated_at,
        ];
    }
}
