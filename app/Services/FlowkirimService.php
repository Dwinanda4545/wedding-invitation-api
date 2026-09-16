<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlowkirimService
{
    /**
     * @return array{provider_message_id: string|null, raw: array<string, mixed>}
     */
    public function sendText(string $to, string $message, ?string $providerDeviceId = null): array
    {
        $this->assertConfigured();

        $base = rtrim((string) config('flowkirim.base_url'), '/');
        $path = (string) config('flowkirim.send_path', '/api/whatsapp/messages/text');
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.ltrim($path, '/');
        }
        $url = $base.$path;
        $timeout = max(1, (int) config('flowkirim.timeout', 20));

        $payload = [
            'to' => $this->formatRecipient($to),
            'message' => $message,
        ];

        if (config('flowkirim.link_preview', true)) {
            $payload['linkPreview'] = true;
        }

        $field = (string) config('flowkirim.device_field', 'session_id');
        if ($providerDeviceId !== null && $providerDeviceId !== '' && $field !== '') {
            $payload[$field] = $providerDeviceId;
        }

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->withToken((string) config('flowkirim.api_token'))
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('FlowKirim connection failed: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->formatError($response));
        }

        $body = $response->json() ?? [];

        return [
            'provider_message_id' => $this->extractMessageId($body),
            'raw' => is_array($body) ? $body : [],
        ];
    }

    public function isConfigured(): bool
    {
        return (string) config('flowkirim.api_token') !== '';
    }

    private function formatRecipient(string $to): string
    {
        $to = trim($to);
        if ($to === '') {
            return $to;
        }

        if (! config('flowkirim.append_jid_suffix', true)) {
            return $to;
        }

        if (str_contains($to, '@')) {
            return $to;
        }

        return $to.'@s.whatsapp.net';
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('FlowKirim is not configured.');
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractMessageId(array $body): ?string
    {
        $candidates = [
            data_get($body, 'id'),
            data_get($body, 'message_id'),
            data_get($body, 'data.id'),
            data_get($body, 'data.message_id'),
            data_get($body, 'key.id'),
            data_get($body, 'data.key.id'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function formatError(Response $response): string
    {
        $body = $response->body();
        $snippet = strlen($body) > 500 ? substr($body, 0, 500).'…' : $body;

        return 'FlowKirim request failed (HTTP '.$response->status().'): '.$snippet;
    }
}
