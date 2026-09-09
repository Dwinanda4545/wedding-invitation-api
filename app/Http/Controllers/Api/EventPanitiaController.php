<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventPanitiaStoreRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EventPanitiaController extends Controller
{
    public function index(Event $event)
    {
        $users = $event->panitiaUsers()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]);

        return response()->json(['data' => $users]);
    }

    public function store(EventPanitiaStoreRequest $request, Event $event)
    {
        $data = $request->validated();

        if (! empty($data['user_id'])) {
            $user = User::query()->findOrFail($data['user_id']);
            if (! $user->isPanitia()) {
                throw ValidationException::withMessages([
                    'user_id' => ['User harus ber-role panitia.'],
                ]);
            }
        } else {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_PANITIA,
            ]);
        }

        $event->panitiaUsers()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function destroy(Event $event, User $user)
    {
        $event->panitiaUsers()->detach($user->id);

        return response()->json(['message' => 'Unassigned']);
    }
}
