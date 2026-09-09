<?php

namespace App\Events;

use App\Models\Guest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestAttendanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Guest $guest) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('event.'.$this->guest->event_id.'.guestbook'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'guest.attendance.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'guest_id' => $this->guest->id,
            'name' => $this->guest->name,
            'guest_type' => $this->guest->guest_type,
            'is_attended' => (bool) $this->guest->is_attended,
            'scanned_at' => $this->guest->scanned_at?->toIso8601String(),
        ];
    }
}
