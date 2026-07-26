<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationTheme extends Model
{
    protected $fillable = [
        'name',
        'description',
        'style',
        'default_content',
    ];

    protected $casts = [
        'style' => 'array',
    ];
}
