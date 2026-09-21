<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UniversalInvitation extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'greeting',
        'token',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function resolvedGreeting(): string
    {
        $greeting = trim((string) $this->greeting);

        return $greeting !== '' ? $greeting : Event::DEFAULT_UNIVERSAL_GREETING;
    }

    public function publicUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/invitation/open/'.$this->token;
    }

    public static function findEnabledByToken(string $token): ?self
    {
        return static::query()
            ->where('token', $token)
            ->where('enabled', true)
            ->first();
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }
}
