<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappDevice extends Model
{
    protected $fillable = [
        'name',
        'provider_device_id',
        'phone_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function invitationSends(): HasMany
    {
        return $this->hasMany(InvitationSend::class);
    }
}
