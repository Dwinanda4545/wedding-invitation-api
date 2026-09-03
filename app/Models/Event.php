<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

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
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'invitation_style' => 'array',
        'couple_info' => 'array',
        'invitation_settings' => 'array',
        'hosts' => 'array',
    ];

    public function guests()
    {
        return $this->hasMany(Guest::class);
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
}
