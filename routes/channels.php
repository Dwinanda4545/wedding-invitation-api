<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('event.{eventId}.guestbook', function (User $user, int $eventId) {
    return $user->canAccessEvent($eventId);
});

Broadcast::channel('event.{eventId}.invitation-sends', function (User $user, int $eventId) {
    return $user->canAccessEvent($eventId);
});
