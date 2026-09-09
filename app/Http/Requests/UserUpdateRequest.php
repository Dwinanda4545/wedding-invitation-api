<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['sometimes', 'required', Rule::in([User::ROLE_ADMIN, User::ROLE_PANITIA])],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['integer', 'exists:events,id'],
        ];
    }
}
