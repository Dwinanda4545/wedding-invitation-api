<?php

namespace App\Support;

class SectionCustomSanitizer
{
    public const MAX_CHARS = 200000;

    public const MAX_LIBRARIES = 20;

    /** @var list<string> */
    public const ELIGIBLE_KEYS = [
        'cover',
        'hero',
        'couple',
        'schedule',
        'love_story',
        'gallery',
        'wishes',
        'hosts',
    ];

    public static function isHttpsUrl(string $src): bool
    {
        $src = trim($src);
        if ($src === '' || preg_match('/\s/', $src) === 1) {
            return false;
        }

        $lower = strtolower($src);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return false;
        }

        return filter_var($src, FILTER_VALIDATE_URL) !== false
            && str_starts_with($lower, 'https://');
    }

    public static function isEligibleKey(string $key): bool
    {
        if ($key === 'qr') {
            return false;
        }

        if (in_array($key, self::ELIGIBLE_KEYS, true)) {
            return true;
        }

        return str_starts_with($key, 'custom:') && strlen($key) > strlen('custom:');
    }

    /**
     * @param  mixed  $raw
     * @return array<string, array{mode: string, html: string, css: string, js: string, libraries: list<array{id: string, name: string, src: string, kind: string}>}>
     */
    public static function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || ! self::isEligibleKey($key) || ! is_array($value)) {
                continue;
            }

            $out[$key] = [
                'mode' => ($value['mode'] ?? '') === 'custom' ? 'custom' : 'existing',
                'html' => self::clamp((string) ($value['html'] ?? '')),
                'css' => self::clamp((string) ($value['css'] ?? '')),
                'js' => self::clamp((string) ($value['js'] ?? '')),
                'libraries' => self::sanitizeLibraries($value['libraries'] ?? []),
            ];
        }

        return $out;
    }

    private static function clamp(string $value): string
    {
        if (strlen($value) <= self::MAX_CHARS) {
            return $value;
        }

        return substr($value, 0, self::MAX_CHARS);
    }

    /**
     * @param  mixed  $raw
     * @return list<array{id: string, name: string, src: string, kind: string}>
     */
    private static function sanitizeLibraries(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $index => $lib) {
            if (count($out) >= self::MAX_LIBRARIES) {
                break;
            }
            if (! is_array($lib) || ! is_string($lib['src'] ?? null)) {
                continue;
            }
            $src = trim($lib['src']);
            if (! self::isHttpsUrl($src)) {
                continue;
            }

            $kind = ($lib['kind'] ?? '') === 'css' ? 'css' : 'js';
            $id = is_string($lib['id'] ?? null) && $lib['id'] !== ''
                ? $lib['id']
                : 'lib_'.$index;
            $name = is_string($lib['name'] ?? null) && trim($lib['name']) !== ''
                ? $lib['name']
                : $src;

            $out[] = [
                'id' => $id,
                'name' => $name,
                'src' => $src,
                'kind' => $kind,
            ];
        }

        return $out;
    }
}
