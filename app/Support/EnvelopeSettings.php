<?php

namespace App\Support;

use App\Models\Event;

class EnvelopeSettings
{
    /**
     * @return array{
     *     enabled: bool,
     *     presets: list<int>,
     *     min_amount: int,
     *     max_amount: int
     * }
     */
    public static function fromEvent(Event $event): array
    {
        $settings = is_array($event->invitation_settings)
            ? $event->invitation_settings
            : [];

        $sections = is_array($settings['sections'] ?? null) ? $settings['sections'] : [];
        $envelope = is_array($settings['digital_envelope'] ?? null)
            ? $settings['digital_envelope']
            : [];

        $presets = $envelope['presets'] ?? [50000, 100000, 200000];
        if (! is_array($presets)) {
            $presets = [50000, 100000, 200000];
        }

        $presets = array_values(array_map('intval', $presets));

        return [
            'enabled' => ($sections['digital_envelope'] ?? false) === true,
            'presets' => $presets,
            'min_amount' => max(1, (int) ($envelope['min_amount'] ?? 10000)),
            'max_amount' => max(1, (int) ($envelope['max_amount'] ?? 10000000)),
        ];
    }
}
