<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with(['events:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->serialize($user));

        return response()->json(['data' => $users]);
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();
        $eventIds = $data['event_ids'] ?? [];
        unset($data['event_ids']);

        $user = User::query()->create($data);
        $this->syncEvents($user, $eventIds);

        return response()->json(['data' => $this->serialize($user->fresh('events'))], 201);
    }

    public function show(User $user)
    {
        return response()->json(['data' => $this->serialize($user->load('events'))]);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();
        $eventIds = array_key_exists('event_ids', $data) ? ($data['event_ids'] ?? []) : null;
        unset($data['event_ids']);

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        if (
            isset($data['role'])
            && $data['role'] === User::ROLE_PANITIA
            && $user->isAdmin()
            && $this->isLastAdmin($user)
        ) {
            throw ValidationException::withMessages([
                'role' => ['Tidak dapat mengubah role admin terakhir.'],
            ]);
        }

        $user->fill($data)->save();

        if ($eventIds !== null || ($data['role'] ?? $user->role) === User::ROLE_ADMIN) {
            $role = $data['role'] ?? $user->role;
            $this->syncEvents($user, $role === User::ROLE_ADMIN ? [] : ($eventIds ?? []));
        }

        return response()->json(['data' => $this->serialize($user->fresh('events'))]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['Tidak dapat menghapus akun sendiri.'],
            ]);
        }

        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => ['Tidak dapat menghapus admin terakhir.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function isLastAdmin(User $user): bool
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    /**
     * @param  list<int>  $eventIds
     */
    private function syncEvents(User $user, array $eventIds): void
    {
        if ($user->isAdmin()) {
            $user->events()->sync([]);

            return;
        }

        $user->events()->sync($eventIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        $user->loadMissing('events:id,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'assigned_events' => $user->events
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                ])
                ->values()
                ->all(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
