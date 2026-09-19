<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'phone_number',
        'guest_type',
        'guest_relation_id',
        'secret_token',
        'qr_code_path',
        'is_attended',
        'scanned_at',
    ];

    protected $casts = [
        'is_attended' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function relation()
    {
        return $this->belongsTo(GuestRelation::class, 'guest_relation_id');
    }

    public function wishes()
    {
        return $this->hasMany(InvitationWish::class);
    }

    public function invitationSends()
    {
        return $this->hasMany(InvitationSend::class)->latest();
    }

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            if (! empty($guest->secret_token)) {
                return;
            }

            do {
                $token = Str::random(64);
            } while (static::where('secret_token', $token)->exists());

            $guest->secret_token = $token;
        });
    }
}
