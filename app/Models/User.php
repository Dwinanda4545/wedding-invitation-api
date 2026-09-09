<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_PANITIA = 'panitia';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPanitia(): bool
    {
        return $this->role === self::ROLE_PANITIA;
    }

    public function canAccessEvent(Event|int $event): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $eventId = $event instanceof Event ? $event->id : $event;

        return $this->events()->where('events.id', $eventId)->exists();
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, assigned_events: list<array{id: int, name: string}>}
     */
    public function toAuthArray(): array
    {
        $assigned = [];
        if ($this->isPanitia()) {
            $this->loadMissing('events:id,name');
            $assigned = $this->events
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                ])
                ->values()
                ->all();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'assigned_events' => $assigned,
        ];
    }
}
