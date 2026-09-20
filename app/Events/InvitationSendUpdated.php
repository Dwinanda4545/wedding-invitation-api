<?php

namespace App\Events;

use App\Models\InvitationSend;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationSendUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public InvitationSend $send) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('event.'.$this->send->event_id.'.invitation-sends'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'invitation.send.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->send->loadMissing([
            'guest:id,name,phone_number',
            'whatsappDevice:id,name,phone_label',
        ]);

        return [
            'id' => $this->send->id,
            'event_id' => $this->send->event_id,
            'guest_id' => $this->send->guest_id,
            'phone_number' => $this->send->phone_number,
            'status' => $this->send->status,
            'error_message' => $this->send->error_message,
            'sent_at' => $this->send->sent_at?->toIso8601String(),
            'created_at' => $this->send->created_at?->toIso8601String(),
            'guest' => $this->send->guest
                ? [
                    'id' => $this->send->guest->id,
                    'name' => $this->send->guest->name,
                    'phone_number' => $this->send->guest->phone_number,
                ]
                : null,
            'device' => $this->send->whatsappDevice
                ? [
                    'id' => $this->send->whatsappDevice->id,
                    'name' => $this->send->whatsappDevice->name,
                    'phone_label' => $this->send->whatsappDevice->phone_label,
                ]
                : null,
        ];
    }
}
