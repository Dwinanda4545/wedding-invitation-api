<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\DokuNotificationController;
use App\Http\Controllers\Api\DigitalEnvelopeController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventEnvelopeController;
use App\Http\Controllers\Api\EventDecorController;
use App\Http\Controllers\Api\EventGalleryController;
use App\Http\Controllers\Api\EventInvitationController;
use App\Http\Controllers\Api\EventMusicController;
use App\Http\Controllers\Api\EventPanitiaController;
use App\Http\Controllers\Api\EventScheduleController;
use App\Http\Controllers\Api\GuestbookController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\InvitationThemeController;
use App\Http\Controllers\Api\InvitationWishController;
use App\Http\Controllers\Api\LoveStoryController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['ok' => true]);

Route::get('/invitation/{secret_token}', [InvitationController::class, 'show'])
    ->where('secret_token', '[A-Za-z0-9]+');

Route::post('/invitation/{secret_token}/wishes', [InvitationWishController::class, 'store'])
    ->where('secret_token', '[A-Za-z0-9]+');
Route::patch('/invitation/{secret_token}/wishes', [InvitationWishController::class, 'update'])
    ->where('secret_token', '[A-Za-z0-9]+');

Route::post('/invitation/{secret_token}/digital-envelopes', [DigitalEnvelopeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->where('secret_token', '[A-Za-z0-9]+');

Route::post('/doku/notification', [DokuNotificationController::class, 'handle']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/check-in', [CheckInController::class, 'store']);

    Route::get('/events/{event}/guestbook', [GuestbookController::class, 'index']);
    Route::post('/events/{event}/guests/{guest}/check-in', [GuestbookController::class, 'checkIn']);
    Route::post('/events/{event}/guests/{guest}/check-in/cancel', [GuestbookController::class, 'cancelCheckIn']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::put('/events/{event}', [EventController::class, 'update']);
        Route::patch('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);

        Route::get('/events/{event}/invitation', [EventInvitationController::class, 'show']);
        Route::put('/events/{event}/invitation', [EventInvitationController::class, 'update']);
        Route::patch('/events/{event}/invitation', [EventInvitationController::class, 'update']);

        Route::post('/events/{event}/schedules', [EventScheduleController::class, 'store']);
        Route::put('/events/{event}/schedules/{schedule}', [EventScheduleController::class, 'update']);
        Route::patch('/events/{event}/schedules/{schedule}', [EventScheduleController::class, 'update']);
        Route::delete('/events/{event}/schedules/{schedule}', [EventScheduleController::class, 'destroy']);

        Route::post('/events/{event}/love-stories', [LoveStoryController::class, 'store']);
        Route::put('/events/{event}/love-stories/{loveStory}', [LoveStoryController::class, 'update']);
        Route::patch('/events/{event}/love-stories/{loveStory}', [LoveStoryController::class, 'update']);
        Route::delete('/events/{event}/love-stories/{loveStory}', [LoveStoryController::class, 'destroy']);

        Route::post('/events/{event}/gallery', [EventGalleryController::class, 'store']);
        Route::delete('/events/{event}/gallery/{galleryImage}', [EventGalleryController::class, 'destroy']);

        Route::post('/events/{event}/music', [EventMusicController::class, 'store']);

        Route::post('/events/{event}/decor', [EventDecorController::class, 'store']);
        Route::delete('/events/{event}/decor/{filename}', [EventDecorController::class, 'destroy']);

        Route::get('/events/{event}/envelope-transactions', [EventEnvelopeController::class, 'index']);

        Route::get('/events/{event}/guests', [GuestController::class, 'index']);
        Route::post('/events/{event}/guests', [GuestController::class, 'store']);
        Route::get('/events/{event}/guests/{guest}', [GuestController::class, 'show']);
        Route::put('/events/{event}/guests/{guest}', [GuestController::class, 'update']);
        Route::patch('/events/{event}/guests/{guest}', [GuestController::class, 'update']);
        Route::delete('/events/{event}/guests/{guest}', [GuestController::class, 'destroy']);

        Route::post('/events/{event}/guests/import', [GuestController::class, 'import']);
        Route::post('/events/{event}/guests/{guest}/qr/regenerate', [GuestController::class, 'regenerateQr']);

        Route::get('/events/{event}/panitia', [EventPanitiaController::class, 'index']);
        Route::post('/events/{event}/panitia', [EventPanitiaController::class, 'store']);
        Route::delete('/events/{event}/panitia/{user}', [EventPanitiaController::class, 'destroy']);

        Route::get('/invitation-themes', [InvitationThemeController::class, 'index']);
        Route::post('/invitation-themes', [InvitationThemeController::class, 'store']);
        Route::put('/invitation-themes/{invitationTheme}', [InvitationThemeController::class, 'update']);
        Route::patch('/invitation-themes/{invitationTheme}', [InvitationThemeController::class, 'update']);
        Route::delete('/invitation-themes/{invitationTheme}', [InvitationThemeController::class, 'destroy']);
    });
});
