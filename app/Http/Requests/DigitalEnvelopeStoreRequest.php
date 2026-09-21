<?php

namespace App\Http\Requests;

use App\Models\Guest;
use App\Models\UniversalInvitation;
use App\Support\EnvelopeSettings;
use Illuminate\Foundation\Http\FormRequest;

class DigitalEnvelopeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $event = null;

        if ($this->route('secret_token')) {
            $event = Guest::query()
                ->where('secret_token', $this->route('secret_token'))
                ->with('event')
                ->first()
                ?->event;
        } elseif ($this->route('token')) {
            $event = UniversalInvitation::findEnabledByToken((string) $this->route('token'))?->event;
        }

        $settings = $event
            ? EnvelopeSettings::fromEvent($event)
            : ['min_amount' => 10000, 'max_amount' => 10000000];

        $min = (int) $settings['min_amount'];
        $max = (int) $settings['max_amount'];

        return [
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:50'],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'amount' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
