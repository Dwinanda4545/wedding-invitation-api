<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    public const DEFAULT_UNIVERSAL_GREETING = 'Yth. Bapak/Ibu/Saudara/i';

    protected $fillable = [
        'name',
        'slug',
        'event_date',
        'location',
        'invitation_template',
        'invitation_style',
        'invitation_content',
        'invitation_mode',
        'couple_info',
        'invitation_settings',
        'hosts',
        'whatsapp_device_id',
        'universal_invitation_token',
        'universal_invitation_enabled',
        'universal_greeting',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'invitation_style' => 'array',
        'couple_info' => 'array',
        'invitation_settings' => 'array',
        'hosts' => 'array',
        'universal_invitation_enabled' => 'boolean',
    ];

    public static function findEnabledUniversal(string $token): ?self
    {
        return static::query()
            ->where('universal_invitation_token', $token)
            ->where('universal_invitation_enabled', true)
            ->first();
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function guestRelations()
    {
        return $this->hasMany(GuestRelation::class)->orderBy('sort_order')->orderBy('id');
    }

    public function schedules()
    {
        return $this->hasMany(EventSchedule::class)->orderBy('sort_order');
    }

    public function loveStories()
    {
        return $this->hasMany(LoveStory::class)->orderBy('sort_order');
    }

    public function galleryImages()
    {
        return $this->hasMany(EventGalleryImage::class)->orderBy('sort_order');
    }

    public function wishes()
    {
        return $this->hasMany(InvitationWish::class)->latest();
    }

    public function envelopeTransactions()
    {
        return $this->hasMany(EnvelopeTransaction::class)->latest();
    }

    public function invitationSends()
    {
        return $this->hasMany(InvitationSend::class)->latest();
    }

    public function whatsappDevice()
    {
        return $this->belongsTo(WhatsappDevice::class);
    }

    public function panitiaUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
