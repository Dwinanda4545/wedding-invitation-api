<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestImportRequest;
use App\Http\Requests\GuestStoreRequest;
use App\Http\Requests\GuestUpdateRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestQrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuestController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $guests = $event->guests()
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

    public function import(GuestImportRequest $request, Event $event, GuestQrCodeService $qr)
    {
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return response()->json(['message' => 'Could not read CSV file'], 422);
        }

        $headerLine = fgetcsv($handle);
        if ($headerLine === false) {
            fclose($handle);

            return response()->json(['message' => 'CSV is empty'], 422);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $headerLine);

        $idxName = array_search('name', $header, true);
        $idxPhone = array_search('phone_number', $header, true);
        $idxType = array_search('guest_type', $header, true);

        if ($idxName === false) {
            fclose($handle);

            return response()->json(['message' => 'CSV must include a name column'], 422);
        }

        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[$idxName] ?? ''));
            if ($name === '') {
                $skipped++;

                continue;
            }

            $phone = $idxPhone !== false ? trim((string) ($row[$idxPhone] ?? '')) : null;
            $typeRaw = $idxType !== false ? strtoupper(trim((string) ($row[$idxType] ?? ''))) : '';
            $guestType = $typeRaw === 'VIP' ? 'VIP' : 'Regular';

            $guest = $event->guests()->create([
                'name' => $name,
                'phone_number' => $phone ?: null,
                'guest_type' => $guestType,
            ]);

            $relative = $qr->generateAndStore($guest, $guest->secret_token);
            $guest->forceFill(['qr_code_path' => $relative])->save();

            $created++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import finished',
            'created' => $created,
            'skipped_empty_rows' => $skipped,
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
        $frontend = rtrim(config('app.frontend_url', ''), '/');

        return [
            'id' => $guest->id,
            'event_id' => $guest->event_id,
            'name' => $guest->name,
            'phone_number' => $guest->phone_number,
            'guest_type' => $guest->guest_type,
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
