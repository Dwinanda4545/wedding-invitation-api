<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Guest;
use App\Models\InvitationSend;
use App\Models\User;
use App\Models\WhatsappDevice;
use RuntimeException;
use Throwable;

class InvitationWhatsappService
{
    public function __construct(
        private readonly FlowkirimService $flowkirim,
    ) {}

    /**
     * @return array{send: InvitationSend, guest_id: int, status: string, error_message: string|null}
     */
    public function sendToGuest(
        Event $event,
        Guest $guest,
        string $messageTemplate,
        User $sender,
        ?int $deviceId = null,
    ): array {
        if ((int) $guest->event_id !== (int) $event->id) {
            abort(404);
        }

        return $this->attemptSend($event, $guest, $messageTemplate, $sender, $deviceId);
    }

    /**
     * @param  list<int>  $guestIds
     * @return array{
     *     sent_count: int,
     *     failed_count: int,
     *     results: list<array{send: InvitationSend|null, guest_id: int, status: string, error_message: string|null}>
     * }
     */
    public function sendToGuests(
        Event $event,
        array $guestIds,
        string $messageTemplate,
        User $sender,
        ?int $deviceId = null,
    ): array {
        $guests = $event->guests()
            ->whereIn('id', $guestIds)
            ->get()
            ->keyBy('id');

        $results = [];
        $sentCount = 0;
        $failedCount = 0;

        foreach ($guestIds as $guestId) {
            /** @var Guest|null $guest */
            $guest = $guests->get($guestId);

            if ($guest === null) {
                $results[] = [
                    'send' => null,
                    'guest_id' => (int) $guestId,
                    'status' => InvitationSend::STATUS_FAILED,
                    'error_message' => 'Guest not found for this event.',
                ];
                $failedCount++;

                continue;
            }

            $result = $this->attemptSend($event, $guest, $messageTemplate, $sender, $deviceId);
            $results[] = $result;

            if ($result['status'] === InvitationSend::STATUS_SENT) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    public function invitationUrl(Guest $guest): string
    {
        $frontend = rtrim((string) config('app.frontend_url', ''), '/');

        if ($frontend === '') {
            return '/invitation/'.$guest->secret_token;
        }

        // WhatsApp only auto-linkifies absolute URLs with a scheme.
        if (! preg_match('#^https?://#i', $frontend)) {
            $frontend = 'https://'.$frontend;
        }

        return $frontend.'/invitation/'.$guest->secret_token;
    }

    public function renderMessage(string $template, Guest $guest): string
    {
        $link = $this->invitationUrl($guest);

        // Keep the URL on its own token boundaries so WhatsApp detects it as a hyperlink.
        $message = str_replace(
            ['{nama}', '{link}'],
            [$guest->name, $link],
            $template,
        );

        return $this->ensureLinkIsStandalone($message, $link);
    }

    /**
     * Ensure the invitation URL is surrounded by whitespace/newlines for WhatsApp URL detection.
     */
    private function ensureLinkIsStandalone(string $message, string $link): string
    {
        if ($link === '' || ! str_contains($message, $link)) {
            return $message;
        }

        // If the link is already alone on a line, leave it.
        if (preg_match('/(^|\n)\s*'.preg_quote($link, '/').'\s*($|\n)/', $message) === 1) {
            return $message;
        }

        // Replace inline "...text https://... more..." with link on its own line.
        return preg_replace(
            '/\s*'.preg_quote($link, '/').'\s*/',
            "\n\n".$link."\n\n",
            $message,
            1,
        ) ?? $message;
    }

    /**
     * Normalize Indonesian phone numbers to digits starting with 62.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        } elseif (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);
        }

        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * @return WhatsappDevice|string Error message string when unresolved
     */
    public function resolveDevice(Event $event, ?int $localDeviceId): WhatsappDevice|string
    {
        $id = $localDeviceId ?? $event->whatsapp_device_id;

        if ($id === null) {
            return 'No WhatsApp device selected. Set an event default or pass device_id.';
        }

        $device = WhatsappDevice::query()->find($id);

        if ($device === null) {
            return 'WhatsApp device not found.';
        }

        if (! $device->is_active) {
            return 'WhatsApp device is inactive.';
        }

        return $device;
    }

    /**
     * @return array{send: InvitationSend, guest_id: int, status: string, error_message: string|null}
     */
    private function attemptSend(
        Event $event,
        Guest $guest,
        string $messageTemplate,
        User $sender,
        ?int $deviceId = null,
    ): array {
        $messageBody = $this->renderMessage($messageTemplate, $guest);
        $phone = $this->normalizePhone($guest->phone_number);
        $resolved = $this->resolveDevice($event, $deviceId);
        $device = $resolved instanceof WhatsappDevice ? $resolved : null;

        $send = InvitationSend::query()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'phone_number' => $phone,
            'message_body' => $messageBody,
            'status' => InvitationSend::STATUS_PENDING,
            'provider' => InvitationSend::PROVIDER_FLOWKIRIM,
            'sent_by' => $sender->id,
            'whatsapp_device_id' => $device?->id,
        ]);

        if (is_string($resolved)) {
            return $this->markFailed($send, $resolved);
        }

        if ($phone === null) {
            return $this->markFailed($send, 'Guest has no valid phone number.');
        }

        if (! $this->flowkirim->isConfigured()) {
            return $this->markFailed($send, 'FlowKirim is not configured.');
        }

        try {
            $response = $this->flowkirim->sendText(
                $phone,
                $messageBody,
                $device->provider_device_id,
            );
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unexpected error: '.$e->getMessage();

            return $this->markFailed($send, $this->truncate($message));
        }

        $send->forceFill([
            'status' => InvitationSend::STATUS_SENT,
            'provider_message_id' => $response['provider_message_id'],
            'error_message' => null,
            'sent_at' => now(),
        ])->save();

        return [
            'send' => $send->fresh(),
            'guest_id' => $guest->id,
            'status' => InvitationSend::STATUS_SENT,
            'error_message' => null,
        ];
    }

    /**
     * @return array{send: InvitationSend, guest_id: int, status: string, error_message: string|null}
     */
    private function markFailed(InvitationSend $send, string $error): array
    {
        $send->forceFill([
            'status' => InvitationSend::STATUS_FAILED,
            'error_message' => $this->truncate($error),
            'sent_at' => null,
        ])->save();

        return [
            'send' => $send->fresh(),
            'guest_id' => (int) $send->guest_id,
            'status' => InvitationSend::STATUS_FAILED,
            'error_message' => $send->error_message,
        ];
    }

    private function truncate(string $message, int $max = 1000): string
    {
        return strlen($message) > $max ? substr($message, 0, $max).'…' : $message;
    }
}
